# Próximos passos (atualizado em 22/07/2026)

Quando voltar, diga: **“relembra os próximos passos”** (este arquivo).

## Já feito (Financeiro — commitado)

- Domínio: contrato / parcela / cobrança / fatura PJ / elegibilidade / composição familiar (DIRF)
- Juros/multa na baixa (`CalcularJurosMultaService`, 0,033%/dia + 2%)
- Locais de pagamento Seridó (canal ≠ taxa) + baixa/retirar baixa com auditoria
- API situação: `GET /api/v1/financeiro?chave_sigoweb=`
- Remessa Sicredi CNAB 240 (SOLID, fila `bancario`)
- Retorno CNAB Sicredi `.CRT` — parser T/U; códigos `02` confirma, `06` liquida, `09`/`10` exclui, `28` tarifa  
  Validado com arquivos reais `08012930` e `08012722` (+ relatório PDF Sigoweb)
- PDF boleto Sicredi desacoplado (`FabricaAdaptadorBoleto` + adapter) — `GET /cobrancas/{id}/boleto.pdf`
- Config Seridó: agência `2207`, posto `04`, cedente `08012`, CNPJ `01.751.280/0001-32`

## Lab Sigoweb (código local — repo `sigoweb` branch `develop`)

Arquivos (não versionar dados reais de `.CRT`/PDF de clientes):

- `view_php/vue/financeiro/laboratorioFinanceiro.php`
- `js/jsvue/laboratorioFinanceiro.js`

Fluxo do protótipo:

1. Beneficiário (CPF) + composição família  
2. Gerar contrato  
3. Situação financeira  
4. Grid parcelas (Detalhar / Baixar / Retirar / **Boleto**)  
5. Remessa (emitir boletos → gerar `.CRM` → download)  
6. Retorno (upload `.CRT`)

URL: `pagina.php?url=vue/financeiro/laboratorioFinanceiro.php`  
API: `localStorage.url_api_financeiro` + Bearer JWT.

## Fila imediata (continuar daqui)

### A. Polir boleto PDF
- Comparar visual com `boletos_mensalidades.pdf` (Jasper): demonstrativo dos 12 pagamentos, tributos, ANS
- Conferir linha digitável com um boleto Sicredi real (mesmo nosso número / valor / vencimento)
- Migration `retornos_bancarios` no MySQL do ambiente se ainda não rodou: `php artisan migrate`

### B. Recibo de pagamento presencial
- Quando baixa no local Uniodonto (`codigo`/`legado` `2`), emitir recibo PDF/HTML do que foi pago (valor, juros/multa, meio, data, operador)
- Endpoint sugerido: `GET|POST /parcelas/{id}/recibo` ou `/cobrancas/{id}/recibo`
- Botão no lab após baixa

### C. Endereço real do pagador
- Sync Sigoweb → `contratantes` (hoje `pagador_padrao` / campos opcionais no create)
- Necessário para boleto “igual produção”

### D. Registro API boleto + PIX Sicredi
- Além do arquivo remessa; discovery: PIX depois do CNAB estável

### E. Validar DV `fun_calculodvmodulo11`
- Se Oracle divergir, ajustar só `CalculoDvModulo11`

### F. UI real Sigoweb (fora do lab)
- Telas de produção apontando para API Financeiro; abandonar geração no `sigo-laravel` no cutover

### G. Migração / cutover piloto Seridó `112`
- Reconciliação `tb_mensalidade` / faturas  
- Flag `usa_financeiro_novo = true` + secrets

## Pendências de domínio / produto (não esquecer)

| Tema | Nota |
|------|------|
| Família toda excluída | Melhorar fallback se busca família só retornar excluídos |
| Caixa / Prática | Não mexer no caixa legado no piloto; baixa lab sem caixa |
| Fatura PJ lote | Domínio `GerarFaturaPjService` existe; falta UI/lote grande no Sigoweb — **depois** do ciclo PF |
| Remessa ponta a ponta Sicredi | Aceite do `.CRM` no banco + retorno correspondente |
| Arquivos de exemplo | `.CRT` / PDF em `storage/app/public` são locais — **não commitá-los** (PII) |

## Fora do piloto

- Remessa/boleto Bradesco e outros bancos (só registrar adapter)
- Boleto avulso (`BA`) e cooperado (`DC`)
- Débito em conta

## Como estender banco (lembrete SOLID)

| Peça | Remessa | Boleto PDF | Retorno |
|------|---------|------------|---------|
| Fábrica | `FabricaAdaptadorBanco` | `FabricaAdaptadorBoleto` | parser por banco no service |
| Contrato | `BancoRemessaAdapterInterface` | `BancoBoletoAdapterInterface` | `RetornoParserInterface` |
| Novo banco | pasta `app/Bancario/{Banco}/` + `registrar()` | idem + view Blade | novo parser + códigos movimento |

## Docs úteis

- [remessa-cnab.md](remessa-cnab.md) — remessa + retorno + PDF boleto
- [discovery-serido.md](discovery-serido.md)
- [filas-redis.md](filas-redis.md)
- [integracao-sigoweb.md](integracao-sigoweb.md)
- [dominio.md](dominio.md)
- [fatura-pj.md](fatura-pj.md)
