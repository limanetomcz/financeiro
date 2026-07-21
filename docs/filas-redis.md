# Filas e Redis (multi-tenant)

## Por que Redis

Com **vários tenants** (ex.: 7 Uniodontos) no mesmo app/MySQL:

| Uso | Por quê Redis |
|-----|----------------|
| **Filas** | Remessa, CNAB, PIX, abrir parcelas, e-mails — não travam a API |
| **Cache** | Consultas/config por cliente com chave isolada |
| **Session** | Se houver UI Blade; API JWT não depende disso |

Um Redis serve todos os tenants. Isolamento de negócio continua sendo `cliente_id` + `ClienteContext`.

## Arquitetura

```text
API (app) ──dispatch──► Redis queues
                              │
                    queue worker(s)
                              │
                    restaura ClienteContext
                              │
                         MySQL (dados)
```

Containers Compose:

| Serviço | Papel |
|---------|--------|
| `app` | HTTP API |
| `queue` | `queue:work redis` (filas `default,cobranca,bancario`) |
| `scheduler` | `schedule:work` (dispara jobs diários) |
| `redis` | Broker + cache (AOF persistente no volume) |
| `mysql` | Dados dos tenants |

## Filas lógicas

| Fila | Uso |
|------|-----|
| `default` | Orquestração (ex.: despachar jobs por cliente) |
| `cobranca` | Abrir parcelas, consolidar em lote, etc. |
| `bancario` | Remessa/CNAB/PIX (quando existirem) |

### Isolamento por cooperativa (opcional)

`FINANCEIRO_QUEUE_POR_CLIENTE=true` → filas `cobranca-cliente-112`, etc.

Deixe `false` no início (mais simples). Ative se um tenant saturar a fila dos outros.

## Jobs com tenant

Todo job de negócio deve estender `App\Jobs\TenantJob`:

- Serializa `clienteId`
- No worker: carrega Cliente, seta `ClienteContext`, executa, limpa

Exemplo já existente: `AbrirParcelasExigiveisJob`.

Cache com tenant:

```php
use App\Support\Cache\TenantCache;

TenantCache::remember('resumo-aberto', 60, fn () => ...);
```

## Comandos

```bash
# worker (já sobe no compose como serviço queue)
docker compose up -d queue scheduler

# abrir parcelas via fila (todos os clientes)
docker compose exec app php artisan parcelas:abrir-exigiveis --queue

# só Seridó, síncrono
docker compose exec app php artisan parcelas:abrir-exigiveis --cliente=112

# ver filas falhas
docker compose exec app php artisan queue:failed
```

Agenda: todo dia **01:15** enfileira abertura de parcelas para todos os clientes ativos (`routes/console.php`).

## Produção (7 tenants) — recomendações

1. **2+ workers** (`queue` replicas) se remessa/CNAB forem pesados  
2. Manter `QUEUE_CONNECTION=redis` e `CACHE_STORE=redis`  
3. Monitorar `queue:failed` + alertas  
4. Redis com persistência (AOF/RDB) e memória dimensionada  
5. Horizon (opcional, depois) se precisar dashboard/métricas por fila  
6. **Não** misturar secrets JWT de cooperativas diferentes no mesmo `.env` em produção multi-tenant real — cada instalação/cliente pode ter secret próprio via config por Cliente no futuro

## Local

```bash
docker compose up -d --build
docker compose ps   # app, queue, scheduler, mysql, redis
```
