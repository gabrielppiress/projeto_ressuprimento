<?php
session_start();
require 'conexao.php';

// Se já logado, redireciona
if (isset($_SESSION['usuario'])) {
    header("Location: dashboard.php");
    exit;
}

$mensagem = "";

// LOGIN
if (isset($_POST['login'])) {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    $sql = $conn->prepare("SELECT id, usuario, senha FROM usuarios WHERE usuario=? LIMIT 1");
    $sql->bind_param("s", $usuario);
    $sql->execute();
    $result = $sql->get_result();

    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($senha, $row['senha'])) {
            $_SESSION['usuario'] = $row['usuario'];
            header("Location: dashboard.php");
            exit;
        } else {
            $mensagem = "❌ Usuário ou senha inválidos!";
        }
    } else {
        $mensagem = "❌ Usuário ou senha inválidos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Estoque</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Que bom ter você aqui!</h2>
        <p>Entre e aproveite o sistema de estoque.</p>

        <form method="POST" class="login-form">
            <input type="text" name="usuario" placeholder="Usuário" required>
            <div class="senha-box">
                <input type="password" id="senha" name="senha" placeholder="Senha" required>
                <span class="toggle-senha" onclick="toggleSenha()">👁</span>
            </div>

            <a href="forgot.php" class="link-senha">Esqueci minha senha</a>

            <button type="submit" name="login" class="btn-primary">Entrar</button>
        </form>

        <a href="cadastro.php"><button type="button" class="btn-white">Criar Conta</button></a>

        <?php if($mensagem): ?>
            <p class="mensagem"><?= htmlspecialchars($mensagem) ?></p>
        <?php endif; ?>

        <?php if(isset($_GET['msg'])): ?>
            <p class="mensagem"><?= htmlspecialchars($_GET['msg']) ?></p>
        <?php endif; ?>
    </div>

    <script>
        function toggleSenha() {
            let campo = document.getElementById("senha");
            campo.type = campo.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>
