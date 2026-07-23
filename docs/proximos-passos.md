# Próximos passos (atualizado em 23/07/2026)

Quando voltar, diga: **“relembra os próximos passos”** (este arquivo).

## Já feito (Financeiro)

- Domínio: contrato / parcela / cobrança / fatura PJ / elegibilidade / composição familiar (DIRF)
- Juros/multa na baixa (`CalcularJurosMultaService`, 0,033%/dia + 2%)
- Locais de pagamento Seridó (canal ≠ taxa) + baixa/retirar baixa com auditoria
- API situação: `GET /api/v1/financeiro?chave_sigoweb=`
- Remessa Sicredi CNAB 240 (SOLID, fila `bancario`) + lab registrar lote / apagar remessa+boletos
- Retorno CNAB Sicredi `.CRT` — `02` confirma, `06` liquida, `09`/`10` exclui, `28` tarifa
- PDF boleto Sicredi (barcode HTML sem GD) — `GET /cobrancas/{id}/boleto.pdf`
- Legendário de status: enums `label()`/`descricao()` + [status.md](status.md)
- Config Seridó: agência `2207`, posto `04`, cedente `08012`, CNPJ `01.751.280/0001-32`
- Endereço pagador + bloqueio de cobrança sem endereço completo
- **Fatura PJ protótipo (async):** plano E → `processando` → Laravel só leitura (vidas/TP/preço/flags) → cálculo Seridó no Financeiro → `aberta`. Sem gravar Oracle. Ver [fatura-pj.md](fatura-pj.md)

## Lab Sigoweb (`develop`)

- `view_php/vue/financeiro/laboratorioFinanceiro.php`
- `js/jsvue/laboratorioFinanceiro.js`
- Fluxo PF: família → gerar → listar → **Registrar boletos** → remessa `.CRM` → retorno `.CRT` → PDF
- Soft delete (`deleted_at`) no domínio — ver [soft-delete.md](soft-delete.md)

URL: `pagina.php?url=vue/financeiro/laboratorioFinanceiro.php`  
API: `localStorage.url_api_financeiro` + Bearer JWT.

## MVP1 — fila imediata

### A. Personalizar / polir boleto PDF *(estagiário)*

Arquivos: `resources/views/boletos/sicredi.blade.php`, `GerarPdfBoletoService`, adapter Sicredi.  
Referência: Jasper `boletos_mensalidades.pdf` (local; não commitar PII).  
Não mexer em CNAB/remessa/retorno.

### B. Validar no lab

- PF: gerar financeiro → boleto PDF / remessa com endereço real
- PJ: empresa → vínculo → fatura competência → desconto → cobrança → PDF

### C. Depois do boleto + endereço + fatura lab
- Remessa ponta a ponta (aceite `.CRM` no Sicredi + retorno)
- PIX / API registro Sicredi (discovery)
- UI real Sigoweb (fora do lab)
- Cutover piloto Seridó `112`

## MVP2 (fora do piloto Seridó imediato)

- **Recibo de pagamento presencial** — Seridó **não usa**; adiar
- Fatura PJ lote grande / impostos por fórmula
- Outros bancos / BA / DC / débito em conta
## Pendências

| Tema | Nota |
|------|------|
| Remessa ponta a ponta Sicredi | Aceite do `.CRM` no banco + retorno |
| Arquivos `.CRT`/PDF locais | **Não commitá-los** (PII) |

## Docs úteis

- [status.md](status.md) — legendas de status
- [fatura-pj.md](fatura-pj.md)
- [remessa-cnab.md](remessa-cnab.md)
- [discovery-serido.md](discovery-serido.md)
- [integracao-sigoweb.md](integracao-sigoweb.md)
