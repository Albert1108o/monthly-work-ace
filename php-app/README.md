# Controle de Horas Trabalhadas (PHP + SQLite)

## Como rodar

```bash
cd php-app
docker compose up --build
```

Acesse http://localhost:8000

- Registre um dia e as horas trabalhadas (um registro por dia; salvar de novo atualiza).
- Escolha o mês para ver o total de horas, dias trabalhados e média diária.
- Os dados ficam em um banco SQLite persistido no volume `horas_data`.
