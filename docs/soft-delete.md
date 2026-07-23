# Soft delete (exclusão lógica)

Domínio financeiro usa `deleted_at` (`SoftDeletes`) nas entidades principais.

## Tabelas

| Tabela | Soft delete |
|--------|-------------|
| `faturas` | sim |
| `fatura_lancamentos` | sim |
| `contratantes` | sim |
| `contratos` | sim |
| `parcelas` | sim |
| `cobrancas` | sim |
| `remessas` / `remessa_itens` | sim |
| `retornos_bancarios` / `retorno_bancario_itens` | sim |
| `contrato_beneficiarios` / `parcela_beneficiarios` | sim |
| `locais_pagamento` / `taxas_local_pagamento` | sim |
| `fatura_numero_sequencias` | **não** (contador) |
| `clientes` | **não** (tenant) |

## Regras

- Listagens/APIs ignoram excluídos (scope padrão do Eloquent).
- **Número de fatura** e **lote de remessa** não reaproveitam (contam `withTrashed`).
- Lab **limpar financeiro**: soft delete em cascata + renomeia `chave_sigoweb` (`#del#…`) para liberar unique e permitir recriar.
- Lab **apagar remessa**: soft delete remessa/itens/cobranças; parcelas voltam a `aberta`.
- Remover fatura: soft delete fatura + soft delete cobrança (`cancelada`).
- Regeneração interna (itens de remessa / lançamentos ao reprocessar): `forceDelete` só dos filhos substituídos.
