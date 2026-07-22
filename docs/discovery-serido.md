# Discovery — Uniodonto Seridó (112)

Status: **parcialmente concluído (MVP suficiente)**  
Atualizado: 2026-07-21

Inventário do que a Seridó faz hoje de contas a receber, para orientar o MVP do Financeiro novo.

---

## 1. Geração de faturas (PJ / planos empresariais)

- [x] Usa fatura PJ? **Sim — tem PF e PJ**
- [x] Ciclo **sempre mensal** (sem anuidade PJ)
- [x] Composição: soma parcelas dos beneficiários + lançamentos IR/ISS/PIS/COFINS (parametrizáveis)
- [x] Boleto = **valor líquido**
- [x] Modelo implementado: ver [fatura-pj.md](fatura-pj.md)

### Implicação

- PF e PJ coexistem; PJ usa `faturas` + `fatura_lancamentos`
- Config por cliente em `clientes.config.pj`

---

## 2. Geração de mensalidades (PF) → virará parcelas de contrato

- [x] Parcelamento: **1, 3, 6, 12**
- [x] Mesmo plano anual, CR alimentado **mês a mês** (não inchar contábil)
- [x] Avulsa: rara / gambiarra — fora do MVP
- [x] Volume: **~1800 mensalidades em junho/2026** (piloto manejável)

### Decisão de modelo (Opção A)

Contrato cria as N parcelas de uma vez:
- mês corrente (e vencidas) → status `aberta` (exigível / no CR)
- meses futuros → status `prevista` (não incham o CR operacional)
- Job/comando mensal promove `prevista` → `aberta`

---

## 3. Baixas

- [x] Meios: **boleto** (+ **PIX** desejado, ainda não tem)
- [x] CNAB retorno: **sim** (melhorar no futuro)
- [x] Banco: **Sicredi**

---

## 4. Agentes financeiros

- [x] **Sicredi** only no piloto
- [x] Remessa/retorno CNAB no fluxo atual

---

## 5. Inadimplência e bloqueio

- [x] Regra **parametrizável** por Cliente
- [x] Barrar = **negar atendimento**
- Config MVP em `clientes.config.elegibilidade`:
  - `dias_apos_vencimento`
  - `min_parcelas_vencidas`

---

## 6. Agregada / consolidada

- [x] Usam **muito** na recepção → MVP obrigatório
- [x] Pode entrar **juros e multa**; **operador decide** se cobra ou não

---

## 7. Reajuste e renovação

- [x] Hoje: “renova” pagando a **13ª** — **vamos mudar**
- [x] Novo modelo: renovação **explícita** (novo contrato / aditamento)
- [ ] Detalhe do reajuste anual de valores *(depois)*

---

## 8. Volumes

- [x] ~1800 títulos/mês (jun/2026)
- [x] PF + PJ; impostos mais relevantes no PJ

---

## Procedures Oracle a solicitar

| Procedure / função | Uso | Fonte entregue? |
|--------------------|-----|-----------------|
| Remessa/retorno Sicredi (nomes) | Geração arquivo / baixa | pendente |
| Geração mensalidade (se usada na 112) | Mapear para parcelas | pendente |

---

## Decisões MVP travadas

| Tema | Decisão |
|------|---------|
| Parcelas | 1/3/6/12 + Opção A (`prevista`/`aberta`) |
| Banco | Sicredi |
| Liquidação | Boleto + CNAB; PIX em seguida |
| Consolidada | Sim + juros/multa opcionais (operador) |
| Elegibilidade | Parametrizável; barra atendimento |
| Renovação | Explícita (sem 13ª) |
| Escopo 1º corte | PF primeiro; PJ/impostos depois |
| Avulsa | Fora do MVP |
