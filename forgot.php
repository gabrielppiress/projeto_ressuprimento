<?php
session_start();
require 'conexao.php'; // deve definir $conn (mysqli)

/*
 * forgot.php
 * - mostra formulário para email/usuário
 * - ao POST: valida, cria token, salva em password_resets e envia email com link
 */

$mensagem = "";
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['email_or_user'] ?? '');

    if ($input === '') {
        $mensagem = "Por favor informe seu e-mail ou usuário.";
    } else {
        // Tenta localizar usuário por email ou por nome de usuário
        $sql = $conn->prepare("SELECT id, email, usuario FROM usuarios WHERE email = ? OR usuario = ? LIMIT 1");
        $sql->bind_param("ss", $input, $input);
        $sql->execute();
        $res = $sql->get_result();

        // Segurança: sempre responder com sucesso (não vazar se e-mail existe)
        $mensagem = "Se a conta existir, você receberá um e-mail com instruções para redefinir a senha.";

        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            $userId = intval($user['id']);
            $userEmail = $user['email'];

            // Gerar token seguro
            $token = bin2hex(random_bytes(24)); // 48 hex chars ~ 24 bytes
            $expires = date('Y-m-d H:i:s', time() + 3600); // expira em 1 hora

            // Inserir no banco (password_resets)
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $userId, $token, $expires);
            $stmt->execute();
            $stmt->close();

            // Montar link de reset (ajuste domain)
            // IMPORTANTE: troque example.com pelo seu domínio / ip
            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST']) . dirname($_SERVER['REQUEST_URI']) . "/reset.php?token=" . $token;

            // Enviar email (usando PHPMailer preferencialmente)
            require_once 'mail_config.php'; // arquivo template com função sendMailReset($to,$subject,$body)
            $subject = "Redefinição de senha - Sistema de Estoque";
            $body = "
                <p>Olá " . htmlspecialchars($user['usuario']) . ",</p>
                <p>Recebemos uma solicitação para redefinir sua senha. Clique no link abaixo para criar uma nova senha. O link expira em 1 hora.</p>
                <p><a href=\"$resetLink\">Redefinir minha senha</a></p>
                <p>Se você não solicitou, ignore esta mensagem.</p>
            ";

            // Envial via função que você irá configurar em mail_config.php
            $sent = sendMailReset($userEmail, $subject, $body);
            // Não mostramos erro de envio ao usuário por segurança — apenas logue em servidor se quiser.
            // Exemplo: error_log("Reset email send result: " . ($sent ? "OK" : "FAIL"));
        }
    }
    $showForm = false; // sempre exibe mensagem
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Esqueci minha senha</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Esqueci minha senha</h2>
        <?php if ($showForm): ?>
            <p>Informe seu e-mail ou nome de usuário para receber o link de redefinição.</p>
            <form method="POST">
                <input type="text" name="email_or_user" placeholder="E-mail ou usuário" required>
                <button type="submit" class="btn-primary">Enviar instruções</button>
            </form>
            <a href="index.php"><button class="btn-white" type="button">Voltar ao login</button></a>
        <?php else: ?>
            <p class="small text-muted"><?= htmlspecialchars($mensagem) ?></p>
            <a href="index.php"><button class="btn-white" type="button">Voltar ao login</button></a>
        <?php endif; ?>
    </div>
</body>
</html>
