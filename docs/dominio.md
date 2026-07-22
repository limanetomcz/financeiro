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
| `chave_plano_sigoweb` | Código do plano no Sigoweb (**obrigatório**) |
| `chave_familia_sigoweb` | Código da família no Sigoweb |
| `valor_mensal_familia` | Soma dos `valor_mensal` dos integrantes |
| `valor_total` | `valor_mensal_familia × quantidade_parcelas` |
| `perfil_pagamento` | `boleto_parcelado` \| `cartao_parcelado` \| `a_vista` |
| `modo_emissao` | `imediata` (CR no ato) \| `escalonada` (CR mês a mês) |

**Composição da família (DIRF):**  
`contrato_beneficiarios` guarda cada integrante (carteira, nome, CPF, titular/dependente, **valor mensal**).  
Em cada parcela, `parcela_beneficiarios` é o snapshot do valor daquele mês (base para DIRF/cliente).

**Unicidade:** mesmo contratante + mesmo plano + vigência sobreposta → bloqueado  
(status `ativo`/`suspenso`/`rascunho`). Planos diferentes no mesmo período são permitidos.

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
Juros/multa na baixa: port de `FUN_CALCULAR_JUROS_MULTA` — **0,033%/dia** + multa **2%**,  
carência se vencimento cai FDS e pagamento em até 2 dias (Sáb/Dom/Seg), flag `cobranca.cobrar_multa_juros_pf`.  
Na baixa usamos a **data de recebimento** (não SYSDATE) para dias/atraso.  
Operador decide se cobra (`aplicar_encargos`). Preview: `POST /parcelas/{id}/calcular-juros`.

Regra: parcela não pode estar em duas cobranças **abertas** ao mesmo tempo.

### Local de pagamento (por tenant)

Separação **canal ≠ tarifa** (o Oracle misturava os dois em `tb_localpagamento`):

| Tabela | Papel |
|--------|--------|
| `locais_pagamento` | Canal: Uniodonto, Sicredi, Caixa, PIX, Itaú cartão… |
| `taxas_local_pagamento` | Condição/tarifa do cartão (modalidade + bandeira + %), com `codigo_legado` = `LOC_CODIGO` |

Catálogo **por cooperativa** (`cliente_id`).  
Na baixa, a cobrança guarda snapshot: código, descrição, `taxa_percentual`, `valor_taxa`, modalidade, bandeira.  
Também grava `baixado_por` / `baixado_por_nome` (e, no estorno, `baixa_retirada_por*`).  
Fusca fica desligado (`FINANCEIRO_AUDITORIA_FUSCA=false`) até sair do lab.

APIs:
- `GET /api/v1/locais-pagamento?com_taxas=1`
- `GET /api/v1/locais-pagamento/resolver?codigo_legado=61`
- `POST /api/v1/parcelas/{id}/baixar` — `codigo_legado` **ou** `local_pagamento_codigo` (+ `taxa_id` se cartão)
- `POST /api/v1/parcelas/{id}/retirar-baixa`
- `POST /api/v1/cobrancas/{id}/liquidar` — baixa da cobrança (mesmo snapshot/operador)

### Elegibilidade

Consulta derivada: o contratante pode usar o plano?

MVP: baseado em parcelas vencidas em aberto / contrato suspenso.  
Regra fina da Seridó será ajustada no discovery.

## Chaves Sigoweb

| Entidade | Campo | Liga com |
|----------|-------|----------|
| Cliente | `chave_sigoweb` | cooperativa (`par_coop`) |
| Contratante | `chave_sigoweb` | beneficiário/empresa |
| Contrato | `chave_plano_sigoweb` | plano (obrigatório) |

## APIs iniciais

| Método | Rota | Ação |
|--------|------|------|
| GET | `/api/v1/me` | Tenant + usuário JWT |
| GET | `/api/v1/contratos` | Lista contratos do tenant |
| POST | `/api/v1/contratos` | Cria contrato + parcelas |
| GET | `/api/v1/contratos/{id}` | Detalhe |
| POST | `/api/v1/cobrancas/consolidadas` | Emite cobrança consolidada (`valor_juros`/`valor_multa` opcionais) |
| POST | `/api/v1/cobrancas/{id}/liquidar` | Baixa (+ `codigo_legado` / local + taxa) |
| GET | `/api/v1/locais-pagamento` | Canais (+ taxas aninhadas) |
| GET | `/api/v1/locais-pagamento/resolver` | Resolve `LOC_CODIGO` legado |
| POST | `/api/v1/parcelas/abrir-exigiveis` | Promove previstas → abertas |
| GET | `/api/v1/elegibilidade` | `?chave_sigoweb=` pode atender? (params no Cliente) |
