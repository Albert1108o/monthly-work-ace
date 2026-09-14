<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

iniciarSessao();
if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && password_verify($senha, (string) $u['senha_hash'])) {
        $_SESSION['usuario_id'] = (int) $u['id'];
        header('Location: index.php');
        exit;
    }
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar — Controle de Horas</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <h1>Entrar</h1>

  <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

  <form method="post" class="card">
    <label>E-mail
      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
    </label>
    <label>Senha
      <input type="password" name="senha" required>
    </label>
    <button type="submit">Entrar</button>
  </form>

  <p>Não tem conta? <a href="cadastro.php">Criar conta</a></p>
</main>
</body>
</html>
