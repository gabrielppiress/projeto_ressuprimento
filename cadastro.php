<?php
require 'conexao.php';

$mensagem = "";

if (isset($_POST['cadastro'])) {
    $usuario = $_POST['usuario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT * FROM usuarios WHERE usuario=? LIMIT 1");
    $check->bind_param("s", $usuario);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $mensagem = "⚠️ Usuário já existe!";
    } else {
        $sql = $conn->prepare("INSERT INTO usuarios (usuario, senha) VALUES (?, ?)");
        $sql->bind_param("ss", $usuario, $senha);
        if ($sql->execute()) {
            $mensagem = "✅ Usuário cadastrado com sucesso!";
        } else {
            $mensagem = "❌ Erro ao cadastrar!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Conta</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Criar Conta</h2>
        <p>Preencha os dados abaixo para se cadastrar:</p>

        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuário" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit" name="cadastro" class="btn-vermelho">Cadastrar</button>
        </form>

        <a href="index.php"><button type="button" class="btn-branco">Voltar ao Login</button></a>

        <?php if($mensagem): ?>
            <p class="mensagem"><?= $mensagem ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
