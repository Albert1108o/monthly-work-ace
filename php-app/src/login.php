<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

iniciarSessao();
if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$tipo = ($_POST['tipo'] ?? $_GET['tipo'] ?? 'estagiario') === 'professor' ? 'professor' : 'estagiario';
$erro = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && password_verify($senha, (string) $u['senha_hash'])) {
        $ehProfessor = hasRole((int) $u['id'], 'admin');
        if ($tipo === 'professor' && !$ehProfessor) {
            $erro = 'Esta conta não é de professor orientador. Use a aba Estagiário.';
        } elseif ($tipo === 'estagiario' && $ehProfessor) {
            $erro = 'Esta conta é de professor orientador. Use a aba Professor orientador.';
        } else {
            $_SESSION['usuario_id'] = (int) $u['id'];
            header('Location: ' . ($ehProfessor ? 'admin.php' : 'index.php'));
            exit;
        }
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}

$titulo = $tipo === 'professor' ? 'Entrar como professor orientador' : 'Entrar como estagiário';
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
  <h1><?= htmlspecialchars($titulo) ?></h1>

  <nav class="abas">
    <a href="login.php?tipo=estagiario" class="<?= $tipo === 'estagiario' ? 'ativa' : '' ?>">Estagiário</a>
    <a href="login.php?tipo=professor" class="<?= $tipo === 'professor' ? 'ativa' : '' ?>">Professor orientador</a>
  </nav>

  <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <label>E-mail
      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
    </label>
    <label>Senha
      <input type="password" name="senha" id="senha" required>
    </label>
    <label class="ver-senha">
      <input type="checkbox" onclick="document.getElementById('senha').type = this.checked ? 'text' : 'password'">
      Ver senha
    </label>
    <button type="submit">Entrar</button>
  </form>

  <p>Não tem conta? <a href="cadastro.php?tipo=<?= htmlspecialchars($tipo) ?>">Criar conta de <?= $tipo === 'professor' ? 'professor orientador' : 'estagiário' ?></a></p>
</main>
</body>
</html>
