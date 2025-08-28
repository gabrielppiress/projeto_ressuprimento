<?php
session_start();
require 'conexao.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$mensagem = "";
$showForm = false;

if (!$token) {
    $mensagem = "Token inválido.";
} else {
    // Validar token
    $stmt = $conn->prepare("SELECT pr.id AS pr_id, pr.user_id, pr.expires_at, u.usuario FROM password_resets pr INNER JOIN usuarios u ON u.id = pr.user_id WHERE pr.token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $mensagem = "Token inválido ou já usado.";
    } else {
        $row = $res->fetch_assoc();
        $expires = $row['expires_at'];
        if (strtotime($expires) < time()) {
            $mensagem = "O token expirou. Solicite uma nova redefinição.";
        } else {
            // Se for GET, mostrar formulário; se POST, processar nova senha
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $nova = $_POST['senha'] ?? '';
                $conf = $_POST['senha_confirm'] ?? '';
                if (strlen($nova) < 6) {
                    $mensagem = "A senha precisa ter ao menos 6 caracteres.";
                    $showForm = true;
                } elseif ($nova !== $conf) {
                    $mensagem = "As senhas não conferem.";
                    $showForm = true;
                } else {
                    // Atualizar senha do usuário
                    $newHash = password_hash($nova, PASSWORD_DEFAULT);
                    $userId = intval($row['user_id']);
                    $upd = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $upd->bind_param("si", $newHash, $userId);
                    $upd->execute();
                    $upd->close();

                    // Remover todos os tokens desse usuário (boas práticas)
                    $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $del->bind_param("i", $userId);
                    $del->execute();
                    $del->close();

                    // redirecionar com mensagem
                    header("Location: index.php?msg=" . urlencode("Senha alterada com sucesso. Faça login."));
                    exit;
                }
            } else {
                $showForm = true;
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Redefinir senha</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Redefinir senha</h2>
        <?php if ($mensagem): ?>
            <p class="mensagem"><?= htmlspecialchars($mensagem) ?></p>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label>Nova senha</label>
                <input type="password" name="senha" placeholder="Nova senha" required>
                <label>Confirme a nova senha</label>
                <input type="password" name="senha_confirm" placeholder="Confirme a senha" required>
                <button type="submit" class="btn-primary">Alterar senha</button>
            </form>
        <?php else: ?>
            <a href="forgot.php"><button class="btn-white">Solicitar novo link</button></a>
            <a href="index.php"><button class="btn-white">Voltar ao login</button></a>
        <?php endif; ?>
    </div>
</body>
</html>
