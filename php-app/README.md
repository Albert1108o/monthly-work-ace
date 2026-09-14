# Controle de Horas Trabalhadas (PHP + SQLite)

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
- Os dados ficam em um banco SQLite persistido no volume `horas_data`.
