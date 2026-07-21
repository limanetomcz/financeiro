# Casos de emissão e inadimplência

Três cenários reais que o domínio precisa cobrir (adesão em **01/01/2026** como exemplo).

## Conceitos

| Conceito | Significado |
|----------|-------------|
| **Vencimento** | Data em que a parcela é devida (base da inadimplência) |
| **Emissão (`emitida_em`)** | Quando a parcela entra no contas a receber / contábil |
| **`modo_emissao`** | `imediata` = todas emitidas na adesão; `escalonada` = uma por mês |
| **`perfil_pagamento`** | `boleto_parcelado` \| `cartao_parcelado` \| `a_vista` |

Inadimplência / “barrar atendimento” olha só parcelas **abertas** ou **em_cobranca** vencidas — **não** olha `prevista`.

---

## Caso 1 — Cartão 12x

Contrato anual, pago em 12 vezes no cartão.

### 1a) Emissão imediata

- `perfil_pagamento = cartao_parcelado`
- `modo_emissao = imediata`
- 12 parcelas com `emitida_em = hoje` (adesão) e vencimentos mensais (repasse/cobrança do cartão)
- Status inicial: todas `aberta` (entram no CR no mesmo mês)

Uso: quando a Uniodonto aceita CR alto no mês da adesão.

### 1b) Emissão escalonada

- `perfil_pagamento = cartao_parcelado`
- `modo_emissao = escalonada`
- 12 parcelas; cada uma com `emitida_em` no seu mês (jan, fev, …)
- Futuras ficam `prevista` até o mês da emissão → **não incham o CR**
- Alinha com repasse gradual do cartão

Job `parcelas:abrir-exigiveis` promove `prevista` → `aberta` e preenche `emitida_em` se ainda null.

---

## Caso 2 — À vista (anual pago de uma vez)

- `perfil_pagamento = a_vista`
- `quantidade_parcelas = 1`
- Se já pago na adesão (`ja_pago = true`): parcela nasce `paga`
- Contratante **não fica inadimplente** até a próxima obrigação (renovação / novo contrato)
- Vigência do plano segue `vigencia_inicio` / `vigencia_fim` (ex.: 1 ano)

---

## Caso 3 — Boleto 12x

- `perfil_pagamento = boleto_parcelado`
- `modo_emissao = escalonada` (padrão)
- Inadimplência **mensal**: cada parcela aberta vencida conta para elegibilidade

---

## Resumo

| Caso | Perfil | Emissão | Inadimplência |
|------|--------|---------|---------------|
| Cartão 12x CR no ato | `cartao_parcelado` | `imediata` | Nas parcelas abertas/vencidas (todas já no CR) |
| Cartão 12x CR mês a mês | `cartao_parcelado` | `escalonada` | Só após a parcela do mês ser emitida/vencer |
| Anual à vista pago | `a_vista` + `ja_pago` | 1 parcela paga | Só na renovação |
| Boleto 12x | `boleto_parcelado` | `escalonada` | Mensal |
