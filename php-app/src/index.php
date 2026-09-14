<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'excluir') {
        $stmt = db()->prepare('DELETE FROM registros WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
    } else {
        $dia = trim((string) ($_POST['dia'] ?? ''));
        $entrada = (string) ($_POST['entrada'] ?? '');
        $saida = (string) ($_POST['saida'] ?? '');

        $total = null;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $entrada, $me) && preg_match('/^(\d{1,2}):(\d{2})$/', $saida, $ms)) {
            $minEntrada = (int) $me[1] * 60 + (int) $me[2];
            $minSaida = (int) $ms[1] * 60 + (int) $ms[2];
            if ($minSaida < $minEntrada) {
                $minSaida += 24 * 60;
            }
            $total = ($minSaida - $minEntrada) / 60;
        }

        if ($dia === '' || $total === null || $total <= 0 || $total > 24) {
            $erro = 'Informe uma data válida e horários de entrada e saída corretos.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO registros (dia, horas) VALUES (:dia, :horas)
                 ON CONFLICT(dia) DO UPDATE SET horas = :horas'
            );
            $stmt->execute([':dia' => $dia, ':horas' => $total]);
        }
    }
    if ($erro === null) {
        $mesRedir = $_POST['mes'] ?? date('Y-m');
        header('Location: index.php?mes=' . urlencode($mesRedir));
        exit;
    }
}

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$stmt = db()->prepare('SELECT * FROM registros WHERE substr(dia,1,7) = ? ORDER BY dia');
$stmt->execute([$mes]);
$registros = $stmt->fetchAll();

$totalHoras = 0.0;
foreach ($registros as $r) {
    $totalHoras += (float) $r['horas'];
}
$diasTrabalhados = count($registros);
$media = $diasTrabalhados > 0 ? $totalHoras / $diasTrabalhados : 0.0;

function hhmm(float $h): string
{
    $min = (int) round($h * 60);
    return sprintf('%dh %02dmin', intdiv($min, 60), $min % 60);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Controle de Horas Trabalhadas</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
  <h1>Controle de Horas Trabalhadas</h1>

  <form method="get" class="card linha">
    <label>Mês
      <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>">
    </label>
    <button type="submit">Ver mês</button>
  </form>

  <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

  <form method="post" class="card linha">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <label>Dia trabalhado
      <input type="date" name="dia" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
    </label>
    <label>Entrada
      <input type="time" name="entrada" value="08:00" required>
    </label>
    <label>Saída
      <input type="time" name="saida" value="17:00" required>
    </label>
    <button type="submit">Salvar</button>
  </form>

  <section class="resumo">
    <div class="card"><span>Dias trabalhados</span><strong><?= $diasTrabalhados ?></strong></div>
    <div class="card"><span>Total do mês</span><strong><?= hhmm($totalHoras) ?></strong></div>
    <div class="card"><span>Média por dia</span><strong><?= hhmm($media) ?></strong></div>
  </section>

  <table class="card">
    <thead><tr><th>Dia</th><th>Horas</th><th></th></tr></thead>
    <tbody>
    <?php if (!$registros): ?>
      <tr><td colspan="3" class="vazio">Nenhum registro neste mês.</td></tr>
    <?php endif; ?>
    <?php foreach ($registros as $r): ?>
      <tr>
        <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['dia']))) ?></td>
        <td><?= hhmm((float) $r['horas']) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Excluir este dia?')">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
            <button class="link" type="submit">excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
