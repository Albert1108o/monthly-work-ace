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
$cpf = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipo === 'professor') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && $u['senha_hash'] && password_verify($senha, (string) $u['senha_hash'])) {
        if (!hasRole((int) $u['id'], 'admin')) {
            $erro = 'Esta conta não é de professor orientador. Use a aba Estagiário.';
        } else {
            $_SESSION['usuario_id'] = (int) $u['id'];
            header('Location: admin.php');
            exit;
        }
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? '')) ?? '';
    $nascimento = (string) ($_POST['nascimento'] ?? '');

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE cpf = ?');
    $stmt->execute([$cpf]);
    $u = $stmt->fetch();

    if ($u && (string) $u['data_nascimento'] === $nascimento) {
        $_SESSION['usuario_id'] = (int) $u['id'];
        header('Location: index.php');
        exit;
    }
    $erro = 'CPF ou data de nascimento incorretos. Peça ao professor orientador para cadastrar você.';
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

  <?php if ($tipo === 'professor'): ?>
  <form method="post" class="card">
    <input type="hidden" name="tipo" value="professor">
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

  <p>Não tem conta? <a href="cadastro.php">Criar conta de professor orientador</a></p>
  <?php else: ?>
  <form method="post" class="card">
    <input type="hidden" name="tipo" value="estagiario">
    <label>Login (CPF)
      <input type="text" name="cpf" inputmode="numeric" value="<?= htmlspecialchars($cpf) ?>" required>
    </label>
    <label>Data de nascimento
      <input type="date" name="nascimento" required>
    </label>
    <button type="submit">Entrar</button>
  </form>

  <p>Estagiários são cadastrados pelo professor orientador.</p>
  <?php endif; ?>
</main>
</body>
</html>
