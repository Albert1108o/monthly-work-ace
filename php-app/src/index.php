<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$usuario = exigirLogin();
$usuarioId = (int) $usuario['id'];

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'excluir') {
        $stmt = db()->prepare('DELETE FROM registros WHERE id = ? AND usuario_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $usuarioId]);
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

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || $total === null || $total <= 0 || $total > 24) {
            $erro = 'Informe uma data válida e horários de entrada e saída corretos.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO registros (usuario_id, dia, horas, entrada, saida) VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT (usuario_id, dia) DO UPDATE SET horas = EXCLUDED.horas, entrada = EXCLUDED.entrada, saida = EXCLUDED.saida'
            );
            $stmt->execute([$usuarioId, $dia, $total, $entrada, $saida]);
        }
    }
    if ($erro === null) {
        $mesRedir = $_POST['mes'] ?? 'all';
        header('Location: index.php?mes=' . urlencode((string) $mesRedir));
        exit;
    }
}

// "all" mostra todos os meses; caso contrário AAAA-MM
$mes = (string) ($_GET['mes'] ?? 'all');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = 'all';
}

if ($mes === 'all') {
    $stmt = db()->prepare('SELECT * FROM registros WHERE usuario_id = ? ORDER BY dia DESC');
    $stmt->execute([$usuarioId]);
} else {
    $stmt = db()->prepare('SELECT * FROM registros WHERE usuario_id = ? AND substr(dia,1,7) = ? ORDER BY dia DESC');
    $stmt->execute([$usuarioId, $mes]);
}
$registros = $stmt->fetchAll();

$stmtGeral = db()->prepare('SELECT COALESCE(SUM(horas), 0) FROM registros WHERE usuario_id = ?');
$stmtGeral->execute([$usuarioId]);
$totalGeral = (float) $stmtGeral->fetchColumn();

$metaHoras = 300.0;
$restantes = max(0.0, $metaHoras - $totalGeral);
$percentual = $metaHoras > 0 ? min(100.0, $totalGeral / $metaHoras * 100) : 0.0;

$totalHoras = 0.0;
foreach ($registros as $r) {
    $totalHoras += (float) $r['horas'];
}
$diasTrabalhados = count($registros);

function hhmm(float $h): string
{
    $min = (int) round($h * 60);
    return sprintf('%dh %02dmin', intdiv($min, 60), $min % 60);
}

function diaDaSemana(string $data): string
{
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    return $dias[(int) date('w', strtotime($data))];
}

$primeiroNome = trim(explode(' ', (string) $usuario['nome'])[0]);
$inicial = mb_strtoupper(mb_substr($primeiroNome !== '' ? $primeiroNome : '?', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estágio - Frequência</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <style>

/* =========================================================
   RESET
   Define configurações básicas para todos os elementos
   da página.
========================================================= */

* {
    /* Remove a margem padrão dos elementos */
    margin: 0;

    /* Remove o espaçamento interno padrão */
    padding: 0;

    /* Faz largura e altura incluírem padding e borda */
    box-sizing: border-box;

    /* Define a fonte padrão de toda a página */
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    /* Define a cor de fundo geral da página */
    background: #f5f3fc;

    /* Define a cor padrão dos textos */
    color: #116e33;
}

/* Faz botões e inputs utilizarem a mesma fonte
   definida anteriormente */
button,
input {
    font-family: inherit;
}


/* =========================================================
   LAYOUT PRINCIPAL
========================================================= */

.container {
    /* Coloca os elementos filhos lado a lado */
    display: flex;

    /* Faz o container ocupar pelo menos toda a altura da tela */
    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
   Barra lateral de navegação.
========================================================= */

.sidebar {
    /* Define a largura da barra lateral */
    width: 250px;

    /* Garante que a sidebar ocupe toda a altura da tela */
    min-height: 100vh;

    /* Cria um degradê vertical de verde */
    background: linear-gradient(180deg, #065706, #062915);

    /* Define a cor dos textos dentro da sidebar */
    color: white;

    /* Espaçamento interno:
       25px em cima/baixo e 15px nas laterais */
    padding: 25px 15px;

    /* Mantém a sidebar fixa na tela */
    position: fixed;

    /* Encosta a sidebar no lado esquerdo */
    left: 0;

    /* Encosta a sidebar no topo */
    top: 0;

    /* Faz a sidebar ocupar até o final da tela */
    bottom: 0;
}


/* =========================================================
   LOGO
========================================================= */

.logo {
    /* Centraliza o conteúdo da logo */
    text-align: center;

    /* Cria espaço abaixo da logo */
    margin-bottom: 40px;
}

.logo-icon {
    /* Define o tamanho do ícone */
    font-size: 42px;
}

.logo h1 {
    /* Define o tamanho do título */
    font-size: 27px;

    /* Aumenta o espaçamento entre as letras */
    letter-spacing: 1px;
}

.logo p {
    /* Define o tamanho do subtítulo */
    font-size: 11px;

    /* Deixa o texto um pouco transparente */
    opacity: 0.8;

    /* Aumenta o espaçamento entre as letras */
    letter-spacing: 2px;
}


/* =========================================================
   MENU
========================================================= */

.menu {
    /* Utiliza Flexbox */
    display: flex;

    /* Organiza os itens verticalmente */
    flex-direction: column;

    /* Define 8px de espaço entre os itens */
    gap: 8px;
}

.menu-item {
    /* Espaçamento interno do item */
    padding: 14px 15px;

    /* Arredonda os cantos */
    border-radius: 10px;

    /* Define a cor do texto */
    color: #ddd9f5;

    /* Remove o sublinhado padrão dos links */
    text-decoration: none;

    /* Usa Flexbox para alinhar ícone e texto */
    display: flex;

    /* Centraliza verticalmente */
    align-items: center;

    /* Espaço entre o ícone e o texto */
    gap: 13px;

    /* Cria uma transição suave nas mudanças */
    transition: 0.2s;
}

/* Aplica estilo quando:
   - o mouse passa sobre o item (:hover)
   - o item está ativo (.active) */
.menu-item:hover,
.menu-item.active {
    /* Muda a cor do fundo */
    background: #32693e;

    /* Deixa o texto branco */
    color: white;
}

.menu-icon {
    /* Define a largura reservada para o ícone */
    width: 23px;

    /* Centraliza o ícone */
    text-align: center;

    /* Define o tamanho do ícone */
    font-size: 18px;
}


/* =========================================================
   BOTÃO / LINK DE LOGOUT
========================================================= */

.logout {
    /* Posiciona o logout de forma absoluta
       dentro da sidebar */
    position: absolute;

    /* Mantém 25px de distância da parte inferior */
    bottom: 25px;

    /* Distância da esquerda */
    left: 15px;

    /* Distância da direita */
    right: 15px;
}


/* =========================================================
   CONTEÚDO PRINCIPAL
========================================================= */

.main {
    /* Cria espaço para a sidebar de 250px */
    margin-left: 250px;

    /* Ocupa o restante da largura da tela */
    width: calc(100% - 250px);

    /* Espaçamento interno */
    padding: 30px 40px;
}


/* =========================================================
   TOPO
========================================================= */

.top {
    /* Ativa Flexbox */
    display: flex;

    /* Coloca os elementos nas extremidades */
    justify-content: space-between;

    /* Alinha verticalmente */
    align-items: center;

    /* Espaço abaixo do cabeçalho */
    margin-bottom: 30px;
}

.welcome h2 {
    /* Tamanho do título de boas-vindas */
    font-size: 27px;

    /* Pequeno espaço abaixo do título */
    margin-bottom: 5px;
}

.welcome p {
    /* Define a cor do texto secundário */
    color: #0a2c06;
}


/* =========================================================
   ÁREA DO USUÁRIO
========================================================= */

.user {
    /* Coloca os elementos lado a lado */
    display: flex;

    /* Centraliza verticalmente */
    align-items: center;

    /* Espaçamento entre os elementos */
    gap: 12px;
}

.notification {
    /* Permite posicionar o indicador de notificação */
    position: relative;

    /* Tamanho do ícone */
    font-size: 23px;

    /* Mostra a mãozinha ao passar o mouse */
    cursor: pointer;

    /* Espaço à direita */
    margin-right: 10px;
}

.notification span {
    /* Posicionamento absoluto em relação à notificação */
    position: absolute;

    /* Largura do indicador */
    width: 8px;

    /* Altura do indicador */
    height: 8px;

    /* Cor rosa/vermelha do indicador */
    background: #e95c9b;

    /* Deixa o indicador circular */
    border-radius: 50%;

    /* Posiciona no topo */
    top: 1px;

    /* Posiciona à direita */
    right: 0;
}


/* =========================================================
   AVATAR
========================================================= */

.avatar {
    /* Largura do avatar */
    width: 42px;

    /* Altura do avatar */
    height: 42px;

    /* Transforma o quadrado em círculo */
    border-radius: 50%;

    /* Cor de fundo */
    background: #03290c;

    /* Cor das letras */
    color: white;

    /* Usa Flexbox */
    display: flex;

    /* Centraliza horizontalmente */
    justify-content: center;

    /* Centraliza verticalmente */
    align-items: center;

    /* Deixa o texto em negrito */
    font-weight: bold;
}


/* =========================================================
   CARDS
========================================================= */

.cards {
    /* Utiliza CSS Grid */
    display: grid;

    /* Cria 4 colunas de tamanhos iguais */
    grid-template-columns: repeat(4, 1fr);

    /* Espaçamento entre os cards */
    gap: 18px;

    /* Espaço abaixo dos cards */
    margin-bottom: 25px;
}

.card {
    /* Fundo branco */
    background: white;

    /* Arredonda os cantos */
    border-radius: 15px;

    /* Espaçamento interno */
    padding: 22px;

    /* Borda fina */
    border: 1px solid #e8e3f8;

    /* Cria uma sombra suave */
    box-shadow: 0 5px 18px rgba(49, 36, 105, 0.05);
}

.card-title {
    /* Cor do título */
    color: #000000;

    /* Tamanho do texto */
    font-size: 14px;

    /* Espaço abaixo */
    margin-bottom: 10px;
}

.card-value {
    /* Tamanho do valor principal */
    font-size: 25px;

    /* Deixa o texto mais grosso */
    font-weight: 700;

    /* Cor do valor */
    color: #195a27;
}

.card-description {
    /* Tamanho da descrição */
    font-size: 12px;

    /* Cor da descrição */
    color: #17462ee5;

    /* Espaço acima */
    margin-top: 5px;
}


/* =========================================================
   CARD DE PROGRESSO
========================================================= */

.progress-card {
    /* Permite posicionar elementos internos
       de maneira absoluta, se necessário */
    position: relative;
}

.progress-info {
    /* Coloca número e texto lado a lado */
    display: flex;

    /* Um elemento fica à esquerda e outro à direita */
    justify-content: space-between;

    /* Alinha pela parte inferior */
    align-items: end;

    /* Espaço abaixo */
    margin-bottom: 12px;
}

.progress-number {
    /* Tamanho do número da progressão */
    font-size: 27px;

    /* Deixa o número em negrito */
    font-weight: 700;
}

.remaining {
    /* Cor do texto restante */
    color: #0d270a;

    /* Tamanho */
    font-size: 13px;
}


/* =========================================================
   BARRA DE PROGRESSO
========================================================= */

.progress-bar {
    /* Altura da barra */
    height: 10px;

    /* Cor do fundo da barra */
    background: #e9e5f7;

    /* Arredonda a barra */
    border-radius: 20px;

    /* Impede que o preenchimento ultrapasse
       os limites arredondados */
    overflow: hidden;
}

.progress-fill {
    /* Ocupa toda a altura da barra */
    height: 100%;

    /* Começa com 0% de preenchimento.
       Provavelmente será alterado pelo JavaScript. */
    width: 0%;

    /* Cria um degradê verde */
    background: linear-gradient(90deg, #014422, #027c02);

    /* Arredonda o preenchimento */
    border-radius: 20px;

    /* Faz a mudança de largura ser animada */
    transition: 0.5s;
}

.progress-percent {
    /* Espaço acima do percentual */
    margin-top: 7px;

    /* Tamanho */
    font-size: 12px;

    /* Cor */
    color: #000000;
}


/* =========================================================
   SEÇÃO DE FREQUÊNCIA
========================================================= */

.frequency-section {
    /* Fundo branco */
    background: white;

    /* Cantos arredondados */
    border-radius: 15px;

    /* Borda */
    border: 1px solid #e8e3f8;

    /* Sombra */
    box-shadow: 0 5px 18px rgba(49, 36, 105, 0.05);

    /* Espaçamento interno */
    padding: 22px;
}

.section-header {
    /* Ativa Flexbox */
    display: flex;

    /* Coloca os elementos nas extremidades */
    justify-content: space-between;

    /* Centraliza verticalmente */
    align-items: center;

    /* Espaço abaixo */
    margin-bottom: 20px;
}

.section-title {
    /* Coloca ícone e título lado a lado */
    display: flex;

    /* Centraliza verticalmente */
    align-items: center;

    /* Espaço entre eles */
    gap: 10px;
}

.section-title h2 {
    /* Tamanho do título */
    font-size: 22px;
}


/* =========================================================
   SELETOR DE MÊS
========================================================= */

.month-select {
    /* Espaçamento interno */
    padding: 9px 14px;

    /* Borda */
    border: 1px solid #dcd6f0;

    /* Cantos arredondados */
    border-radius: 8px;

    /* Cor do texto */
    color: #09200f;

    /* Fundo branco */
    background: white;

    /* Remove o contorno padrão */
    outline: none;
}


/* =========================================================
   FORMULÁRIO
========================================================= */

.form-box {
    /* Fundo levemente acinzentado */
    background: #f8f7fd;

    /* Borda */
    border: 1px solid #e7e2f5;

    /* Arredonda os cantos */
    border-radius: 12px;

    /* Espaçamento interno */
    padding: 18px;

    /* Espaço abaixo */
    margin-bottom: 22px;
}

.form-box h3 {
    /* Espaço abaixo do título */
    margin-bottom: 15px;

    /* Tamanho do título */
    font-size: 16px;
}


/* =========================================================
   GRID DO FORMULÁRIO
========================================================= */

.form-grid {
    /* Usa CSS Grid */
    display: grid;

    /* Cria quatro colunas.
       A primeira é um pouco maior. */
    grid-template-columns: 1.3fr 1fr 1fr auto;

    /* Espaçamento entre campos */
    gap: 12px;

    /* Alinha os elementos pela parte inferior */
    align-items: end;
}

.input-group {
    /* Usa Flexbox */
    display: flex;

    /* Coloca label e input verticalmente */
    flex-direction: column;

    /* Espaço entre label e input */
    gap: 6px;
}

.input-group label {
    /* Tamanho da label */
    font-size: 12px;

    /* Deixa a label em negrito */
    font-weight: 600;

    /* Cor */
    color: #12331d;
}

.input-group input {
    /* Faz o input ocupar toda a largura disponível */
    width: 100%;

    /* Altura */
    height: 42px;

    /* Espaçamento interno horizontal */
    padding: 0 12px;

    /* Borda */
    border: 1px solid #dcd6ee;

    /* Cantos arredondados */
    border-radius: 8px;

    /* Remove o contorno padrão */
    outline: none;

    /* Fundo */
    background: white;

    /* Cor do texto digitado */
    color: #183d24;
}


/* =========================================================
   INPUT QUANDO ESTÁ SELECIONADO
========================================================= */

.input-group input:focus {
    /* Muda a cor da borda */
    border-color: #163617;

    /* Cria um brilho/contorno ao redor */
    box-shadow: 0 0 0 3px rgba(102, 83, 183, 0.1);
}


/* =========================================================
   BOTÃO ADICIONAR
========================================================= */

.add-button {
    /* Altura */
    height: 42px;

    /* Espaçamento horizontal */
    padding: 0 18px;

    /* Remove a borda padrão */
    border: none;

    /* Arredonda os cantos */
    border-radius: 8px;

    /* Cor do fundo */
    background: #0c3f15;

    /* Cor do texto */
    color: white;

    /* Texto em negrito */
    font-weight: 600;

    /* Mostra o cursor de clique */
    cursor: pointer;

    /* Anima alterações */
    transition: 0.2s;
}

.add-button:hover {
    /* Altera a cor quando o mouse passa */
    background: #133521;

    /* Move o botão 1px para cima */
    transform: translateY(-1px);
}


/* =========================================================
   TABELA
========================================================= */

.table-container {
    /* Ocupa toda a largura */
    width: 100%;

    /* Permite rolagem horizontal em telas pequenas */
    overflow-x: auto;
}

table {
    /* Ocupa toda a largura disponível */
    width: 100%;

    /* Remove espaços entre as células */
    border-collapse: collapse;
}

thead {
    /* Fundo do cabeçalho da tabela */
    background: #eeebfa;
}

th {
    /* Alinha o texto à esquerda */
    text-align: left;

    /* Espaçamento interno */
    padding: 13px;

    /* Tamanho da fonte */
    font-size: 12px;

    /* Cor */
    color: #0d3116;
}

td {
    /* Espaçamento interno */
    padding: 13px;

    /* Linha separadora */
    border-bottom: 1px solid #eeeaf6;

    /* Tamanho do texto */
    font-size: 13px;

    /* Cor */
    color: #14331c;
}


/* Muda o fundo da linha quando o mouse passa */
tbody tr:hover {
    background: #faf9fe;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    /* Permite definir padding e dimensões */
    display: inline-block;

    /* Espaçamento interno */
    padding: 5px 12px;

    /* Deixa o status arredondado */
    border-radius: 20px;

    /* Tamanho da fonte */
    font-size: 11px;

    /* Texto em negrito */
    font-weight: 600;
}

.status.registered {
    /* Fundo verde claro */
    background: #def5eb;

    /* Texto verde */
    color: #25845c;
}


/* =========================================================
   BOTÃO EXCLUIR
========================================================= */

.delete-button {
    /* Remove a borda */
    border: none;

    /* Fundo transparente */
    background: transparent;

    /* Mostra o cursor de clique */
    cursor: pointer;

    /* Tamanho do ícone */
    font-size: 17px;

    /* Deixa o botão inicialmente um pouco transparente */
    opacity: 0.7;
}

.delete-button:hover {
    /* Deixa o botão totalmente visível */
    opacity: 1;
}


/* Mensagem exibida quando a tabela está vazia */
.empty {
    /* Centraliza o texto */
    text-align: center;

    /* Espaçamento interno */
    padding: 35px;

    /* Cor */
    color: #84a78e;
}


/* =========================================================
   RESUMO
========================================================= */

.bottom-grid {
    /* Utiliza CSS Grid */
    display: grid;

    /* Primeira coluna é maior que a segunda */
    grid-template-columns: 1.5fr 1fr;

    /* Espaçamento entre as colunas */
    gap: 18px;

    /* Espaço acima */
    margin-top: 20px;
}

.summary {
    /* Fundo branco */
    background: white;

    /* Borda */
    border: 1px solid #e8e3f8;

    /* Cantos arredondados */
    border-radius: 15px;

    /* Espaçamento interno */
    padding: 22px;
}

.summary h2 {
    /* Tamanho do título */
    font-size: 18px;

    /* Espaço abaixo */
    margin-bottom: 22px;
}


/* =========================================================
   ITENS DO RESUMO
========================================================= */

.summary-items {
    /* Utiliza Grid */
    display: grid;

    /* Cria 3 colunas */
    grid-template-columns: repeat(3, 1fr);

    /* Espaçamento entre os itens */
    gap: 10px;
}

.summary-item {
    /* Centraliza o conteúdo */
    text-align: center;

    /* Espaçamento interno */
    padding: 10px;

    /* Linha divisória entre os itens */
    border-right: 1px solid #e6e1f1;
}

.summary-item:last-child {
    /* Remove a borda do último item */
    border: none;
}

.summary-item strong {
    /* Faz o elemento ocupar uma linha inteira */
    display: block;

    /* Tamanho do número */
    font-size: 25px;

    /* Cor */
    color: #1c4d13;
}

.summary-item span {
    /* Cor do texto secundário */
    color: #84a484;

    /* Tamanho */
    font-size: 12px;
}


/* =========================================================
   CARD DE COMPROMISSO
========================================================= */

.commitment {
    /* Fundo com degradê */
    background: linear-gradient(135deg, #eeeafd, #f8f7fd);

    /* Borda */
    border: 1px solid #e1dcf4;

    /* Cantos arredondados */
    border-radius: 15px;

    /* Espaçamento interno */
    padding: 22px;

    /* Usa Flexbox */
    display: flex;

    /* Coloca texto e ícone nas extremidades */
    justify-content: space-between;

    /* Centraliza verticalmente */
    align-items: center;
}

.commitment h3 {
    /* Espaço abaixo do título */
    margin-bottom: 10px;
}

.commitment p {
    /* Tamanho do texto */
    font-size: 13px;

    /* Cor */
    color: #22571b;

    /* Limita a largura do texto */
    max-width: 300px;

    /* Define o espaçamento entre linhas */
    line-height: 1.5;
}

.commitment-icon {
    /* Define um ícone grande */
    font-size: 55px;
}


/* =========================================================
   CALENDÁRIO PERSONALIZADO
========================================================= */

.date-wrapper {
    /* Cria um contexto para o calendário
       que será posicionado dentro dele */
    position: relative;
}

.date-input {
    /* Mostra que o campo pode ser clicado */
    cursor: pointer;
}

.calendar {
    /* Permite posicionamento em relação ao .date-wrapper */
    position: absolute;

    /* Garante que o calendário apareça acima de outros elementos */
    z-index: 50;

    /* Distância do topo */
    top: 72px;

    /* Alinha à esquerda */
    left: 0;

    /* Largura */
    width: 300px;

    /* Fundo branco */
    background: white;

    /* Cantos arredondados */
    border-radius: 14px;

    /* Borda */
    border: 1px solid #ddd7ef;

    /* Sombra */
    box-shadow: 0 15px 40px rgba(37, 26, 83, 0.18);

    /* Espaçamento interno */
    padding: 18px;

    /* Inicialmente o calendário fica escondido */
    display: none;
}


/* Quando o calendário recebe a classe "active",
   ele passa a ser exibido */
.calendar.active {
    display: block;

    /* Executa a animação criada abaixo */
    animation: showCalendar 0.15s ease;
}


/* =========================================================
   ANIMAÇÃO DO CALENDÁRIO
========================================================= */

@keyframes showCalendar {

    /* Estado inicial */
    from {
        /* Começa transparente */
        opacity: 0;

        /* Começa 5px acima */
        transform: translateY(-5px);
    }

    /* Estado final */
    to {
        /* Fica totalmente visível */
        opacity: 1;

        /* Volta para a posição original */
        transform: translateY(0);
    }
}


/* =========================================================
   CABEÇALHO DO CALENDÁRIO
========================================================= */

.calendar-header {
    /* Usa Flexbox */
    display: flex;

    /* Elementos ficam nas extremidades */
    justify-content: space-between;

    /* Centralização vertical */
    align-items: center;

    /* Espaço abaixo */
    margin-bottom: 18px;
}

.calendar-header strong {
    /* Tamanho do mês/ano */
    font-size: 15px;

    /* Cor */
    color: #0f4912;
}


/* =========================================================
   SETAS DO CALENDÁRIO
========================================================= */

.calendar-arrow {
    /* Largura */
    width: 30px;

    /* Altura */
    height: 30px;

    /* Remove borda */
    border: none;

    /* Arredonda os cantos */
    border-radius: 7px;

    /* Fundo */
    background: #f0edfa;

    /* Cor */
    color: #155018;

    /* Mostra cursor de clique */
    cursor: pointer;
}


/* =========================================================
   DIAS DA SEMANA E DIAS DO MÊS
========================================================= */

.calendar-weekdays,
.calendar-days {
    /* Utiliza CSS Grid */
    display: grid;

    /* Divide em 7 colunas */
    grid-template-columns: repeat(7, 1fr);

    /* Centraliza os textos */
    text-align: center;

    /* Espaço entre os dias */
    gap: 4px;
}

.calendar-weekdays {
    /* Espaço abaixo dos nomes dos dias */
    margin-bottom: 8px;
}

.calendar-weekdays span {
    /* Tamanho dos nomes dos dias */
    font-size: 10px;

    /* Cor */
    color: #9993b2;

    /* Deixa em negrito */
    font-weight: 600;
}


/* =========================================================
   BOTÕES DOS DIAS
========================================================= */

.calendar-day {
    /* Altura de cada dia */
    height: 34px;

    /* Remove a borda */
    border: none;

    /* Fundo transparente */
    background: transparent;

    /* Cantos arredondados */
    border-radius: 8px;

    /* Mostra cursor de clique */
    cursor: pointer;

    /* Cor do número */
    color: #214721;

    /* Tamanho */
    font-size: 12px;
}

.calendar-day:hover {
    /* Fundo quando passa o mouse */
    background: #eeeafb;

    /* Cor do número */
    color: #000000;
}


/* Dia atual */
.calendar-day.today {
    /* Fundo diferenciado */
    background: #eee9fc;

    /* Cor */
    color: #000000;

    /* Negrito */
    font-weight: bold;
}


/* Dia selecionado */
.calendar-day.selected {
    /* Fundo verde */
    background: #1a693e;

    /* Texto branco */
    color: white;
}


/* Dias que não podem ser selecionados */
.calendar-day.disabled {
    /* Cor mais clara */
    color: #d2cedf;

    /* Cursor normal em vez de cursor de clique */
    cursor: default;
}


/* =========================================================
   DIA DA MEDIAÇÃO
========================================================= */

.calendar-day.mediation-day {
    /* Fundo verde */
    background: #46b93c;

    /* Cor do texto */
    color: #000000;

    /* Impede indicar que o elemento é clicável */
    cursor: not-allowed;

    /* Texto em negrito */
    font-weight: 700;

    /* Necessário para posicionar o tooltip */
    position: relative;
}


/* Mantém a mesma aparência quando o mouse passa */
.calendar-day.mediation-day:hover {
    background: #46b93c;
    color: #000000;
}


/* =========================================================
   TOOLTIP DO DIA DA MEDIAÇÃO
========================================================= */

.calendar-day.mediation-day::after {
    /* Texto que será exibido no tooltip */
    content: "📅 Dia da mediação";

    /* Permite posicionar o tooltip */
    position: absolute;

    /* Coloca o tooltip acima do dia */
    bottom: 42px;

    /* Começa no centro */
    left: 50%;

    /* Centraliza horizontalmente */
    transform: translateX(-50%);

    /* Fundo escuro */
    background: #2e4d12;

    /* Texto branco */
    color: white;

    /* Espaçamento interno */
    padding: 7px 10px;

    /* Arredonda os cantos */
    border-radius: 7px;

    /* Tamanho da fonte */
    font-size: 11px;

    /* Peso da fonte */
    font-weight: 500;

    /* Impede que o texto quebre em várias linhas */
    white-space: nowrap;

    /* Começa invisível */
    opacity: 0;

    /* Também fica escondido visualmente */
    visibility: hidden;

    /* Não interfere com o mouse */
    pointer-events: none;

    /* Cria uma transição suave */
    transition: 0.2s;

    /* Fica acima dos outros elementos */
    z-index: 100;
}


/* Ao passar o mouse sobre o dia da mediação,
   o tooltip aparece */
.calendar-day.mediation-day:hover::after {
    /* Torna o tooltip visível */
    opacity: 1;

    /* Mostra o elemento */
    visibility: visible;
}


/* =========================================================
   RESPONSIVIDADE
   Adapta o site para diferentes tamanhos de tela.
========================================================= */


/* Ajusta especificamente o valor
   dentro do card de mediação */
.mediation-card .card-value {
    font-size: 28px;
}


/* =========================================================
   TELAS ATÉ 1200px
========================================================= */

@media (max-width: 1200px) {

    .cards {
        /* Passa de 4 para 2 colunas */
        grid-template-columns: repeat(2, 1fr);
    }
}


/* =========================================================
   TELAS ATÉ 1000px
========================================================= */

@media (max-width: 1000px) {

    .sidebar {
        /* Diminui a largura da sidebar */
        width: 210px;
    }

    .main {
        /* Ajusta a margem de acordo com a nova sidebar */
        margin-left: 210px;

        /* Calcula o espaço restante */
        width: calc(100% - 210px);

        /* Diminui o padding */
        padding: 25px;
    }

    .cards {
        /* Os cards passam a ocupar uma coluna */
        grid-template-columns: 1fr;
    }

    .form-grid {
        /* O formulário passa a ter duas colunas */
        grid-template-columns: 1fr 1fr;
    }

    .add-button {
        /* O botão ocupa toda a largura */
        width: 100%;
    }

    .bottom-grid {
        /* Resumo e compromisso ficam um abaixo do outro */
        grid-template-columns: 1fr;
    }

}


/* =========================================================
   CELULARES / TELAS ATÉ 700px
========================================================= */

@media (max-width: 700px) {

    .sidebar {
        /* Sidebar fica estreita */
        width: 70px;

        /* Reduz o espaçamento interno */
        padding: 20px 8px;
    }

    /* Esconde o nome da logo,
       subtítulo e texto dos menus */
    .logo h1,
    .logo p,
    .menu-item span:not(.menu-icon) {
        display: none;
    }

    .logo-icon {
        /* Reduz o tamanho do ícone */
        font-size: 30px;
    }

    .menu-item {
        /* Centraliza apenas os ícones */
        justify-content: center;
    }

    .logout {
        /* Ajusta o logout para a sidebar menor */
        left: 8px;
        right: 8px;
    }

    .main {
        /* Ajusta o espaço reservado para a sidebar */
        margin-left: 70px;

        /* Calcula a largura restante */
        width: calc(100% - 70px);

        /* Reduz o espaçamento */
        padding: 20px 15px;
    }

    .welcome h2 {
        /* Diminui o título */
        font-size: 20px;
    }

    .welcome p {
        /* Diminui o texto */
        font-size: 12px;
    }

    .top {
        /* Alinha os elementos pelo início */
        align-items: flex-start;
    }

    .user {
        /* Esconde notificações e avatar
           em telas muito pequenas */
        display: none;
    }

    .form-grid {
        /* Formulário passa para uma única coluna */
        grid-template-columns: 1fr;
    }

    .summary-items {
        /* Resumo passa para uma coluna */
        grid-template-columns: 1fr;
    }

    .summary-item {
        /* Remove a borda lateral */
        border-right: none;

        /* Adiciona uma linha abaixo */
        border-bottom: 1px solid #e6e1f1;
    }

    .calendar {
        /* Reduz o tamanho do calendário */
        width: 280px;
    }
   
}

</style>

</head>

<body>

<div class="container">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="logo">
            <div class="logo-icon">🎓</div>
            <h1>PontoJa</h1>
            <p>ESTÁGIO</p>
            <small>Formação para o futuro</small>
        </div>

        <nav class="menu">
            <a href="index.php" class="menu-item active">
                <span class="menu-icon">⌂</span>
                <span>Início</span>
            </a>
<?php if (usuarioAdmin()): ?>
            <a href="admin.php" class="menu-item">
                <span class="menu-icon">▣</span>
                <span>Administração</span>
            </a>
<?php endif; ?>
        </nav>

        <div class="logout">
            <a href="logout.php" class="menu-item">
                <span class="menu-icon">⇥</span>
                <span>Sair</span>
            </a>
        </div>
    </aside>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="main">

        <!-- TOPO -->
        <header class="top">
            <div class="welcome">
                <h2>Olá, <?= htmlspecialchars((string) $usuario['nome']) ?>! 👋</h2>
                <p>Aqui você acompanha sua frequência e o progresso do seu estágio.</p>
            </div>
            <div class="user">
                <div class="avatar"><?= htmlspecialchars($inicial) ?></div>
                <span><?= htmlspecialchars((string) $usuario['nome']) ?></span>
            </div>
        </header>

        <!-- CARDS -->
        <section class="cards">
            <div class="card">
                <div class="card-title">📅 Período do estágio</div>
                <div class="card-value" style="font-size: 18px;">19/08/2026 — 01/12/2026</div>
                <div class="card-description">4 meses</div>
            </div>

            <div class="card">
                <div class="card-title">🕐 Carga horária total</div>
                <div class="card-value"><?= (int) $metaHoras ?> horas</div>
                <div class="card-description">Carga horária necessária</div>
            </div>

            <div class="card progress-card">
                <div class="progress-info">
                    <div>
                        <div class="card-title">Progresso do estágio</div>
                        <div class="progress-number">
                            <?= hhmm($totalGeral) ?>
                            <span style="color:#aaa; font-size: 16px;">/ <?= (int) $metaHoras ?>h</span>
                        </div>
                    </div>
                    <div class="remaining">
                        <?= hhmm($restantes) ?> restantes
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: <?= number_format($percentual, 2, '.', '') ?>%"></div>
                </div>
                <div class="progress-percent">
                    <?= number_format($percentual, 1, ',', '') ?>% concluído
                </div>
            </div>

            <div class="card mediation-card">
                <div class="card-title">📅 Dia da mediação</div>
                <div class="card-value" id="mediationDate">--/--/----</div>
                <p>Último dia útil do mês — não haverá estágio</p>
            </div>
        </section>

        <!-- FREQUÊNCIA -->
        <section class="frequency-section">
            <div class="section-header">
                <div class="section-title">
                    <span style="font-size:25px;">📅</span>
                    <h2>Minha Frequência</h2>
                </div>
                <form method="get" class="month-form">
                    <select class="month-select" id="monthFilter" name="mes" onchange="this.form.submit()">
                        <option value="all"<?= $mes === 'all' ? ' selected' : '' ?>>Todos os meses</option>
<?php
$nomesMeses = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'];
$anoAtual = date('Y');
foreach ($nomesMeses as $num => $nomeMes):
    $valor = $anoAtual . '-' . $num;
?>
                        <option value="<?= $valor ?>"<?= $mes === $valor ? ' selected' : '' ?>><?= $nomeMes ?>/<?= $anoAtual ?></option>
<?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- FORMULÁRIO -->
            <div class="form-box">

<?php if ($erro): ?>
                <p style="color:#c0392b; font-weight:600; margin-bottom:12px;"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

                <h3>Registrar dia de estágio</h3>

                <form method="post" class="form-grid">
                    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
                    <input type="hidden" name="dia" id="diaISO">

                    <!-- DATA -->
                    <div class="input-group date-wrapper">
                        <label>Dia do estágio</label>
                        <input type="text" id="dateInput" class="date-input" placeholder="Clique para selecionar" readonly>

                        <!-- CALENDÁRIO -->
                        <div class="calendar" id="calendar">
                            <div class="calendar-header">
                                <button type="button" class="calendar-arrow" id="prevMonth">‹</button>
                                <strong id="calendarMonth"></strong>
                                <button type="button" class="calendar-arrow" id="nextMonth">›</button>
                            </div>
                            <div class="calendar-weekdays">
                                <span>DOM</span><span>SEG</span><span>TER</span><span>QUA</span><span>QUI</span><span>SEX</span><span>SÁB</span>
                            </div>
                            <div class="calendar-days" id="calendarDays"></div>
                        </div>
                    </div>

                    <!-- ENTRADA -->
                    <div class="input-group">
                        <label>Horário de entrada</label>
                        <input type="time" id="entryTime" name="entrada" value="08:00" required>
                    </div>

                    <!-- SAÍDA -->
                    <div class="input-group">
                        <label>Horário de saída</label>
                        <input type="time" id="exitTime" name="saida" value="17:00" required>
                    </div>

                    <!-- BOTÃO -->
                    <button type="submit" class="add-button" id="addButton">Adicionar</button>
                </form>
            </div>

            <!-- TABELA -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia da semana</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Total de horas</th>
                            <th>Situação</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody id="frequencyTable">
<?php if (!$registros): ?>
                        <tr><td colspan="7" class="empty">Nenhum registro encontrado.</td></tr>
<?php endif; ?>
<?php foreach ($registros as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $r['dia']))) ?></td>
                            <td><?= htmlspecialchars(diaDaSemana((string) $r['dia'])) ?></td>
                            <td><?= htmlspecialchars((string) ($r['entrada'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['saida'] ?? '—')) ?></td>
                            <td><?= hhmm((float) $r['horas']) ?></td>
                            <td><span class="status registered">Registrado</span></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Excluir este dia?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
                                    <button class="delete-button" type="submit">🗑️</button>
                                </form>
                            </td>
                        </tr>
<?php endforeach; ?>
                </table>
            </div>
        </section>

        <!-- RESUMO -->
        <div class="bottom-grid">
            <div class="summary">
                <h2>✓ Resumo do Estágio</h2>
                <div class="summary-items">
                    <div class="summary-item">
                        <strong><?= hhmm($totalGeral) ?></strong>
                        <span>Horas já estagiadas</span>
                    </div>
                    <div class="summary-item">
                        <strong><?= hhmm($restantes) ?></strong>
                        <span>Horas restantes</span>
                    </div>
                    <div class="summary-item">
                        <strong><?= (int) $metaHoras ?>h</strong>
                        <span>Total do estágio</span>
                    </div>
                </div>
            </div>

            <div class="commitment">
                <div>
                    <h3>📋 Seu compromisso</h3>
                    <p>"Cada hora de hoje é um passo para o seu futuro."</p>
                </div>
                <div class="commitment-icon">💻</div>
            </div>
        </div>

    </main>
</div>

<script>
    const dateInput = document.getElementById("dateInput");
    const diaISO = document.getElementById("diaISO");
    const calendar = document.getElementById("calendar");
    const calendarDays = document.getElementById("calendarDays");
    const calendarMonth = document.getElementById("calendarMonth");
    const mediationDateEl = document.getElementById("mediationDate");

    let currentCalendarDate = new Date();
    let selectedDate = new Date();

    function getLastBusinessDay(year, month) {
        let date = new Date(year, month + 1, 0);
        while (date.getDay() === 0 || date.getDay() === 6) {
            date.setDate(date.getDate() - 1);
        }
        return date;
    }

    function updateMediationCard() {
        const d = getLastBusinessDay(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
        mediationDateEl.textContent = d.toLocaleDateString("pt-BR");
    }

    function isMediationDay(date) {
        const m = getLastBusinessDay(date.getFullYear(), date.getMonth());
        return date.getDate() === m.getDate() && date.getMonth() === m.getMonth() && date.getFullYear() === m.getFullYear();
    }

    function toISO(date) {
        const mm = String(date.getMonth() + 1).padStart(2, "0");
        const dd = String(date.getDate()).padStart(2, "0");
        return date.getFullYear() + "-" + mm + "-" + dd;
    }

    function applySelection() {
        if (!selectedDate) return;
        dateInput.value = selectedDate.toLocaleDateString("pt-BR");
        diaISO.value = toISO(selectedDate);
    }

    function renderCalendar() {
        calendarDays.innerHTML = "";
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();

        const monthName = currentCalendarDate.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
        calendarMonth.textContent = monthName.charAt(0).toUpperCase() + monthName.slice(1);
        updateMediationCard();

        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement("button");
            empty.type = "button";
            empty.classList.add("calendar-day", "disabled");
            empty.disabled = true;
            calendarDays.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayButton = document.createElement("button");
            dayButton.type = "button";
            dayButton.classList.add("calendar-day");
            dayButton.textContent = day;

            const currentDateObj = new Date(year, month, day);

            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayButton.classList.add("today");
            }

            if (isMediationDay(currentDateObj)) {
                dayButton.classList.add("mediation-day");
                dayButton.disabled = true;
            } else {
                dayButton.addEventListener("click", () => {
                    selectedDate = currentDateObj;
                    applySelection();
                    calendar.classList.remove("active");
                    renderCalendar();
                });
            }

            if (selectedDate && day === selectedDate.getDate() && month === selectedDate.getMonth() && year === selectedDate.getFullYear()) {
                dayButton.classList.add("selected");
            }

            calendarDays.appendChild(dayButton);
        }
    }

    dateInput.addEventListener("click", (e) => {
        e.stopPropagation();
        calendar.classList.toggle("active");
    });

    document.addEventListener("click", (e) => {
        if (!calendar.contains(e.target) && e.target !== dateInput) {
            calendar.classList.remove("active");
        }
    });

    document.getElementById("prevMonth").addEventListener("click", (e) => {
        e.stopPropagation();
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById("nextMonth").addEventListener("click", (e) => {
        e.stopPropagation();
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
        renderCalendar();
    });

    applySelection();
    renderCalendar();
</script>

</body>

</html>
