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

## Caso 1 — Cartão 12x (Seridó)

Contrato anual pago no cartão (valor cheio em 12x na operadora). Na Uniodonto as parcelas **já nascem baixadas** (`paga`), porque o recebimento ocorreu na adesão. A **emissão** (`emitida_em`) segue **mês a mês** para não poluir o CR contábil de uma vez.

### Padrão Seridó — emissão escalonada + já baixada

- `perfil_pagamento = cartao_parcelado`
- `modo_emissao = escalonada` (padrão do perfil)
- Mês corrente e anteriores: status `paga`, com `emitida_em` e `pago_em`
- Meses futuros: `prevista` (ainda sem emissão) → **não incham o CR**
- Job `parcelas:abrir-exigiveis` promove `prevista` → **`paga`** (não `aberta`) e preenche `emitida_em`

### Variante — emissão imediata (todas baixadas no ato)

- `modo_emissao = imediata`
- 12 parcelas `paga` com `emitida_em = hoje`
- Uso raro: só se a cooperativa aceitar reconhecer tudo no mês da adesão

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

| Caso | Perfil | Emissão | Status | Inadimplência |
|------|--------|---------|--------|---------------|
| Cartão 12x (Seridó) | `cartao_parcelado` | `escalonada` | `paga` / `prevista`→`paga` | Não (já liquidado) |
| Cartão 12x tudo no ato | `cartao_parcelado` | `imediata` | todas `paga` | Não |
| Anual à vista pago | `a_vista` + `ja_pago` | 1 parcela | `paga` | Só na renovação |
| Boleto 12x | `boleto_parcelado` | `escalonada` | `aberta` / `prevista`→`aberta` | Mensal |
