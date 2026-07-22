# Remessa CNAB (SOLID / multi-banco)

## Objetivo

Gerar arquivo de remessa **fora do Oracle**, de forma **assíncrona** (fila `bancario`), com arquitetura aberta para outras cooperativas/bancos.

No legado, quase tudo está em `PRO_ARQUIVO_REMESSA_BANCOS` + `view_remessa_boletos`. Aqui isso vira adaptadores PHP + **fontes compostas** (no lugar do `UNION ALL`).

## Por que abandonar a view

A `view_remessa_boletos` mistura 10 braços (inclusão + alteração × M/MA/F/BA/DC), regras de banco inline, `NOT EXISTS` correlacionados e joins frágeis — impossível de manter com segurança por um único dev. Anomalias observadas no fonte:

| Problema | Onde |
|----------|------|
| `tb_contas` sem join explícito | quase todos os braços → risco de produto cartesiano |
| Alias duplicado `p` (plano e pessoa) | braço mensalidade |
| Inclusão DC exige `clc_numeroregistro is null` e o filtro externo `NUMEROREGISTRO IS NOT NULL` | braço cooperado **nunca retorna** |
| Join inconsistente agregada (`mea_meg_*` vs `mea_men_*`) | inclusão vs alteração MA |
| Multa/juros/especie hardcoded por banco em cada braço | cópia 10× |
| Filtros de período comentados na view | filtro só na procedure |

No Financeiro cada braço vira uma **fonte** testável.

## SOLID

| Princípio | Como |
|-----------|------|
| **S** | Seleção, nosso número, layout, nome e orquestração separados |
| **O** | Novo banco = adapter; novo tipo de título = nova `FonteTituloRemessaInterface` |
| **L** | Adapters e fontes intercambiáveis |
| **I** | Interfaces pequenas |
| **D** | `GerarRemessaService` → fábrica; seletor → fontes |

```text
view_remessa_boletos (UNION ALL × 10)
        │  vira
        ▼
CompositeTitulosRemessaSelector
  ├─ EntradaCobrancasFonte        (M + MA + F, ocorrência 01)
  ├─ AlteracaoVencimentoCobrancasFonte (06)
  └─ (futuro) AvulsoFonte / CooperadoFonte se o domínio pedir
```

## Mapeamento legado → domínio novo

| View `tipoboleto` | Legado | Domínio Financeiro |
|-------------------|--------|--------------------|
| `M` | `tb_mensalidade` | Cobrança simples (boleto) |
| `MA` | mensalidade agregada | Cobrança **consolidada** |
| `F` | `tb_fatura` | Cobrança ligada a **fatura PJ** |
| `BA` | CR beneficiário | Fora do piloto Seridó |
| `DC` | lançamento cooperado | Fora do piloto (e braço legado morto) |

### Regras de entrada (01) — portadas

- status aberto / não pago  
- número de registro presente (gerado se faltar)  
- ainda não consta em remessa com enviado ∈ {1,2,3}  
- **vencimento > hoje** e dentro do intervalo pedido  
- CPF/CNPJ do pagador preenchido  
- juros/dia ≈ `(percentual_juros_mes / 30 / 100) * valor`  
- multa percentual **2%**, `codigo_multa = 2`  
- Sicredi: espécie `99`, tipo doc PF `1` / PJ `2`, dias devolução `60`

### Alteração (06)

Título já enviado (`enviado_remessa = 2`) cujo vencimento da cobrança **diferente** do vencimento do item anterior.

## API

```http
POST /api/v1/remessas
Authorization: Bearer {jwt}
Content-Type: application/json

{
  "vencimento_inicial": "2026-04-01",
  "vencimento_final": "2026-04-30",
  "sincrono": false
}
```

| Campo | Uso |
|-------|-----|
| `sincrono=false` (padrão) | HTTP 202 + job na fila `bancario` |
| `sincrono=true` | Processa na request (só testes/piloto) |

```http
GET /api/v1/remessas
GET /api/v1/remessas/{id}
GET /api/v1/remessas/{id}/download
```

## Config do cliente (`clientes.config.bancario`)

Tudo que a view/procedure hardcodava (banco, espécie, multa %, posto, cidade padrão, etc.) vai em config. Contador Sicredi/Unicred: coluna `clientes.contador_boletos_unicred` (ex-`par_contadorboletosunicred`).

```json
{
  "banco": "sicredi",
  "codigo_banco": "748",
  "conta": {
    "agencia": "0101",
    "posto": "00",
    "conta": "12345",
    "dv_conta": "6",
    "carteira": "1",
    "modalidade_carteira": "1",
    "codigo_cedente": "12345",
    "beneficiario_nome": "UNIODONTO SERIDO",
    "beneficiario_cnpj": "00000000000191",
    "dias_devolucao": 60,
    "percentual_multa": 2.0,
    "percentual_juros_mes": 1.0
  },
  "cnab": {
    "nome_banco": "SICREDI",
    "especie_titulo": "99",
    "codigo_multa": "2",
    "tipo_inscricao_pf": "1",
    "tipo_inscricao_pj": "2",
    "contador_digitos": 6,
    "layout_versao": "081",
    "layout_densidade": "01600"
  },
  "pagador_padrao": {
    "cidade": "CAICO",
    "cep": "59300000",
    "uf": "RN"
  }
}
```

### Número de registro (`Fun_GerarNumRegistroUnicred`)

```text
registro = YY + contador(pad) + DV11(agencia + posto + conta + YY + contador)
contador++
```

DV: `CalculoDvModulo11` (pesos 2..9). Se o fonte de `fun_calculodvmodulo11` divergir, ajustar só essa classe.

## Extensão: outro banco / outra Uniodonto

1. `app/Bancario/{Banco}/` — layout + adapter + nome arquivo  
2. Reusar fontes de seleção ou criar fontes específicas  
3. `FabricaAdaptadorBanco::registrar`  
4. Config do tenant

## Pendências

| Artefato | Status |
|----------|--------|
| `PRO_ARQUIVO_REMESSA_BANCOS` (Sicredi) | Layout P/Q/R |
| `view_remessa_boletos` | Regras portadas para fontes (M/MA/F + 06) |
| `Fun_GerarNumRegistroUnicred` | Portada (`SicrediNossoNumeroGenerator`) |
| `fun_calculodvmodulo11` | Portada padrão; validar com fonte Oracle se possível |
| Retorno CNAB / `tb_baixar_arquivo_banco` tipo `02` | Parser + liquidação Sicredi 240 (T/U) — validar códigos com `.CRT` real Seridó |
| Endereço real do pagador | Placeholder até sync Sigoweb |
| BA / DC | Fora do piloto |

## Status da remessa

`pendente` → `processando` → `concluida` | `vazia` | `falha`

## Retorno CNAB Sicredi (`.CRT`)

```http
POST /api/v1/retornos
Authorization: Bearer {jwt}
Content-Type: multipart/form-data

arquivo: (file .CRT)
```

```http
GET /api/v1/retornos
GET /api/v1/retornos/{id}
```

Parser: `SicrediCnab240RetornoParser` (posições do legado `arquivoSicredi200Colunas`).

| Código movimento (seg. T) | Ação |
|---------------------------|------|
| `02` | Confirma entrada → `remessa_itens.enviado_remessa = 2` |
| `06`, `17` | Liquida cobrança (local Sicredi `7`); juros do seg. U quando houver |
| `09`, `10` | Exclusão/baixa pelo banco → cancela cobrança e libera parcelas |
| `03` | Registra rejeição |
| `28` | Tarifa/custas — registra sem liquidar |
| outros | Registra sem ação automática |

Arquivo duplicado (mesmo SHA-256) é rejeitado.

## PDF do boleto

```http
GET /api/v1/cobrancas/{id}/boleto.pdf
Authorization: Bearer {jwt}
```

Gera PDF (recibo + ficha de compensação) com linha digitável / código de barras.

### Extensão (outro banco)

Mesmo padrão da remessa (OCP):

1. `app/Bancario/{Banco}/` — `*CodigoBarrasBoleto` + `*BoletoAdapter`
2. View `resources/views/boletos/{banco}.blade.php`
3. `FabricaAdaptadorBoleto::registrar('237', BradescoBoletoAdapter::class)`
4. Config do tenant (`bancario.banco` / `codigo_banco`)

`GerarPdfBoletoService` não conhece Sicredi — só a fábrica + DTO `BoletoCodigoBarrasDTO`.
