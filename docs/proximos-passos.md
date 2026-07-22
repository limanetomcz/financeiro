# Próximos passos (atualizado em 22/07/2026 — noite)

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

## Lab Sigoweb (`develop`)

- `view_php/vue/financeiro/laboratorioFinanceiro.php`
- `js/jsvue/laboratorioFinanceiro.js`
- Fluxo: família → gerar → listar → **Registrar boletos** → remessa `.CRM` → retorno `.CRT` → PDF
- Legendário de status no grid (parcela / remessa)

URL: `pagina.php?url=vue/financeiro/laboratorioFinanceiro.php`  
API: `localStorage.url_api_financeiro` + Bearer JWT.

## Fila imediata — estagiário (sugerido hoje à noite)

### A. Personalizar / polir boleto PDF *(bom escopo para estagiário)*

**Por quê:** visual, com referência clara, pouco risco de quebrar CNAB/remessa.

Arquivos principais:
- `resources/views/boletos/sicredi.blade.php`
- `app/Services/Boleto/GerarPdfBoletoService.php`
- `app/Bancario/Sicredi/SicrediBoletoAdapter.php`
- Referência visual: Jasper legado `boletos_mensalidades.pdf` (local; não commitar PII)

Checklist:
1. Comparar lado a lado com o PDF Jasper (layout ficha de compensação)
2. Logo Uniodonto Seridó / cedente
3. Demonstrativo dos beneficiários da parcela (já existe composição; exibir no PDF)
4. Bloco “próximas parcelas” / demonstrativo dos 12 pagamentos se couber
5. Textos ANS / tributos / instruções de pagamento Sicredi
6. Conferir linha digitável e código de barras com um boleto Sicredi real (mesmo NN/valor/vencimento)
7. Endereço do pagador: hoje usa `pagador_padrao` se contratante sem endereço — documentar gap (item C)

**Não mexer nesta tarefa:** parser CNAB, geração `.CRM`, retorno `.CRT` (salvo se achar divergência de DV — avisar).

### B. Recibo de pagamento presencial
- Baixa no local Uniodonto → recibo PDF
- Endpoint + botão no lab

### C. Endereço real do pagador
- Sync Sigoweb → `contratantes` (boleto “igual produção”)

### D. Depois do boleto polido
- PIX / API registro Sicredi (discovery)
- UI real Sigoweb (fora do lab)
- Cutover piloto Seridó `112`

## Pendências

| Tema | Nota |
|------|------|
| Remessa ponta a ponta Sicredi | Aceite do `.CRM` no banco + retorno |
| Fatura PJ lote | Depois do ciclo PF |
| Arquivos `.CRT`/PDF locais | **Não commitá-los** (PII) |

## Docs úteis

- [status.md](status.md) — legendas de status
- [remessa-cnab.md](remessa-cnab.md)
- [discovery-serido.md](discovery-serido.md)
- [integracao-sigoweb.md](integracao-sigoweb.md)
