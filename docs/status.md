# Legenda de status (domínio Financeiro)

Referência para migrar o protótipo do lab Sigoweb para a UI definitiva.
Enums: `app/Enums/Status*.php` (`label()` + `descricao()`).

## Parcela (`StatusParcela`)

| Código | Label | Significado |
|--------|-------|-------------|
| `prevista` | Prevista | Mês futuro — ainda não exigível; não gera boleto até abrir |
| `aberta` | Aberta | Exigível no CR, **ainda sem boleto registrado** |
| `em_cobranca` | Em cobrança | **Boleto registrado** (cobrança criada); pode ir à remessa / PDF |
| `paga` | Paga | Liquidada (baixa manual, retorno ou PIX) |
| `cancelada` | Cancelada | Não cobra mais |
| `perdida` | Perdida | Baixa por perda / inadimplência definitiva |

Flag auxiliar na API/lab: `vencida` = parcela aberta/`em_cobranca` com vencimento &lt; hoje (não é status separado).

## Cobrança / boleto (`StatusCobranca`)

| Código | Label | Significado |
|--------|-------|-------------|
| `aberta` | Aberta | Boleto em aberto aguardando pagamento/retorno |
| `paga` | Paga | Liquidada |
| `cancelada` | Cancelada | Título excluído/cancelado (ex.: retorno 09/10) |
| `expirada` | Expirada | Sem liquidação útil no prazo |

## Remessa (`StatusRemessa`)

| Código | Label | Significado |
|--------|-------|-------------|
| `pendente` | Pendente | Criada, arquivo ainda não gerado |
| `processando` | Processando | Gerando `.CRM` (fila) |
| `concluida` | Concluída | `.CRM` pronto para download/envio |
| `vazia` | Vazia | Nenhum título elegível no filtro |
| `falha` | Falha | Erro na geração |

## Retorno bancário (`StatusRetornoBancario`)

| Código | Label | Significado |
|--------|-------|-------------|
| `pendente` | Pendente | `.CRT` recebido |
| `processando` | Processando | Aplicando itens |
| `concluido` | Concluído | Tudo tratado |
| `parcial` | Parcial | Parte ok, parte erro/ignorado |
| `falha` | Falha | Erro no processamento |

## Fluxo resumido (lab → produto)

```text
prevista → (abrir) → aberta → (registrar boleto) → em_cobranca
                                              ↓
                                    remessa concluida (.CRM)
                                              ↓
                                    retorno (.CRT) → parcela paga
```
