# Fatura PJ (empresarial) — protótipo

## Objetivo

Abandonar no cutover: `tb_fatura`, `tb_mensalidade`, `tb_lancamento_mensalidade`, `tb_lancamento_fatura`.  
**Não alterar Sigoweb de produção** — só protótipo (lab + Financeiro + endpoint Laravel de leitura).

## Fluxo

```text
1. Lab/Sigoweb-protótipo  →  POST Financeiro /faturas
      { chave_plano_sigoweb, competencia, sincrono? }
2. Financeiro cria fatura status=processando  →  202
3. Job (fila cobranca)  →  GET Laravel dadosFaturaFinanceiroNovo (HTTP sync)
4. Laravel só LÊ Oracle: plano E, vidas, histben/TP, vlpreco, flags imposto
5. Financeiro calcula valor por vida (regras Seridó), soma, impostos
6. Grava fatura_lancamentos + fatura status=aberta (ou erro)
```

`sincrono=1` processa na hora (lab sem worker).

## O que NÃO fazemos

- Não chama `Fun_GeraValorMensFamiliaPlEmpr` / `Fun_GeraValorMensBenefPlEmpr` (gravam Oracle).
- Não grava fatura no Oracle.
- Sigoweb legado continua como está.

## Cálculo Seridó (no Financeiro)

Espelha a intenção de `Fun_GeraValorMensBenefPlEmpr`:

- 1ª competência no Financeiro **ou** TP mudou → preço `tb_vlpreco`
- Meses seguintes → valor da **fatura anterior do Financeiro** (não `lme_*`)
- Impostos: flags do plano + alíquotas `tb_impostossobrefaturas`

Outro tenant = outra strategy (parametrizar depois).

## API

| Método | Rota | Uso |
|--------|------|-----|
| `POST` | `/faturas` | `chave_plano_sigoweb` + `competencia` → 202 |
| `GET` | `/faturas/{id}` | Polling (`processando` / `aberta` / `erro`) |
| `DELETE` | `/faturas/{id}` | Remove fatura (+ boleto se não pago) |
| `POST` | `/faturas/{id}/cobranca` | Boleto do líquido |
| `GET` | `/faturas/{id}/fatura.pdf` | PDF fatura de serviço |
| `GET` | `/faturas/{id}/demonstrativo-titulares.pdf` | Demonstrativo só titulares |
| `GET` | `/faturas/{id}/demonstrativo.pdf` | Demonstrativo titulares + dependentes |
| `GET` | `/faturas/{id}/boleto.pdf` | PDF boleto (exige `cobranca_id`) |
| Laravel `GET` | `.../dadosFaturaFinanceiroNovo/{plano}?competencia=` | Dados brutos |

**Número da fatura:** `AAAAMM/SSSS` (ex. `202612/0001`).

- Sequência **por tenant** + competência (cada coop pode ter `202612/0001`).
- Número é **queimado** ao alocar: apagar a fatura não reaproveita o SSSS (próxima = `0002`).
**Exclusão:** lógica (`deleted_at`). Número permanece no histórico; cobrança vinculada vai para `cancelada`.

Config Financeiro: `SIGO_LARAVEL_URL`.

## Documentos (lab seção 7)

Após fatura `aberta` / `em_cobranca` / `paga`:

1. Fatura  
2. Demonstrativo titulares  
3. Demonstrativo titulares + dependentes  
4. Boleto (após emitir cobrança)

## Status fatura

`processando` → `aberta` → `em_cobranca` → `paga`  
ou `erro` (+ `mensagem_erro`)
