# Controle de Horas Trabalhadas (PHP + PostgreSQL)

## Como rodar

```bash
cd php-app
docker compose up --build
```

Acesse http://localhost:8000

- Somente o **professor orientador** cria a própria conta em `cadastro.php` (nome, e-mail e senha).
- Login em `login.php`, com duas abas:
  - **Estagiário**: CPF e data de nascimento.
  - **Professor orientador**: e-mail e senha (com opção "ver senha").
- O professor cadastra os estagiários na própria página (`admin.php`), informando CPF e data de nascimento.
- Na página principal, cada pessoa registra o dia, horário de entrada e saída (um registro por dia; salvar de novo atualiza).
- Escolha o mês para ver o total de horas, dias trabalhados e média diária.
- O card "Progresso do estágio" mostra o total acumulado em relação à meta de 300h.
- Em `admin.php` o professor vê todos os usuários, as horas do mês e pode excluir usuários (menos a própria conta).
- Professores podem promover outros usuários em `promover-admin.php`.

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
