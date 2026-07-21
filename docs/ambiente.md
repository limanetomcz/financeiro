# Stack e ambiente local

## Stack

| Camada | Tecnologia |
|--------|------------|
| App | Laravel 12 (PHP 8.2+) |
| Banco | MySQL 8 |
| Cache/fila (local) | Redis |
| Containers | Docker Compose |
| Auth operacional | JWT Sigoweb (ver `integracao-sigoweb.md`) |

## Serviços Docker

| Serviço | Porta host | Uso |
|---------|------------|-----|
| `app` | 8085 → 80 | API / app Financeiro |
| `queue` | — | Worker Redis (`default,cobranca,bancario`) |
| `scheduler` | — | `schedule:work` |
| `mysql` | 3307 → 3306 | MySQL do domínio |
| `redis` | 6380 → 6379 | Cache / fila / session |

Portas escolhidas para não colidir com Apache/MySQL locais do Sigo.  
Detalhes de filas: [filas-redis.md](filas-redis.md).

## Setup

Passo a passo completo (clone, `.env`, migrate, JWT, testes): **[como-usar.md](como-usar.md)**.

## Comandos úteis

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
docker compose logs -f app
docker compose down
```

## Oracle

O Financeiro **não** conecta no Oracle como banco principal.

Quando precisar do código-fonte das procedures da Seridó (geração, baixa, remessa, inadimplência), solicitar ao time — mapear comportamento e reimplementar no MySQL/domínio novo.
