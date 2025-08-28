<?php
session_start();
if(!isset($_SESSION['usuario'])) { header("Location: index.php"); exit; }
require 'conexao.php';

$result = $conn->query("SELECT nome, estoque_min, estoque_atual FROM produtos ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Estoque</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>📦 Estoque Atual</h2>
        <table border="1" width="100%" cellpadding="8" style="background:#fff;border-radius:6px;">
            <tr><th>Produto</th><th>Estoque Mínimo</th><th>Estoque Atual</th></tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['nome']) ?></td>
                <td><?= intval($row['estoque_min']) ?></td>
                <td><?= intval($row['estoque_atual']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <br>
        <a href="dashboard.php"><button class="btn-branco" type="button">Voltar</button></a>
    </div>
</body>
</html>
