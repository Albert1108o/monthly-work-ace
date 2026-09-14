<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$usuario = exigirAdmin();

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

function hhmm(float $h): string
{
    $min = (int) round($h * 60);
    return sprintf('%dh %02dmin', intdiv($min, 60), $min % 60);
}

// Todos os usuários com total de horas no mês selecionado
$stmt = db()->prepare(
    'SELECT u.id, u.nome, u.email,
            COALESCE(SUM(r.horas), 0) AS total_horas,
            COUNT(r.id) AS dias_trabalhados
     FROM usuarios u
     LEFT JOIN registros r ON r.usuario_id = u.id AND substr(r.dia,1,7) = ?
     GROUP BY u.id, u.nome, u.email
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
  <p>Olá, <strong><?= htmlspecialchars((string) $usuario['nome']) ?></strong> — <a href="index.php">meus registros</a> — <a href="logout.php">sair</a></p>

  <form method="get" class="card linha">
    <label>Mês
      <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>">
    </label>
    <button type="submit">Ver mês</button>
  </form>

  <h2>Usuários e horas do mês</h2>
  <table class="card">
    <thead>
      <tr>
        <th>Nome</th>
        <th>E-mail</th>
        <th>Dias trabalhados</th>
        <th>Total do mês</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$usuarios): ?>
      <tr><td colspan="4" class="vazio">Nenhum usuário cadastrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><?= htmlspecialchars((string) $u['nome']) ?></td>
        <td><?= htmlspecialchars((string) $u['email']) ?></td>
        <td><?= (int) $u['dias_trabalhados'] ?></td>
        <td><?= hhmm((float) $u['total_horas']) ?></td>
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
