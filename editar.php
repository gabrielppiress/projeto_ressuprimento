<?php
session_start();
if(!isset($_SESSION['usuario'])) { header("Location: index.php"); exit; }
require 'conexao.php';

// Atualizar estoque (via POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quantidade']) && isset($_POST['acao']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $acao = $_POST['acao'];
    $quantidade = max(0, intval($_POST['quantidade']));

    if ($acao === "entrada") {
        $stmt = $conn->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?");
        $stmt->bind_param("ii", $quantidade, $id);
    } else {
        // saída
        $stmt = $conn->prepare("UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?");
        $stmt->bind_param("ii", $quantidade, $id);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php");
    exit;
}

// Buscar produtos para o select
$result = $conn->query("SELECT id, nome, estoque_atual FROM produtos ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Editar Estoque</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>✏️ Editar Estoque</h2>
        <form method="POST">
            <label>Produto:</label><br>
            <select name="id" required>
                <?php while($row = $result->fetch_assoc()): ?>
                    <option value="<?= intval($row['id']) ?>"><?= htmlspecialchars($row['nome']) ?> (Atual: <?= intval($row['estoque_atual']) ?>)</option>
                <?php endwhile; ?>
            </select><br><br>

            <label>Quantidade:</label><br>
            <input type="number" name="quantidade" min="1" required><br><br>

            <input type="radio" name="acao" value="entrada" required> Entrada
            <input type="radio" name="acao" value="saida"> Saída <br><br>

            <button type="submit" class="btn-vermelho">Atualizar Estoque</button>
        </form>
        <a href="dashboard.php"><button class="btn-branco" type="button">Voltar</button></a>
    </div>
</body>
</html>
