# Próximos passos (atualizado em 24/07/2026)

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
- Soft delete (`deleted_at`) no domínio — [soft-delete.md](soft-delete.md)
- Config Seridó: agência `2207`, posto `04`, cedente `08012`, CNPJ `01.751.280/0001-32`
- Endereço pagador + bloqueio de cobrança sem endereço completo
- Docker: `extra_hosts` + `SIGO_LARAVEL_URL=http://host.docker.internal:8082/sigo-laravel/public` (app alcança Apache do host)
- **Fatura PJ (lab validado):**
  - Plano E → `processando` → Laravel só leitura → cálculo Seridó → `aberta` (sem gravar Oracle)
  - Número `AAAAMM/SSSS` (sequência por tenant+competência; apagar não reaproveita)
  - 4 PDFs: fatura, demonstrativo titulares, demonstrativo completo, boleto
  - `data_emissao` + `PATCH /faturas/{id}/emissao` (só para o passado) e `PATCH .../vencimento` (sincroniza cobrança aberta)
  - `GET /faturas` com filtros ricos (número, plano, status, emissão/vencimento, sacado, apenas_abertas, excluidas…)
  - Ver [fatura-pj.md](fatura-pj.md)

## Lab Sigoweb

Branch: `feature/lab-financeiro-prototipo-pj` (não mergear em `develop`/`main` sem alinhamento).

- `view_php/vue/financeiro/laboratorioFinanceiro.php`
- `js/jsvue/laboratorioFinanceiro.js`
- Fluxo PF: família → gerar → listar → **Registrar boletos** → remessa `.CRM` → retorno `.CRT` → PDF
- Fluxo PJ (seção 7): buscar dados Laravel → gerar fatura (síncrono) → PDFs → alterar datas → consultar com filtros → limpar

URL: `pagina.php?url=vue/financeiro/laboratorioFinanceiro.php`  
API: `localStorage.url_api_financeiro` + Bearer JWT.

**Atenção:** se Docker recriar o MySQL, rode de novo `php artisan db:seed` (Cliente `112`). Sem seed → `Cliente não cadastrado no Financeiro`.

Laravel (dados fatura, só leitura): branch `feature/financeiro-novo-dados-fatura-readonly`.

## MVP1 — fila imediata

### A. Personalizar / polir boleto PDF *(estagiário)*

Arquivos: `resources/views/boletos/sicredi.blade.php`, `GerarPdfBoletoService`, adapter Sicredi.  
Referência: Jasper `boletos_mensalidades.pdf` (local; não commitar PII).  
Não mexer em CNAB/remessa/retorno.

### B. Validar no lab (fechar lacunas)

- PF: gerar financeiro → boleto PDF / remessa com endereço real → aceitar `.CRM` no Sicredi + processar `.CRT`
- PJ: fatura competência real → desconto/lançamentos → cobrança → remessa do boleto PJ (mesmo CNAB)

### C. Depois do lab estável
- Remessa ponta a ponta (aceite `.CRM` no Sicredi + retorno em produção controlada)
- PIX / API registro Sicredi (discovery)
- UI real Sigoweb (fora do lab)
- Cutover piloto Seridó `112` (`usa_financeiro_novo`)

## MVP2 (fora do piloto Seridó imediato)

- **Recibo de pagamento presencial** — Seridó **não usa**; adiar
- Fatura PJ lote grande / impostos por fórmula mais completa
- Outros bancos / BA / DC / débito em conta
- Migração Oracle → MySQL + reconciliação de saldos

## Pendências

| Tema | Nota |
|------|------|
| Remessa ponta a ponta Sicredi | Aceite do `.CRM` no banco + retorno |
| PIX / registro online Sicredi | Discovery |
| UI operacional Sigoweb | Lab ≠ produção |
| Cutover `112` | Feature flag + dual-run |
| Arquivos `.CRT`/PDF locais | **Não commitá-los** (PII) |
| Seed após recreate Docker | Sem Cliente `112` a API autentica e barra |

## Docs úteis

- [status.md](status.md) — legendas de status
- [fatura-pj.md](fatura-pj.md)
- [soft-delete.md](soft-delete.md)
- [remessa-cnab.md](remessa-cnab.md)
- [discovery-serido.md](discovery-serido.md)
- [integracao-sigoweb.md](integracao-sigoweb.md)
- [como-usar.md](como-usar.md)
