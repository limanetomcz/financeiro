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

| Campo | Uso |
|-------|-----|
| `perfil_pagamento` | `boleto_parcelado` \| `cartao_parcelado` \| `a_vista` |
| `modo_emissao` | `imediata` (CR no ato) \| `escalonada` (CR mês a mês) |

| Status | Significado |
|--------|-------------|
| `rascunho` | Ainda não vigente |
| `ativo` | Em vigor |
| `suspenso` | Bloqueado (ex. inadimplência) |
| `encerrado` | Vigência terminou |
| `cancelado` | Cancelado |

**Não** existe renovação implícita por “parcela 13”.  
Casos: [casos-emissao-inadimplencia.md](casos-emissao-inadimplencia.md).

### Parcela

Fatia do contrato. É a dívida unitária.

| Status | Significado |
|--------|-------------|
| `prevista` | Ainda não exigível no CR (mês futuro) — evita inchar o contas a receber |
| `aberta` | Exigível / a pagar, sem cobrança ativa |
| `em_cobranca` | Vinculada a cobrança aberta |
| `paga` | Liquidada |
| `cancelada` | Anulada |
| `perdida` | Baixa como perda (futuro) |

Campo `emitida_em`: quando a parcela entrou no CR.  
`modo_emissao = escalonada` (padrão boleto): mês corrente `aberta` + `emitida_em`; futuros `prevista` sem emissão.  
`modo_emissao = imediata` (ex. cartão 12x no ato): todas `aberta` com `emitida_em` na adesão.  
Job/API `parcelas:abrir-exigiveis` promove `prevista` → `aberta` no virar do mês.

### Cobrança

Documento de pagamento (boleto, PIX, manual…). Pode ser:

- **simples** — 1 parcela
- **consolidada** — N parcelas (antiga “agregada”)

Pagamento da cobrança liquida todas as parcelas vinculadas.

Campos de valor: `valor_principal` + `valor_juros` + `valor_multa` = `valor`.  
Juros/multa são **opcionais** — o operador decide se cobra (padrão 0).

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
| POST | `/api/v1/cobrancas/consolidadas` | Emite cobrança consolidada (`valor_juros`/`valor_multa` opcionais) |
| POST | `/api/v1/cobrancas/{id}/liquidar` | Baixa cobrança + parcelas |
| POST | `/api/v1/parcelas/abrir-exigiveis` | Promove previstas → abertas |
| GET | `/api/v1/elegibilidade` | `?chave_sigoweb=` pode atender? (params no Cliente) |
