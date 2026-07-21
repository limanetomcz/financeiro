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

## Variáveis de ambiente

```env
SIGOWEB_JWT_SECRET=
SIGOWEB_JWT_ALGO=HS256
FINANCEIRO_URL=http://localhost:8085
```

## Nota de segurança

O secret JWT é sensível. Em produção, cada instalação (Seridó, etc.) usa o secret daquele ambiente. Não versionar secrets no Git.
