<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function usuarioLogado(): ?array
{
    iniciarSessao();
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, nome, email FROM usuarios WHERE id = ?');
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function exigirLogin(): array
{
    $u = usuarioLogado();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function usuarioAdmin(): bool
{
    $u = usuarioLogado();
    if (!$u) {
        return false;
    }
    return hasRole((int) $u['id'], 'admin');
}

function exigirAdmin(): array
{
    $u = exigirLogin();
    if (!hasRole((int) $u['id'], 'admin')) {
        header('Location: index.php');
        exit;
    }
    return $u;
}
