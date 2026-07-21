# Domínio: contrato, parcela, cobrança

## Visão

```text
Cliente (tenant / Uniodonto)
  └─ Contratante (beneficiário ou empresa no Sigoweb)
       └─ Contrato (vigência, valor, N parcelas)
            └─ Parcelas (o que é devido)
                 └─ Cobrança (o que é pago)  ← 1 cobranca : N parcelas
```

## Entidades

### Contratante

Quem tem o contrato conosco (PF ou PJ).

| Campo | Uso |
|-------|-----|
| `chave_sigoweb` | ID no Sigoweb (ex. código do beneficiário/empresa) |
| `tipo` | `pf` \| `pj` |
| `documento` | CPF/CNPJ normalizado |

### Contrato

Venda com vigência (ex. anual), renovação explícita via novo contrato (`renovado_de_contrato_id`).

| Status | Significado |
|--------|-------------|
| `rascunho` | Ainda não vigente |
| `ativo` | Em vigor |
| `suspenso` | Bloqueado (ex. inadimplência) |
| `encerrado` | Vigência terminou |
| `cancelado` | Cancelado |

**Não** existe renovação implícita por “parcela 13”.

### Parcela

Fatia do contrato. É a dívida unitária.

| Status | Significado |
|--------|-------------|
| `aberta` | A pagar, sem cobrança ativa |
| `em_cobranca` | Vinculada a cobrança aberta |
| `paga` | Liquidada |
| `cancelada` | Anulada |
| `perdida` | Baixa como perda (futuro) |

### Cobrança

Documento de pagamento (boleto, PIX, manual…). Pode ser:

- **simples** — 1 parcela
- **consolidada** — N parcelas (antiga “agregada”)

Pagamento da cobrança liquida todas as parcelas vinculadas.

Regra: parcela não pode estar em duas cobranças **abertas** ao mesmo tempo.

### Elegibilidade

Consulta derivada: o contratante pode usar o plano?

MVP: baseado em parcelas vencidas em aberto / contrato suspenso.  
Regra fina da Seridó será ajustada no discovery.

## Chaves Sigoweb

| Entidade | Campo | Liga com |
|----------|-------|----------|
| Cliente | `chave_sigoweb` | cooperativa (`par_coop`) |
| Contratante | `chave_sigoweb` | beneficiário/empresa |
| Contrato | `chave_plano_sigoweb` | plano (opcional) |

## APIs iniciais

| Método | Rota | Ação |
|--------|------|------|
| GET | `/api/v1/me` | Tenant + usuário JWT |
| GET | `/api/v1/contratos` | Lista contratos do tenant |
| POST | `/api/v1/contratos` | Cria contrato + parcelas |
| GET | `/api/v1/contratos/{id}` | Detalhe |
| POST | `/api/v1/cobrancas/consolidadas` | Emite cobrança consolidada |
| POST | `/api/v1/cobrancas/{id}/liquidar` | Baixa cobrança + parcelas |
| GET | `/api/v1/elegibilidade` | `?chave_sigoweb=` pode usar plano? |
