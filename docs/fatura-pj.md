# Fatura PJ (empresarial)

## Como funciona hoje (legado)

1. Gera “mensalidades” de todos os beneficiários da empresa no período  
2. Soma → lançamento **mensalidades**  
3. Acrescenta lançamentos de impostos/retenções (IR, ISS, PIS, COFINS, …)  
4. Boleto = **valor líquido**  
5. Empresa não paga → inadimplente  
6. Ciclo **sempre mensal** — não há anuidade PJ

## Modelo no Financeiro novo

```text
Contratante PJ (empresa)
  └─ Beneficiários PF (contratante.empresa_id)
       └─ Contratos / Parcelas do mês
  └─ Fatura (competência YYYY-MM)
       └─ Lançamentos (mensalidades, ir, iss, …)
       └─ Cobrança (boleto do líquido)
```

Não usamos a palavra “mensalidade” como entidade. O que entra na fatura é a **soma das parcelas exigíveis** dos PFs vinculados à empresa na competência.

## Entidades

### `faturas`

| Campo | Uso |
|-------|-----|
| `contratante_id` | Empresa (PJ) |
| `competencia` | `YYYY-MM` (sempre mensal) |
| `vencimento` | Vencimento do boleto |
| `valor_bruto` | Soma dos lançamentos natureza `base` / composição positiva |
| `valor_retencoes` | Soma natureza `retencao` |
| `valor_liquido` | Bruto − retenções (+ acréscimos, se houver) |
| `status` | `rascunho` \| `aberta` \| `em_cobranca` \| `paga` \| `cancelada` |

### `fatura_lancamentos`

| Campo | Uso |
|-------|-----|
| `codigo` | `mensalidades`, `ir`, `iss`, `pis`, `cofins`, … |
| `natureza` | `base` \| `retencao` \| `acrescimo` \| `informativo` |
| `valor` | Sempre positivo; sinal vem da natureza |
| `origem` | `soma_parcelas` \| `manual` \| `formula` |
| `meta` | JSON (alíquota, observação, ids de parcelas, …) |

### Vínculo PF → empresa

`contratantes.empresa_id` → contratante PJ.  
Só PFs com esse vínculo entram na soma da fatura.

### Parcelas incluídas

Pivot `fatura_parcela`: quais parcelas de beneficiários compuseram o lançamento `mensalidades` (auditoria / não duplicar).

## Parametrização (`clientes.config.pj`)

| Parâmetro | Significado | Default Seridó |
|-----------|-------------|----------------|
| `min_faturas_vencidas_inadimplencia` | Com quantas faturas **vencidas** a empresa fica inadimplente | `1` |
| `max_faturas_abertas_para_gerar` | Se já existem N faturas em aberto (`aberta`/`em_cobranca`/`rascunho`), **não gera** outra | `1` |
| `bloquear_beneficiarios_se_empresa_inadimplente` | PF da empresa também fica barrado | `true` |
| `boleto_usa_valor` | `liquido` ou `bruto` | `liquido` |
| `dia_vencimento_padrao` | Dia do vencimento na competência | `10` |
| `lancamentos[]` | Composição da fatura (mensalidades, IR, ISS, …) | ver abaixo |

```json
{
  "pj": {
    "ciclo": "mensal",
    "boleto_usa_valor": "liquido",
    "dia_vencimento_padrao": 10,
    "min_faturas_vencidas_inadimplencia": 1,
    "max_faturas_abertas_para_gerar": 1,
    "bloquear_beneficiarios_se_empresa_inadimplente": true,
    "lancamentos": [
      {
        "codigo": "mensalidades",
        "descricao": "Mensalidades",
        "natureza": "base",
        "origem": "soma_parcelas",
        "ativo": true,
        "ordem": 1
      },
      {
        "codigo": "ir",
        "descricao": "IR",
        "natureza": "retencao",
        "origem": "manual",
        "ativo": true,
        "ordem": 2
      },
      {
        "codigo": "iss",
        "descricao": "ISS",
        "natureza": "retencao",
        "origem": "manual",
        "ativo": true,
        "ordem": 3
      },
      {
        "codigo": "pis",
        "descricao": "PIS",
        "natureza": "retencao",
        "origem": "manual",
        "ativo": false,
        "ordem": 4
      },
      {
        "codigo": "cofins",
        "descricao": "COFINS",
        "natureza": "retencao",
        "origem": "manual",
        "ativo": false,
        "ordem": 5
      }
    ]
  }
}
```

- `origem: soma_parcelas` — calculado pelo sistema  
- `origem: manual` — operador/API informa o valor na geração (ou regra futura por alíquota em `meta`)  
- Lançamentos `ativo: false` não entram  
- Ordem/códigos são por **Cliente** (cada Uniodonto parametriza)

## Inadimplência PJ

Elegibilidade da **empresa**: fatura `aberta`/`em_cobranca` com vencimento ultrapassado (mesmos parâmetros de dias/mínimo do Cliente, ou bloco `pj.elegibilidade` se existir).

Beneficiários da empresa podem herdar bloqueio da empresa (param: `pj.bloquear_beneficiarios_se_empresa_inadimplente`, default `true`).

## O que não é PJ

- Anuidade / `a_vista` anual — só PF  
- Renovação por 13ª — não se aplica
