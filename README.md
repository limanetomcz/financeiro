# Financeiro (novo sistema de cobrança)

Sistema novo, fora do monolito (`sigoweb` / `sigo-laravel`), com MySQL próprio.

Objetivo: substituir o modelo atual de **mensalidades** por um domínio alinhado ao negócio real, começando pela Uniodonto Seridó (`par_coop` **112**).

**Repositório:** https://github.com/limanetomcz/financeiro.git

---

## Como usar (setup rápido)

Guia completo: **[docs/como-usar.md](docs/como-usar.md)**.

```bash
git clone https://github.com/limanetomcz/financeiro.git
cd financeiro
cp .env.example .env
# edite .env → rode: php artisan key:generate
# preencha SIGOWEB_JWT_SECRET (mesmo JWT_SECRET do sigo-laravel)

docker compose up -d --build
docker compose exec app composer install   # se necessário
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

curl http://localhost:8085/api/v1/health
# {"ok":true,"service":"financeiro"}
```

| Serviço | URL / porta |
|---------|-------------|
| API | http://localhost:8085 |
| MySQL | `127.0.0.1:3307` |
| Redis | `127.0.0.1:6380` |
| Queue worker | container `financeiro-queue` |
| Scheduler | container `financeiro-scheduler` |

Rotas autenticadas usam `Authorization: Bearer <JWT do Sigoweb>` (sem novo login).

---

## Documentação deste repositório

| Doc | Conteúdo |
|-----|----------|
| [docs/como-usar.md](docs/como-usar.md) | **Passo a passo** para clonar, subir e usar |
| [docs/discovery-serido.md](docs/discovery-serido.md) | Discovery piloto Seridó (112) |
| [docs/integracao-sigoweb.md](docs/integracao-sigoweb.md) | SSO: mesmo JWT do Sigoweb, sem novo login |
| [docs/cliente-tenant.md](docs/cliente-tenant.md) | Cadastro de Cliente (tenant) + `chave_sigoweb` |
| [docs/dominio.md](docs/dominio.md) | Contrato, parcela, cobrança, elegibilidade |
| [docs/ambiente.md](docs/ambiente.md) | Docker, portas, stack |
| [docs/filas-redis.md](docs/filas-redis.md) | Redis, workers e filas multi-tenant |
| [docs/casos-emissao-inadimplencia.md](docs/casos-emissao-inadimplencia.md) | Cartão 12x, à vista, boleto mensal |
| [docs/fatura-pj.md](docs/fatura-pj.md) | Fatura empresarial mensal + impostos |

---

## Por que existe este projeto

O contas a receber atual está espalhado entre legado PHP, Laravel e Oracle:

- Geração de mensalidades/faturas
- Baixas (manual, CNAB, PIX, cartão, etc.)
- Remessa / agentes financeiros
- Reajuste anual
- Cobrança e bloqueio de uso do plano
- Muitas gambiarras por cooperativa (Ilhéus, Maceió, Itabuna, etc.)
- Conhecimento concentrado em poucas pessoas

Migrar tela para o `sigo-laravel` **não resolveu**: o motor de regras continua no Oracle (`pro_*`, `fun_*`). Do jeito que está, não dá para sustentar por muito tempo.

---

## Decisão de produto

| Decisão | Detalhe |
|---------|---------|
| Onde vive | App novo neste diretório (`financeiro`), **não** módulo dentro do `sigo-laravel` |
| Banco | MySQL próprio (sair do Oracle neste domínio) |
| Forma de entrega | **Sistema completo**, **API-first** |
| UI no dia 1 | Não reescrever o financeiro inteiro; Sigoweb/portal consomem a API; telas mínimas quando a operação precisar |
| Rollout | Cooperativa a cooperativa (piloto → próximo cliente → ajustes) |

**Não é:** API fina em cima do Oracle.  
**Não é:** copiar o conceito de “mensalidade” para o MySQL.

---

## Integração com o Sigoweb (resumo)

- Usuário **não** faz login de novo no Financeiro.
- Browser reutiliza o JWT já guardado no `localStorage` após login no Sigoweb.
- Financeiro valida o JWT e resolve o **Cliente** via `par_coop` ↔ `chave_sigoweb` / `codigo_cooperativa`.
- Detalhes em `docs/integracao-sigoweb.md`.

## Cliente (tenant)

- Cadastro nosso de cada Uniodonto (piloto: Seridó `112`).
- Campo `chave_sigoweb` para correlacionar com o outro lado.
- Multi-tenant: **um MySQL**, coluna `cliente_id` em todas as tabelas de negócio.
- Detalhes em `docs/cliente-tenant.md`.

---

## Modelo de domínio (o que vamos construir)

### Conceitos certos

1. **Contrato** — produto vendido com vigência (ex.: anual)
2. **Parcelas** — o contrato dividido em N cobranças
3. **Renovação explícita** — depois do 1º ano, novo contrato / aditamento (não inferida)
4. **Cobrança** — o documento que o cliente paga (boleto, PIX, etc.)
5. **Elegibilidade** — se o beneficiário pode usar o plano

### Conceito que morre

- **Mensalidade** como entidade central do sistema

Hoje a renovação é tratada de forma falha: considera-se renovado quando o cliente paga a “décima terceira mensalidade”. Isso está errado e **não** deve existir no modelo novo.

### Cobrança consolidada (antiga “agregada”)

A “agregada” nasceu (~15 anos) para um caso real de recepção:

> Cliente deve várias parcelas → emite **um** boleto com a soma → ao pagar, o sistema baixa **todas** as parcelas vinculadas.

Isso continua necessário. Só muda o nome/modelo:

```text
Contrato
  └─ Parcelas (1..N)           ← o que o cliente deve
       └─ Cobrança             ← o que o cliente paga

Uma Cobrança pode referenciar 1 ou N parcelas.
Pagamento da Cobrança → liquida as parcelas vinculadas.
```

Nomes possíveis: cobrança consolidada, boleto unificado, agrupamento de pagamento.

Regras importantes:

- Parcela não pode estar em duas cobranças abertas ao mesmo tempo
- Definir se consolidada aceita só pagamento total ou rateio parcial
- Negociação futura pode reusar o mesmo mecanismo

---

## Estratégia de rollout: piloto Seridó (112)

Escolher o cliente com **menos particularidades** possíveis.

**Piloto:** Uniodonto Seridó — `par_coop` **112**

No código atual, Seridó aparece pouco nas exceções pesadas de financeiro (diferente de Ilhéus/Maceió/Itabuna/Feira). Bom farol.

### Discovery obrigatório (antes de achar que “está pronto”)

Levantar tudo que a Seridó faz de contas a receber:

1. Geração de faturas
2. Geração de mensalidades (hoje) → virará geração de parcelas de contrato
3. Baixas (quais meios)
4. Envios aos agentes financeiros (quais bancos / remessa / API)
5. Como considera inadimplência (e o que isso bloqueia no atendimento)

Atenção: “simples no PHP” ≠ “simples no Oracle”. Mapear também as procedures que ela realmente usa.

### Sequência

1. Discovery Seridó (itens acima + volumes + exceções reais)
2. Modelo canônico (contrato / parcela / cobrança / renovação / elegibilidade)
3. MVP Seridó (gerar parcelas, baixas principais, consulta `pode usar plano`)
4. Migração de dados com reconciliação (totais devem bater com Oracle)
5. Feature flag: `par_coop=112` aponta para este sistema
6. Ideal: 1–2 ciclos em sombra (novo calcula, velho ainda cobra) antes do cutover
7. Próximo cliente: ajustar só o que for diferente (extensão, não fork)

### Escopo do piloto

- Preferir começar por **PF** se for o maior volume/dor; PJ/fatura pode ser fase seguinte no mesmo cliente
- CNAB/PIX só do que a Seridó usa de fato
- NFSe **fora** do MVP do núcleo de cobrança

---

## Migração de dados

Ponto mais crítico do projeto (não a tela).

Cada título atual (`tb_mensalidade` e afins) precisa virar algo no espírito de:

```text
Contrato (vigência, plano, valor, status)
  └─ Parcela 1..N (vencimento, valor, status)
       └─ Cobranças / baixas
```

Definir regras explícitas para: avulsa, consolidada (agregada), renegociação, vencido, parcial, cancelado.

Sem reconciliação de saldo aberto/pago/vencido, não há cutover.

---

## Bounded contexts (não misturar tudo)

| Contexto | Responsabilidade |
|----------|------------------|
| Precificação / reajuste | Calcula valor |
| Faturamento | Gera contrato e parcelas |
| Meios de pagamento | Boleto, PIX, CNAB, cartão (adaptadores) |
| Liquidação | Concilia pagamento ↔ parcelas |
| Cobrança / negociação | Processo, acordo, carta, contato |
| Elegibilidade | Pode usar o plano? |
| Fiscal (NFSe) | Paralelo — não no núcleo do MVP |

---

## Relação com o legado

| Sistema | Papel durante a transição |
|---------|---------------------------|
| `sigoweb` | UI operacional; passa a consumir a API deste projeto |
| `sigo-laravel` | Continua com o que ainda não migrou; não é o destino deste domínio |
| Oracle | Fonte histórica / dual-run até cutover da cooperativa |
| `financeiro` (este repo) | Fonte da verdade de cobrança para cooperativas migradas |

Cutover por cooperativa exige interruptor claro: legado **não** gera/baixa título daquela coop em paralelo sem controle.

---

## O que não fazer (para não perder o foco)

- Reescrever remessa/baixa multi-banco de todas as cooperativas de uma vez
- Levar o nome/estrutura de “mensalidade” / “agregada” como conceito central
- Inferir renovação por “parcela 13”
- Misturar NFSe no MVP
- Criar fork por cooperativa em vez de configuração/adapters
- Começar pela cooperativa com mais exceções (Ilhéus etc.)

---

## Stack local

Ver [docs/ambiente.md](docs/ambiente.md) e o passo a passo em [docs/como-usar.md](docs/como-usar.md).

API local: http://localhost:8085

---

## Próximos passos

1. ~~Scaffold Laravel + Docker + MySQL~~
2. ~~Multi-tenant + Cliente Seridó~~
3. ~~Auth JWT Sigoweb (middleware + `/api/v1/me`)~~ — configurar `SIGOWEB_JWT_SECRET` no `.env`
4. ~~Migrations do domínio: contrato, parcela, cobrança, vínculo, elegibilidade~~
5. ~~Discovery Seridó (parcial)~~ — ver [docs/discovery-serido.md](docs/discovery-serido.md); Oracle/procedures ainda sob demanda
6. Plano de migração de dados + reconciliação
7. Adaptador Sicredi (boleto/CNAB) + PIX
8. Cutover `112` / flag `usa_financeiro_novo`

Infra já preparada: **Redis + filas + worker + scheduler** (ver [docs/filas-redis.md](docs/filas-redis.md)).

**Oracle:** quando precisar do fonte das procedures da Seridó, avisar — não conectar o Oracle como banco do Financeiro.

---

## Referências no legado (contexto)

- Mensalidade / baixa / remessa: `sigo-laravel/app/Services/Financeiro/`
- Rotas financeiras: `sigo-laravel/routes/api-financeiro.php`
- Legado beneficiário: `sigoweb/Psti/SigoWeb/Mensalidade.php`
- Manual cobrança PJ (parcialmente útil): `sigo-laravel/docs/manual-cobranca-pj.md`

Estas referências são para estudo e migração — **não** para copiar o modelo.
