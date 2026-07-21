# Cliente (tenant) e chave Sigoweb

## O que é “Cliente” neste sistema

**Cliente** = a Uniodonto / cooperativa que contrata o Financeiro (ex.: Uniodonto Seridó).

Não confundir com:

| Termo | Significado |
|-------|-------------|
| **Cliente** (tenant) | Cooperativa (Seridó, Ilhéus, …) |
| **Contratante / beneficiário** | Pessoa/empresa que tem contrato e parcelas |
| **Usuário** | Funcionário logado no Sigoweb |

## Por que cadastrar do nosso lado

1. Multi-tenant: todos os dados (contratos, parcelas, cobranças) pertencem a um Cliente.
2. Rollout cooperativa a cooperativa (flag: Cliente ativo no Financeiro ou ainda no legado).
3. Configurações por cliente (bancos, regras de inadimplência, etc.) sem `if (par_coop == ...)`.
4. **Chave de correlação com o Sigoweb** — saber quem é quem do outro lado.

## Campos mínimos do cadastro

| Campo | Descrição |
|-------|-----------|
| `id` | UUID interno do Financeiro |
| `nome` | Ex.: Uniodonto Seridó |
| `codigo_cooperativa` | Ex.: `112` (`par_coop`) |
| `chave_sigoweb` | Identificador estável para integração (ver abaixo) |
| `ativo` | Se o Cliente já opera neste sistema |
| `usa_financeiro_novo` | Feature flag de cutover (true = gera/baixa aqui) |
| `timezone` / `config` | JSON de preferências |

### `chave_sigoweb`

Chave explícita de ligação com o ecossistema Sigoweb/sigo-laravel.

No MVP pode ser igual ao `codigo_cooperativa` (`112`), mas o campo separado permite:

- mudar código interno sem quebrar integração
- ambientes (homolog/prod) com chaves distintas
- futuro: UUID emitido pelo Sigoweb

**Regra:** todo request autenticado resolve o Cliente por `par_coop` do JWT ↔ `chave_sigoweb` ou `codigo_cooperativa`.

## Modelo multi-tenant

**Single database + `cliente_id` em todas as tabelas de negócio.**

Motivos:

- Piloto com uma cooperativa e depois N
- Migração/reconciliação mais simples
- Relatórios cross-tenant só para admin da plataforma (nós)

Não usar database-per-tenant no início.

### Isolamento

- Middleware `ResolveCliente` após auth JWT
- Global scope Eloquent: `where cliente_id = atual`
- Jobs/filas sempre com `cliente_id` no payload

## Piloto

| Campo | Valor Seridó |
|-------|----------------|
| nome | Uniodonto Seridó |
| codigo_cooperativa | `112` |
| chave_sigoweb | `112` (MVP) |
| usa_financeiro_novo | `false` até cutover |

## Seed inicial

Criar o Cliente Seridó no migrate/seed do projeto. Demais cooperativas entram sob demanda no rollout.
