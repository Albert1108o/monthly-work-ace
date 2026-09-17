<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'horas';
        $user = getenv('DB_USER') ?: 'horas';
        $pass = getenv('DB_PASSWORD') ?: 'horas';

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        // O banco pode demorar alguns segundos para aceitar conexões na primeira subida
        $ultimoErro = null;
        for ($tentativa = 0; $tentativa < 15; $tentativa++) {
            try {
                $pdo = new PDO($dsn, $user, $pass);
                break;
            } catch (PDOException $e) {
                $ultimoErro = $e;
                sleep(2);
            }
        }
        if ($pdo === null) {
            throw new RuntimeException('Não foi possível conectar ao banco de dados: ' . ($ultimoErro ? $ultimoErro->getMessage() : ''));
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE IF NOT EXISTS usuarios (
            id SERIAL PRIMARY KEY,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha_hash TEXT NOT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS registros (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            dia TEXT NOT NULL,
            horas DOUBLE PRECISION NOT NULL,
            UNIQUE (usuario_id, dia)
        )');

        // Tabela de papéis separada da tabela de usuários
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_roles (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
            role TEXT NOT NULL,
            UNIQUE (user_id, role)
        )');

        // Alunos entram com CPF + data de nascimento (sem e-mail/senha)
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS cpf TEXT');
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS data_nascimento TEXT');
        $pdo->exec('ALTER TABLE usuarios ALTER COLUMN email DROP NOT NULL');
        $pdo->exec('ALTER TABLE usuarios ALTER COLUMN senha_hash DROP NOT NULL');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS usuarios_cpf_idx ON usuarios (cpf)');
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
    $stmt = db()->prepare('INSERT INTO user_roles (user_id, role) VALUES (?, ?) ON CONFLICT (user_id, role) DO NOTHING');
    $stmt->execute([$userId, $role]);
}
