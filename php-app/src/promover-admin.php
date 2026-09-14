<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

exigirAdmin();

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u) {
            $erro = 'Usuário não encontrado.';
        } else {
            addRole((int) $u['id'], 'admin');
            $sucesso = 'Usuário promovido a administrador.';
        }
    }
}

$stmt = db()->prepare(
    'SELECT u.id, u.nome, u.email FROM usuarios u
     WHERE EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role = ?)
     ORDER BY u.nome'
);
$stmt->execute(['admin']);
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Promover administrador — Controle de Horas</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <h1>Promover administrador</h1>

  <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
  <?php if ($sucesso): ?><p style="color:#8aff8a;"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

  <form method="post" class="card">
    <label>E-mail do usuário
      <input type="email" name="email" required>
    </label>
    <button type="submit">Tornar administrador</button>
  </form>

  <h2>Administradores atuais</h2>
  <ul class="card">
    <?php foreach ($admins as $a): ?>
      <li><?= htmlspecialchars((string) $a['nome']) ?> — <?= htmlspecialchars((string) $a['email']) ?></li>
    <?php endforeach; ?>
    <?php if (!$admins): ?>
      <li class="vazio">Nenhum administrador.</li>
    <?php endif; ?>
  </ul>

  <p><a href="admin.php">Voltar à administração</a></p>
</main>
</body>
</html>
