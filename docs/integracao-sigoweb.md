# Integração Sigoweb ↔ Financeiro (SSO)

## Objetivo

O usuário que já está logado no **Sigoweb** deve acessar telas/APIs do **Financeiro** **sem novo login**.

## Como o Sigoweb autentica hoje

1. Login no Sigoweb dispara autenticação no `sigo-laravel` (JWT via `tymon/jwt-auth`).
2. O token fica no `localStorage` do browser (`token`).
3. Chamadas Vue/JS enviam `Authorization: Bearer <token>` para `/sigo-laravel/public/api/v1/...`.

O Financeiro deve seguir o **mesmo padrão**: aceitar o JWT já emitido (ou um token derivado), sem tela de login própria no fluxo operacional.

## Estratégia escolhida (MVP)

| Item | Decisão |
|------|---------|
| Mecanismo | **JWT do Sigoweb / sigo-laravel** |
| Login próprio no Financeiro | Não no fluxo operacional (pode existir login admin interno depois) |
| Como o front chama | `Authorization: Bearer <mesmo token do localStorage>` |
| Como sabemos a cooperativa | Claim/`parametros` com `par_coop` (ex.: `112`) → resolve o **Cliente** no Financeiro |
| Segredo | Variável `SIGOWEB_JWT_SECRET` (mesmo valor do `JWT_SECRET` do sigo-laravel no ambiente daquela instalação) |

### Fluxo

```text
Usuário loga no Sigoweb
        │
        ▼
sigo-laravel emite JWT (claims: login, tipo_acesso, par_coop, ...)
        │
        ▼
localStorage.token
        │
        ├─► APIs sigo-laravel (legado)
        └─► APIs financeiro (novo)  ── valida JWT ── resolve Cliente ── escopo multi-tenant
```

### Telas

Opções (podem coexistir):

1. **Páginas no Sigoweb** que chamam a API do Financeiro (padrão atual das telas Vue).
2. **UI no próprio Financeiro** aberta em rota/embed, recebendo o token (query one-time exchange ou header já disponível no SPA).

No MVP, preferir (1): menos atrito. UI nativa no Financeiro entra quando fizer sentido.

## O que o Financeiro valida no token

Mínimo:

- Assinatura e expiração do JWT
- `par_coop` (ou claim equivalente) presente
- Cliente ativo no cadastro local com `chave_sigoweb` / `codigo_cooperativa` correspondente
- Usuário identificado (`login` / `sub`) para auditoria

Não replicar senha nem cadastro completo de usuários do Sigoweb no MVP — o token é a prova de autenticação.

## O que ainda não fazer no MVP

- SSO SAML/OAuth completo com IdP separado
- Login social
- Duplicar tabela de usuários/perfis do Sigoweb
- Trocar o JWT do legado agora

## Próximos refinamentos (depois do piloto)

- Token de troca de curta duração (`POST /auth/exchange`) se não quisermos compartilhar o mesmo secret para sempre
- Mapa de permissões Financeiro (quem pode baixar, emitir consolidada, etc.)
- Logout/invalidação alinhada

## URL do Financeiro no Sigoweb

No `.env` do Sigoweb:

```env
FINANCEIRO_URL=http://localhost:8085
FINANCEIRO_URL_INTERNAL=http://host.docker.internal:8085
```

| Variável | Uso |
|----------|-----|
| `FINANCEIRO_URL` | Browser / JS (`localStorage.url_api_financeiro` no login) |
| `FINANCEIRO_URL_INTERNAL` | PHP no container php74 → host (não compartilham rede Docker) |

Em produção: ambos apontam para a URL pública do Financeiro (outro servidor). `INTERNAL` pode ficar vazio.

PHP: `Banco::getUrlFinanceiroApi()` / `getUrlFinanceiroApiForJavaScript()`.

JS:
```js
const base = localStorage.getItem('url_api_financeiro'); // ex. http://localhost:8085
fetch(`${base}/api/v1/financeiro?chave_sigoweb=...`, {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
});
```

Redes Docker: `financeiro_financeiro` ≠ `php74_server` — correto para espelhar produção.

```http
GET /api/v1/financeiro?chave_sigoweb={codigo_benef_ou_empresa}
Authorization: Bearer {jwt}
```

Retorna: contratante, elegibilidade (pode atender?), contratos, parcelas em aberto/vencidas, faturas PJ em aberto e `saldo_em_aberto`.

## Remessa CNAB

```http
POST /api/v1/remessas
Authorization: Bearer {jwt}

{ "vencimento_inicial": "2026-04-01", "vencimento_final": "2026-04-30" }
```

Resposta `202`: remessa enfileirada. Download: `GET /api/v1/remessas/{id}/download`.

Detalhes: [remessa-cnab.md](remessa-cnab.md).

Só elegibilidade (mais leve): `GET /api/v1/elegibilidade?chave_sigoweb=...`

## Variáveis de ambiente

```env
SIGOWEB_JWT_SECRET=
SIGOWEB_JWT_ALGO=HS256
FINANCEIRO_URL=http://localhost:8085
```

## Nota de segurança

O secret JWT é sensível. Em produção, cada instalação (Seridó, etc.) usa o secret daquele ambiente. Não versionar secrets no Git.
