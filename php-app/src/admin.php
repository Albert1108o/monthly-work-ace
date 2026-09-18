<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$usuario = exigirAdmin();

$aviso = null;
$sucesso = null;

$acao = $_POST['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'excluir_usuario') {
    $alvo = (int) ($_POST['id'] ?? 0);
    if ($alvo === (int) $usuario['id']) {
        $aviso = 'Você não pode excluir a sua própria conta.';
    } elseif ($alvo > 0) {
        $stmt = db()->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->execute([$alvo]);
        $sucesso = 'Usuário excluído junto com todos os registros dele.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'cadastrar_aluno') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $cpf = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? '')) ?? '';
    $nascimento = (string) ($_POST['nascimento'] ?? '');

    if ($nome === '') {
        $aviso = 'Informe o nome do estagiário.';
    } elseif (strlen($cpf) !== 11 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nascimento)) {
        $aviso = 'Informe um CPF com 11 dígitos e uma data de nascimento válida.';
    } else {
        $existe = db()->prepare('SELECT 1 FROM usuarios WHERE cpf = ?');
        $existe->execute([$cpf]);
        if ($existe->fetch()) {
            $aviso = 'Já existe um estagiário cadastrado com esse CPF.';
        } else {
            $stmt = db()->prepare('INSERT INTO usuarios (nome, cpf, data_nascimento) VALUES (?, ?, ?)');
            $stmt->execute([$nome, $cpf, $nascimento]);
            $sucesso = 'Estagiário cadastrado. Ele entra com o CPF e a data de nascimento.';
        }
    }
}

$mes = $_POST['mes'] ?? $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

function hhmm(float $h): string
{
    $min = (int) round($h * 60);
    return sprintf('%dh %02dmin', intdiv($min, 60), $min % 60);
}

function cpfFormatado(?string $cpf): string
{
    $cpf = preg_replace('/\D/', '', (string) $cpf) ?? '';
    if (strlen($cpf) !== 11) {
        return '—';
    }
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// Todos os usuários com total de horas no mês selecionado
$stmt = db()->prepare(
    'SELECT u.id, u.nome, u.email, u.cpf, u.data_nascimento,
            COALESCE(SUM(r.horas), 0) AS total_horas,
            COUNT(r.id) AS dias_trabalhados
     FROM usuarios u
     LEFT JOIN registros r ON r.usuario_id = u.id AND substr(r.dia,1,7) = ?
     GROUP BY u.id, u.nome, u.email, u.cpf, u.data_nascimento
     ORDER BY u.nome'
);
$stmt->execute([$mes]);
$usuarios = $stmt->fetchAll();

// Registros detalhados do mês para exibição/expansão
$stmtReg = db()->prepare(
    'SELECT r.id, r.usuario_id, r.dia, r.horas, u.nome
     FROM registros r
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE substr(r.dia,1,7) = ?
     ORDER BY r.dia, u.nome'
);
$stmtReg->execute([$mes]);
$registros = $stmtReg->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administração — Controle de Horas</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <div class="linha">
    <h1>Administração</h1>
  </div>
  <p>
    Olá, <strong><?= htmlspecialchars((string) $usuario['nome']) ?></strong> —
    <a href="promover-admin.php">promover administrador</a> —
    <a href="index.php">meus registros</a> —
    <a href="logout.php">sair</a>
  </p>

  <form method="get" class="card linha">
    <label>Mês
      <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>">
    </label>
    <button type="submit">Ver mês</button>
  </form>

  <?php if ($aviso): ?><p class="erro"><?= htmlspecialchars($aviso) ?></p><?php endif; ?>
  <?php if ($sucesso): ?><p class="sucesso"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

  <h2>Cadastrar estagiário</h2>
  <form method="post" class="card linha">
    <input type="hidden" name="acao" value="cadastrar_aluno">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <label>Nome
      <input type="text" name="nome" placeholder="nome do estagiário" required>
    </label>
    <label>CPF
      <input type="text" name="cpf" inputmode="numeric" placeholder="somente números" required>
    </label>
    <label>Data de nascimento
      <input type="date" name="nascimento" required>
    </label>
    <button type="submit">Cadastrar estagiário</button>
  </form>

  <h2>Usuários e horas do mês</h2>
  <table class="card">
    <thead>
      <tr>
        <th>Nome / CPF</th>
        <th>Tipo</th>
        <th>Dias trabalhados</th>
        <th>Total do mês</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$usuarios): ?>
      <tr><td colspan="5" class="vazio">Nenhum usuário cadastrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($usuarios as $u): $ehProfessor = hasRole((int) $u['id'], 'admin');
      $nomeExibido = (string) $u['nome'];
      // Estagiários antigos foram salvos com o CPF no lugar do nome
      if (!$ehProfessor && preg_match('/^\d{11}$/', $nomeExibido)) {
        $nomeExibido = cpfFormatado($nomeExibido) . ' (sem nome)';
      }
    ?>
      <tr>
        <td><?= htmlspecialchars($nomeExibido) ?></td>
        <td><?= $ehProfessor ? 'Professor orientador' : 'Estagiário' ?></td>
        <td><?= (int) $u['dias_trabalhados'] ?></td>
        <td><?= hhmm((float) $u['total_horas']) ?></td>
        <td>
          <?php if ((int) $u['id'] !== (int) $usuario['id']): ?>
          <form method="post" onsubmit="return confirm('Excluir este usuário e todos os registros dele?')">
            <input type="hidden" name="acao" value="excluir_usuario">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
            <button class="link" type="submit">excluir</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Registros detalhados do mês</h2>
  <table class="card">
    <thead>
      <tr>
        <th>Dia</th>
        <th>Usuário</th>
        <th>Horas</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$registros): ?>
      <tr><td colspan="3" class="vazio">Nenhum registro neste mês.</td></tr>
    <?php endif; ?>
    <?php foreach ($registros as $r): ?>
      <tr>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['dia']))) ?></td>
        <td><?= htmlspecialchars((string) $r['nome']) ?></td>
        <td><?= hhmm((float) $r['horas']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
