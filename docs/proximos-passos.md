# Próximos passos (pausado em 22/07/2026)

Quando voltar, diga: **“relembra os próximos passos”** (este arquivo).

## Já feito (contexto)

- Domínio: contrato / parcela / cobrança / fatura PJ / elegibilidade
- API situação financeira: `GET /api/v1/financeiro?chave_sigoweb=`
- Remessa Sicredi CNAB 240 (SOLID, fila `bancario`, fontes no lugar da view)
- `Fun_GerarNumRegistroUnicred` portada + params em `clientes.config.bancario`
- Migrations aplicadas no MySQL local (`remessas`, `remessa_itens`, contador…)

## Fila imediata

1. **Retorno CNAB Sicredi (`.CRT`)**  
   Baixa automática → liquidar cobrança / marcar `enviado_remessa = 2` (registrado).  
   Pedir fonte Oracle de retorno/baixa se ainda não tiver (`tb_baixar_arquivo_banco` / procedure de retorno).

2. **Registro de boleto + PIX Sicredi**  
   Adaptador API do banco (além do arquivo remessa). Discovery: PIX em seguida ao boleto/CNAB.

3. **Endereço real do pagador**  
   Sync/campos do Sigoweb nos `contratantes` (hoje há fallback `pagador_padrao`).

4. **Validar DV com fonte `fun_calculodvmodulo11`**  
   Se o Oracle divergir do `CalculoDvModulo11`, ajustar só essa classe.

5. **Plano de migração Oracle → MySQL**  
   Dados Seridó (`112`) + reconciliação com `tb_mensalidade` / faturas.

6. **Integração UI Sigoweb**  
   Tela de remessa/financeiro apontando para API do app `financeiro` (abandonar geração no `sigo-laravel`).

7. **Cutover piloto**  
   `usa_financeiro_novo = true` na cooperativa `112` + `SIGOWEB_JWT_SECRET` alinhado.

## Fora do piloto (não priorizar agora)

- Remessa Bradesco / outros bancos (só registrar adapter quando precisar)
- Boleto avulso (`BA`) e cooperado (`DC`)
- Débito em conta (“remessa personalizada”)

## Docs úteis

- [remessa-cnab.md](remessa-cnab.md)
- [discovery-serido.md](discovery-serido.md)
- [filas-redis.md](filas-redis.md)
- [integracao-sigoweb.md](integracao-sigoweb.md)
