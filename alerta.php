<?php
session_start();
if(!isset($_SESSION['usuario'])) { header("Location: index.php"); exit; }
require 'conexao.php';

$sql = "SELECT nome, estoque_atual, estoque_min FROM produtos WHERE estoque_atual < estoque_min ORDER BY nome ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alerta de Ressuprimento</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>⚠️ Produtos em Falta</h2>
        <?php if($result && $result->num_rows > 0): ?>
            <ul>
            <?php while($row = $result->fetch_assoc()): ?>
                <li><?= htmlspecialchars($row['nome']) ?> - Atual: <?= intval($row['estoque_atual']) ?> (Mín: <?= intval($row['estoque_min']) ?>)</li>
            <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>✅ Nenhum produto em falta!</p>
        <?php endif; ?>
        <a href="dashboard.php"><button class="btn-branco" type="button">Voltar</button></a>
    </div>
</body>
</html>
