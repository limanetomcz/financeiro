# Próximos passos (atualizado em 27/07/2026)

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
  - **Lote:** `POST /faturas/lote` (competência + data base → todos planos E; lab botão “Gerar todas”)
  - Ver [fatura-pj.md](fatura-pj.md)

## Lab Sigoweb

Branch: `feature/lab-financeiro-prototipo-pj` (não mergear em `develop`/`main` sem alinhamento).

- `view_php/vue/financeiro/laboratorioFinanceiro.php`
- `js/jsvue/laboratorioFinanceiro.js`
- Fluxo PF: família → gerar → listar → **Registrar boletos** → remessa `.CRM` → retorno `.CRT` → PDF
- Fluxo PJ (seção 7): buscar dados Laravel → gerar fatura (síncrono) → **gerar todas (lote)** → PDFs → alterar datas → consultar com filtros → limpar

URL: `pagina.php?url=vue/financeiro/laboratorioFinanceiro.php`  
API: `localStorage.url_api_financeiro` + Bearer JWT.

**Atenção:** se Docker recriar o MySQL, rode de novo `php artisan db:seed` (Cliente `112`). Sem seed → `Cliente não cadastrado no Financeiro`.  
O entrypoint do app também tenta o `ClienteSeridoSeeder` em `APP_ENV=local`.  
**PHPUnit:** o container exporta `DB_*=mysql`; `tests/bootstrap.php` força SQLite `:memory:` (senão `RefreshDatabase` apaga o lab).

Laravel (dados fatura, só leitura): branch `feature/financeiro-novo-dados-fatura-readonly`.

## MVP1 — fila imediata

### A. Personalizar / polir boleto PDF *(estagiário)*

Arquivos: `resources/views/boletos/sicredi.blade.php`, `GerarPdfBoletoService`, adapter Sicredi.  
Referência: Jasper `boletos_mensalidades.pdf` (local; não commitar PII).  
Não mexer em CNAB/remessa/retorno.

### B. Validar no lab (fechar lacunas)

- PF: gerar financeiro → boleto PDF / remessa com endereço real → aceitar `.CRM` no Sicredi + processar `.CRT`
- PJ: fatura competência real → desconto/lançamentos → cobrança → remessa do boleto PJ (mesmo CNAB)

### C. Antes de produção (arquitetura async — **obrigatório**)

Hoje no lab o botão **Gerar todas** já responde **202** (job orquestrador); o cálculo Oracle continua no worker (`ProcessarFaturaPjJob`). Ainda falta endurecer para cutover:

**Alvo de produção:**

```text
Sigoweb  →  POST /faturas ou /faturas/lote  →  202 imediato
              ↓
         fila cobranca (1 job por plano, ou lote orquestrado)
              ↓
         GET sigo-laravel (leitura Oracle)  —  dentro do worker, com retry
              ↓
         fatura aberta / erro  →  Sigoweb só consulta status
```

Pendências concretas:

- [ ] Lote **sempre assíncrono** em produção (`sincrono` só lab/debug)
- [ ] Worker Redis estável (`queue:work` / Horizon) na fila `cobranca`
- [ ] Timeout / retry / backoff na chamada Financeiro → sigo-laravel
- [ ] UI Sigoweb: pedir lote → acompanhar progresso (processando / aberta / erro) sem esperar HTTP longo
- [ ] (Opcional) cache ou snapshot de vidas por competência+data_base se o Oracle continuar lento
- [ ] Medir tempo real por plano (vidas grandes) antes do cutover `112`

### D. Depois do lab estável
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
| **Async produção (lote + Laravel)** | Ver §C — **bloqueia cutover** |
| Remessa ponta a ponta Sicredi | Aceite do `.CRM` no banco + retorno |
| PIX / registro online Sicredi | Discovery |
| UI operacional Sigoweb | Lab ≠ produção; acompanhar status do lote |
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
