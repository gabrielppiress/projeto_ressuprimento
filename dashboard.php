<?php
session_start();
require 'conexao.php';

// Proteção: se não estiver logado, redireciona para login
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

// Funções utilitárias
function safeInt($v, $default = 0) {
    return isset($v) ? intval($v) : $default;
}
function safeFloat($v, $default = 0.0) {
    return isset($v) ? floatval($v) : $default;
}

// Processar ações vindas do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_stock') {
        // update estoque (entrada/saída)
        $id = intval($_POST['id']);
        $change = intval($_POST['change']); // pode ser +1, -1, ou outra quantidade enviada
        // Atualiza com prepared statement
        $stmt = $conn->prepare("UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual + ?) WHERE id = ?");
        $stmt->bind_param("ii", $change, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: dashboard.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // atualizar configurações do produto
        $id = intval($_POST['id']);
        $estoque_min = safeInt($_POST['estoque_min']);
        $estoque_max = safeInt($_POST['estoque_max']);
        $consumo_diario = safeInt($_POST['consumo_diario']);
        $tempo_producao = safeInt($_POST['tempo_producao']);
        $custo_unitario = safeFloat($_POST['custo_unitario']);
        $fornecedor = isset($_POST['fornecedor']) ? $_POST['fornecedor'] : '';

        $stmt = $conn->prepare("UPDATE produtos SET estoque_min = ?, estoque_max = ?, consumo_diario = ?, tempo_producao = ?, custo_unitario = ? WHERE id = ?");
        $stmt->bind_param("iiiddi", $estoque_min, $estoque_max, $consumo_diario, $tempo_producao, $custo_unitario, $id);

        $stmt->execute();
        $stmt->close();

        header("Location: dashboard.php?tab=settings");
        exit;
    }
}

// Buscar produtos do banco
$produtos = [];
$res = $conn->query("SELECT * FROM produtos ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $produtos[] = $row;
    }
    $res->close();
}

// Funções para calcular status/ponto de resuprimento etc.
function calculateReorderPoint($p) {
    $daily = isset($p['consumo_diario']) && intval($p['consumo_diario'])>0 ? intval($p['consumo_diario']) : 1;
    $prod_time = isset($p['tempo_producao']) && intval($p['tempo_producao'])>0 ? intval($p['tempo_producao']) : 1;
    $min_stock = isset($p['estoque_min']) ? intval($p['estoque_min']) : 0;
    return ($daily * $prod_time) + $min_stock;
}
function calculateOrderQuantity($p) {
    $cur = isset($p['estoque_atual']) ? intval($p['estoque_atual']) : 0;
    $max = isset($p['estoque_max']) ? intval($p['estoque_max']) : ($cur + 10);
    $reorder = calculateReorderPoint($p);
    if ($cur <= $reorder) return max(0, $max - $cur);
    return 0;
}
function getStockStatus($p) {
    $cur = isset($p['estoque_atual']) ? intval($p['estoque_atual']) : 0;
    $min = isset($p['estoque_min']) ? intval($p['estoque_min']) : 0;
    $reorder = calculateReorderPoint($p);
    if ($cur <= $min) return 'critical';
    if ($cur <= $reorder) return 'warning';
    return 'normal';
}
function getDaysRemaining($p) {
    $daily = isset($p['consumo_diario']) && intval($p['consumo_diario'])>0 ? intval($p['consumo_diario']) : 1;
    $cur = isset($p['estoque_atual']) ? intval($p['estoque_atual']) : 0;
    return floor($cur / $daily);
}

// Resumo geral
$summary = [
    'total_stock' => 0,
    'critical_count' => 0,
    'warning_count' => 0,
    'total_order_value' => 0.0
];
foreach ($produtos as $p) {
    $summary['total_stock'] += intval($p['estoque_atual']);
    $status = getStockStatus($p);
    if ($status === 'critical') $summary['critical_count']++;
    if ($status === 'warning') $summary['warning_count']++;
    $summary['total_order_value'] += calculateOrderQuantity($p) * (isset($p['custo_unitario']) ? floatval($p['custo_unitario']) : 0.0);
}

$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Estoque</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Pequeno CSS adicional para o dashboard (mantive o estilo geral) */
        body { font-family: Arial, sans-serif; background:#f5f5f5; margin:0; padding:0; }
        .header { background:#2563eb; color:#fff; padding:12px 20px; display:flex; justify-content:space-between; align-items:center; }
        .nav-links a { color:#fff; margin-left:12px; text-decoration:none; font-weight:600; }
        .container { max-width:1100px; margin:20px auto; padding:0 16px; }
        .cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap:16px; }
        .card { background:#fff; border-radius:8px; padding:12px; border:2px solid #ddd; }
        .card.normal { border-color:#16a34a; background:#f0fdf4; }
        .card.warning { border-color:#eab308; background:#fffbeb; }
        .card.critical { border-color:#dc2626; background:#fff1f2; }
        .card h3 { display:flex; justify-content:space-between; align-items:center; margin:0 0 8px 0; }
        .summary { display:grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-top:16px; }
        .summary .box { background:#fff; padding:12px; border-radius:8px; text-align:center; }
    </style>
</head>
<body>
    <div class="header">
        <div><strong>📦 Sistema de Estoque</strong> — Usuário: <?= htmlspecialchars($_SESSION['usuario']) ?></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="estoque.php">Estoque</a>
            <a href="editar.php">Editar Estoque</a>
            <a href="alerta.php">Alertas</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <?php if ($currentTab === 'dashboard'): ?>
            <h2>Dashboard</h2>
            <div class="cards">
                <?php foreach ($produtos as $p): 
                    $status = getStockStatus($p);
                    $reorder = calculateReorderPoint($p);
                    $orderQty = calculateOrderQuantity($p);
                    $days = getDaysRemaining($p);
                ?>
                    <div class="card <?= $status ?>">
                        <h3>
                            <?= htmlspecialchars($p['nome']) ?>
                            <?php if ($status !== 'normal'): ?>
                                <span><?= $status === 'critical' ? '🔴 Crítico' : '⚠️ Alerta' ?></span>
                            <?php endif; ?>
                        </h3>
                        <div>
                            <div>Estoque Atual: <strong><?= intval($p['estoque_atual']) ?></strong></div>
                            <div>Estoque Mínimo: <?= intval($p['estoque_min']) ?></div>
                            <div>Ponto de Resuprimento: <strong><?= $reorder ?></strong></div>
                            <div>Dias Restantes: <strong><?= $days ?></strong></div>
                        </div>

                        <?php if ($orderQty > 0): ?>
                        <?php $valorEstimado = $orderQty * (isset($p['custo_unitario']) ? floatval($p['custo_unitario']) : 10); ?>
                            <div style="margin-top:8px; padding:8px; background:#fff; border:1px solid #eee; border-radius:6px;">
                            <div style="font-weight:bold; color:<?= $status === 'critical' ? '#dc2626' : '#eab308' ?>">
                            AÇÃO NECESSÁRIA
                            </div>
                            <div>Quantidade a pedir: <strong><?= $orderQty ?></strong></div>
                            <div>Valor estimado: <strong>R$ <?= number_format($valorEstimado, 2, ',', '.') ?></             strong></div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:10px; display:flex; gap:8px;">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_stock">
                                <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                                <input type="hidden" name="change" value="-1">
                                <button type="submit" class="btn-vermelho">➖ Saída</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_stock">
                                <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                                <input type="hidden" name="change" value="1">
                                <button type="button" onclick="addEntrada(<?= intval($p['id']) ?>)" class="btn-branco">➕ Entrada</button>
                            </form>
                            <!-- botão entrada usa JS para abrir prompt e submeter valor -->
                            <form id="entrada-form-<?= intval($p['id']) ?>" method="POST" style="display:none">
                                <input type="hidden" name="action" value="update_stock">
                                <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                                <input type="hidden" name="change" id="entrada-change-<?= intval($p['id']) ?>" value="1">
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary" style="margin-top:18px;">
                <div class="box">
                    <div style="font-size:20px; font-weight:bold;"><?= $summary['total_stock'] ?></div>
                    <div>Total em Estoque</div>
                </div>
                <div class="box">
                    <div style="font-size:20px; font-weight:bold;"><?= $summary['critical_count'] ?></div>
                    <div>Críticos</div>
                </div>
                <div class="box">
                    <div style="font-size:20px; font-weight:bold;"><?= $summary['warning_count'] ?></div>
                    <div>Em Alerta</div>
                </div>
                <div class="box">
                    <div style="font-size:16px; font-weight:bold;">R$ <?= number_format($summary['total_order_value'], 2, ',', '.') ?></div>
                    <div>Valor total a pedir</div>
                </div>
            </div>

        <?php else: ?>
            <h2>Configurações</h2>
            <?php foreach ($produtos as $p): ?>
                <div style="background:#fff; padding:12px; margin-bottom:12px; border-radius:8px;">
                    <h4><?= htmlspecialchars($p['nome']) ?></h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                        <label>Estoque Mínimo</label><br>
                        <input type="number" name="estoque_min" value="<?= intval($p['estoque_min']) ?>" required><br>
                        <label>Estoque Máximo</label><br>
                        <input type="number" name="estoque_max" value="<?= intval($p['estoque_max']) ?>" required><br>
                        <label>Consumo Diário</label><br>
                        <input type="number" name="consumo_diario" value="<?= intval($p['consumo_diario']) ?>" required><br>
                        <label>Tempo de Produção (dias)</label><br>
                        <input type="number" name="tempo_producao" value="<?= intval($p['tempo_producao']) ?>" required><br>
                        <label>Custo Unitário (R$)</label><br>
                        <input type="number" step="0.01" name="custo_unitario" value="<?= isset($p['custo_unitario']) ? number_format(floatval($p['custo_unitario']),2,'.','') : '0.00' ?>" required><br>
                        <button type="submit" class="btn-vermelho">Salvar Configurações</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function addEntrada(id) {
            var q = prompt("Quantidade para entrada (número):", "1");
            if (!q) return;
            q = parseInt(q);
            if (isNaN(q) || q <= 0) { alert("Quantidade inválida"); return; }
            document.getElementById('entrada-change-' + id).value = q;
            document.getElementById('entrada-form-' + id).submit();
        }
    </script>
</body>
</html>
