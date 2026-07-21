# Como usar este repositório

Passo a passo para outra pessoa clonar, subir e trabalhar no Financeiro.

## Pré-requisitos

| Ferramenta | Observação |
|------------|------------|
| Git | Clonar o repo |
| Docker + Docker Compose | Subir app, MySQL e Redis |
| PHP 8.2+ e Composer | Opcional no host (útil para `artisan` / testes fora do container) |

No Windows, se o `docker` não existir no PowerShell, use **WSL** (ex.: `wsl -e bash -lc "..."`).

## 1. Clonar

```bash
git clone https://github.com/limanetomcz/financeiro.git
cd financeiro
```

## 2. Configurar ambiente

```bash
cp .env.example .env
```

Edite o `.env`:

1. Gere a chave da aplicação (no host ou no container, depois que subir):

```bash
# no host (se tiver PHP/Composer)
composer install
php artisan key:generate

# ou dentro do container (depois do passo 3)
docker compose exec app php artisan key:generate
```

2. **JWT do Sigoweb** (obrigatório para rotas autenticadas como `/api/v1/me` e contratos):

```env
SIGOWEB_JWT_SECRET=<mesmo JWT_SECRET do sigo-laravel da cooperativa>
SIGOWEB_JWT_ALGO=HS256
```

Peça o valor ao time — **não** versionar o secret no Git.

3. Confira portas padrão (já no `.env.example`):

| Serviço | Host |
|---------|------|
| API | http://localhost:8085 |
| MySQL | `127.0.0.1:3307` (user/senha `financeiro` / `financeiro`) |
| Redis | `127.0.0.1:6380` |

Se alguma porta estiver ocupada, ajuste `docker-compose.yml` e o `.env`.

### Atenção: host vs container

- No **host** (PHP local / testes / `artisan` na máquina): `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6380`.
- No **container** `app`, o `docker-compose.yml` sobrescreve para `DB_HOST=mysql` e `REDIS_HOST=redis` (portas internas). Não precisa mudar isso manualmente.

## 3. Subir os containers

```bash
docker compose up -d --build
docker compose ps
```

Esperado: `financeiro-app`, `financeiro-mysql` (healthy), `financeiro-redis`.

## 4. Dependências, migrate e seed

Se o volume montou o código sem `vendor` no container:

```bash
docker compose exec app composer install
```

Depois:

```bash
docker compose exec app php artisan migrate --seed
```

O seed cria o Cliente piloto **Uniodonto Seridó** (`codigo_cooperativa` / `chave_sigoweb` = `112`).

## 5. Conferir se subiu

```bash
curl http://localhost:8085/api/v1/health
```

Resposta esperada:

```json
{"ok":true,"service":"financeiro"}
```

Raiz:

```bash
curl http://localhost:8085/
```

## 6. Chamar API autenticada

As rotas de negócio exigem o **mesmo JWT** do Sigoweb (`Authorization: Bearer <token>`).

Exemplo:

```bash
curl http://localhost:8085/api/v1/me \
  -H "Authorization: Bearer SEU_TOKEN_JWT"
```

O token precisa trazer a cooperativa (`par_coop`, ex. `112`) para o Financeiro resolver o Cliente.

### Rotas principais (prefixo `/api/v1`)

| Método | Rota | Auth |
|--------|------|------|
| GET | `/health` | não |
| GET | `/me` | sim |
| GET/POST | `/contratos` | sim |
| GET | `/contratos/{id}` | sim |
| POST | `/cobrancas/consolidadas` | sim |
| GET | `/cobrancas/{id}` | sim |
| POST | `/cobrancas/{id}/liquidar` | sim |
| POST | `/parcelas/abrir-exigiveis` | sim |
| GET | `/elegibilidade?chave_sigoweb=` | sim |

Comando útil:

```bash
docker compose exec app php artisan parcelas:abrir-exigiveis --cliente=112
```

Detalhes do domínio: [dominio.md](dominio.md).  
SSO: [integracao-sigoweb.md](integracao-sigoweb.md).

## 7. Testes

No host (usa SQLite em memória via `phpunit.xml`):

```bash
composer install
php artisan test
```

Ou no container:

```bash
docker compose exec app php artisan test
```

## 8. Comandos do dia a dia

```bash
# logs do app
docker compose logs -f app

# shell no container
docker compose exec app bash

# parar
docker compose down

# parar e apagar volume do MySQL (cuidado: apaga dados locais)
docker compose down -v
```

## Problemas comuns

| Sintoma | O que checar |
|---------|----------------|
| Porta 6379 / 8085 / 3307 em uso | Remapear em `docker-compose.yml` e `.env` |
| `Token ausente` / 401 | Header `Authorization: Bearer ...` |
| `Cliente não cadastrado` | Seed rodou? `par_coop` do JWT bate com `chave_sigoweb`? |
| `SIGOWEB_JWT_SECRET não configurado` | Preencher no `.env` e reiniciar o container se necessário |
| Migrate falha no host | MySQL do Compose está up? `DB_PORT=3307`? |
| App 500 no container | `docker compose logs app` e permissões de `storage/` |

## O que ler em seguida

1. [README.md](../README.md) — visão e decisões do projeto  
2. [cliente-tenant.md](cliente-tenant.md) — multi-tenant  
3. [dominio.md](dominio.md) — contrato / parcela / cobrança  
4. [integracao-sigoweb.md](integracao-sigoweb.md) — autenticação sem novo login  
5. [ambiente.md](ambiente.md) — stack e portas  
