<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

iniciarSessao();
if (usuarioLogado()) {
    header('Location: index.php');
    exit;
}

$erro = null;
$nome = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $senha2 = (string) ($_POST['senha2'] ?? '');

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
        $erro = 'Preencha o nome, um e-mail válido e uma senha com pelo menos 6 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não conferem.';
    } else {
        $existe = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $existe->execute([$email]);
        if ($existe->fetch()) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            $stmt = db()->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?) RETURNING id');
            $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            $novoId = (int) $stmt->fetchColumn();
            $_SESSION['usuario_id'] = $novoId;
            addRole($novoId, 'admin');
            header('Location: admin.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criar conta — Controle de Horas</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <h1>Criar conta de professor orientador</h1>

  <p>Apenas professores orientadores criam a própria conta. Os estagiários são cadastrados pelo professor.</p>

  <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

  <form method="post" class="card">
    <label>Nome
      <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required>
    </label>
    <label>E-mail
      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
    </label>
    <label>Senha
      <input type="password" name="senha" id="senha" minlength="6" required>
    </label>
    <label>Repetir senha
      <input type="password" name="senha2" id="senha2" minlength="6" required>
    </label>
    <label class="ver-senha">
      <input type="checkbox" onclick="var t = this.checked ? 'text' : 'password'; document.getElementById('senha').type = t; document.getElementById('senha2').type = t;">
      Ver senhas
    </label>
    <button type="submit">Cadastrar</button>
  </form>

  <p>Já tem conta? <a href="login.php?tipo=professor">Entrar</a></p>
</main>
</body>
</html>
