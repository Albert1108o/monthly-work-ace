<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = getenv('DB_DIR') ?: __DIR__ . '/../data';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $dir . '/horas.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha_hash TEXT NOT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS registros (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            dia TEXT NOT NULL,
            horas REAL NOT NULL,
            UNIQUE (usuario_id, dia)
        )');

        // Migração: vincula registros a usuários (um dia por usuário)
        $cols = $pdo->query('PRAGMA table_info(registros)')->fetchAll();
        $temUsuario = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'usuario_id') {
                $temUsuario = true;
            }
        }
        if (!$temUsuario) {
            $pdo->exec('CREATE TABLE registros_novo (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                dia TEXT NOT NULL,
                horas REAL NOT NULL,
                UNIQUE (usuario_id, dia)
            )');
            $pdo->exec('INSERT INTO registros_novo (usuario_id, dia, horas)
                        SELECT 0, dia, horas FROM registros');
            $pdo->exec('DROP TABLE registros');
            $pdo->exec('ALTER TABLE registros_novo RENAME TO registros');
        }

        // Tabela de papéis separada da tabela de usuários
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            role TEXT NOT NULL,
            UNIQUE (user_id, role)
        )');
    }
    return $pdo;
}

function hasRole(int $userId, string $role): bool
{
    $stmt = db()->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role = ?');
    $stmt->execute([$userId, $role]);
    return (bool) $stmt->fetch();
}

function addRole(int $userId, string $role): void
{
    $stmt = db()->prepare('INSERT OR IGNORE INTO user_roles (user_id, role) VALUES (?, ?)');
    $stmt->execute([$userId, $role]);
}
