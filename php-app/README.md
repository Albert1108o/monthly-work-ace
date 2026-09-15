# Controle de Horas Trabalhadas (PHP + PostgreSQL)

## Como rodar

```bash
cd php-app
docker compose up --build
```

Acesse http://localhost:8000

- Crie uma conta em `cadastro.php`.
- Faça login em `login.php`.
- Na página principal, registre o dia, horário de entrada e saída (um registro por dia; salvar de novo atualiza).
- Escolha o mês para ver o total de horas, dias trabalhados e média diária.
- O primeiro usuário cadastrado vira administrador automaticamente.
- Administradores acessam `admin.php` para ver todos os usuários e as horas trabalhadas no mês.
- Administradores podem promover outros usuários a administrador em `promover-admin.php`.

## Usar as mesmas contas em várias máquinas

Por padrão o compose sobe um banco PostgreSQL local (serviço `db`), que continua
sendo separado em cada computador.

Para que o login funcione em qualquer máquina, aponte todas elas para o **mesmo**
banco PostgreSQL hospedado na internet (Neon, Supabase, Railway, um VPS, etc.):

1. Copie `.env.example` para `.env`.
2. Preencha `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` com os dados do
   servidor.
3. Rode `docker compose up --build app` (sem subir o serviço `db` local).

As tabelas são criadas automaticamente na primeira execução.
