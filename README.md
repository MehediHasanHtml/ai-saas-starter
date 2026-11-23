# AI SaaS Starter Kit

A production-shaped starter for AI SaaS products: **Next.js + Laravel + PostgreSQL (pgvector)**, with multi-provider AI, a RAG document chatbot, Free / Pro / Business plans on Stripe, multi-tenant teams, and a platform admin dashboard.

The demo product built on it is **Margin**: teams upload their documents, ask questions, and every answer cites the passage it came from.

| Area | What's included |
| --- | --- |
| **Full-stack** | Next.js 16 (App Router, TypeScript, Tailwind v4) talking to a Laravel 12 JSON API through a backend-for-frontend proxy. Docker Compose for the whole stack, GitHub Actions CI. |
| **AI integration** | One `ChatProvider` interface with OpenAI, Anthropic, Google Gemini, Ollama and a zero-config demo provider. Streaming over Server-Sent Events, token accounting, per-plan provider access. |
| **RAG** | Upload PDF, DOCX, Markdown, TXT, CSV, HTML or JSON, then extract, chunk, embed and store in pgvector (HNSW, cosine). Grounded prompts with numbered sources, and citations returned to the UI. |
| **SaaS architecture** | Organizations as tenants (shared schema, fail-closed global scope), roles, invitations, plan entitlements and quotas, Stripe Checkout and Billing Portal via Cashier, usage metering, platform admin. |

---

## Quick start

```bash
docker compose up --build
```

| URL | |
| --- | --- |
| http://localhost:3000 | Web app |
| http://localhost:8000/api | API |

Demo accounts (password `password`):

- `admin@example.com`: platform admin, owner of **Acme Inc** (Business plan)
- `demo@example.com`: member of Acme, owner of a Free workspace

No API keys are needed. The built-in **demo provider** answers by quoting your documents and uses hashed bag-of-words embeddings, so uploads, retrieval, streaming and citations all work offline. To use real models, set keys before starting:

```bash
export OPENAI_API_KEY=sk-...  ANTHROPIC_API_KEY=...  GEMINI_API_KEY=...
export AI_DEFAULT_PROVIDER=openai AI_EMBEDDING_PROVIDER=openai
docker compose up --build
```

> Changing the embedding provider or dimensions changes the vector space. Run `docker compose down -v` (fresh DB) or re-migrate and `php artisan rag:reindex`.

### Without Docker

```bash
# API (PHP 8.2+, PostgreSQL 16+ with pgvector, Redis optional)
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve            # :8000
php artisan queue:work       # document indexing (or set QUEUE_CONNECTION=sync)

# Web (Node 20+)
cd frontend
cp .env.example .env.local
npm install && npm run dev   # :3000
```

---

## Architecture

```
Browser ──► Next.js (BFF) ───────────────► Laravel API ──► PostgreSQL + pgvector
            /api/auth/*  token in httpOnly   Sanctum          organizations, documents,
            /api/backend/* adds Bearer +     ResolveTenant     chunks(vector), messages,
                           X-Organization-Id Entitlements      usage_records, subscriptions
            streams SSE through untouched    AiManager ─────► OpenAI / Anthropic / Gemini / Ollama
                                             Queue ─► ProcessDocument (extract → chunk → embed → store)
                                             Cashier ◄─ Stripe webhooks
```

### Multi-tenancy (`backend/app/Tenancy`)

- **Model**: single database, shared schema. Every tenant-owned table has `organization_id`. The `Organization` is both the tenant and the Stripe customer, so a team shares one plan.
- **Resolution**: `ResolveTenant` middleware reads `X-Organization-Id` (or the user's last org), verifies membership, rejects suspended orgs and binds the org to a scoped `TenantContext`. It is placed in the middleware priority list *before* rate limiting and route-model binding.
- **Isolation**: the `BelongsToTenant` trait adds a global scope that **fails closed**. With no tenant resolved, queries match nothing. New rows are stamped automatically, and writing a row for another tenant throws. Admin and system code opts out explicitly with `Model::withoutTenancy()`.
- **Roles**: `owner`, `admin`, `member` on the `organization_user` pivot, enforced with `tenant.role:owner,admin`.
- Tests cover cross-tenant 404s, non-member access, fail-closed scoping and role checks (`tests/Feature/TenancyTest.php`).

### Plans, quotas and billing (`backend/app/Billing`, `config/plans.php`)

Plans live in config, not in the database, so they version with the code:

| | Free | Pro ($19) | Business ($79) |
| --- | --- | --- | --- |
| AI messages / month | 100 | 2,000 | 20,000 |
| Documents | 5 | 100 | Unlimited |
| Max file / storage | 5 MB / 25 MB | 20 MB / 1 GB | 50 MB / 10 GB |
| Seats | 1 | 5 | 25 |
| Providers | Demo, OpenAI, Ollama | All | All |
| Requests / minute | 10 | 60 | 120 |

- `Organization::plan()` resolves to **admin override → active Stripe subscription → Free**.
- `Entitlements` is the single gate (`ensureCanSendMessage`, `ensureCanUploadDocument`, `ensureCanAddMember`, `ensureProviderAllowed`). Breaches return **HTTP 402** with `code: quota_exceeded`, which the UI turns into an upgrade prompt.
- The `ai` rate limiter is keyed by organization and scales with the plan.
- Stripe: Checkout for new subscriptions, in-place `swap()` for plan changes, cancel at period end for downgrades to Free, and the Billing Portal for payment details. Webhooks are handled by Cashier at `POST /stripe/webhook`.

### AI providers (`backend/app/AI`)

```php
$ai = app(AiManager::class);
$ai->provider('anthropic')->stream(new ChatRequest(model: 'claude-sonnet-5-5', messages: [...], system: '...'));
$ai->embedder()->embed(['text'], 'text-embedding-3-small', 1536);
```

| Provider | Chat | Stream | Embeddings | Notes |
| --- | :-: | :-: | :-: | --- |
| OpenAI | ✓ | ✓ | ✓ | Any OpenAI-compatible endpoint via `OPENAI_BASE_URL` (Groq, Together, vLLM, LM Studio) |
| Anthropic | ✓ | ✓ | | Messages API, system prompt and role alternation handled |
| Gemini | ✓ | ✓ | ✓ | `generateContent`, `streamGenerateContent?alt=sse`, `batchEmbedContents` |
| Ollama | ✓ | ✓ | ✓ | Self-hosted, NDJSON stream |
| Demo (`fake`) | ✓ | ✓ | ✓ | Deterministic, no network, used by tests |

To add a provider, implement `ChatProvider` (and optionally `EmbeddingProvider`), add a `createXxxDriver()` method to `AiManager` and an entry in `config/ai.php`. Model lists are env-configurable (`OPENAI_MODELS=...`) because model ids change often.

### RAG pipeline (`backend/app/Rag`, `app/Jobs/ProcessDocument.php`)

1. **Upload**: validated against plan limits, stored per organization, `202 Accepted`.
2. **Extract** (`TextExtractor`): text per PDF page (smalot/pdfparser), DOCX via ZipArchive, HTML stripped, encoding normalised.
3. **Chunk** (`Chunker`): recursive splitting (paragraphs, lines, sentences, words), about 1,200 characters with 200 overlap, page numbers kept for citations. Unit-tested.
4. **Embed**: batched through the platform embedding provider. Usage is metered.
5. **Store** (`PgVectorStore`): `vector(N)` column with an HNSW cosine index. Swap the `VectorStore` binding to move to Qdrant or Pinecone.
6. **Retrieve**: query embedding, top-k within the tenant (optionally limited to selected documents), then a minimum-similarity cut-off.
7. **Generate** (`PromptBuilder`, `ChatService`): sources go into the system prompt as numbered `<source>` blocks, with instructions to cite `[n]` and to treat source text as data rather than instructions. The citations the model actually used are saved with the message.

`POST /api/search` exposes retrieval directly. The Knowledge base page uses it for a "Test retrieval" panel.

### Streaming chat

`POST /api/conversations/{id}/messages` responds with Server-Sent Events:

```
event: start      data: {"user_message_id": 41}
event: citations  data: [{"index":1,"document_title":"Procurement policy","page":4,"snippet":"…"}]
event: delta      data: {"text":"Vendor contracts can be ended with "}
event: done       data: {…persisted assistant message with tokens and latency…}
event: error      data: {"message":"…"}
```

Quota and plan checks run **before** the stream opens, so failures are ordinary JSON 402/422 responses. Send `"stream": false` for a single JSON reply.

### Frontend (`frontend/src`)

- **BFF auth**: `/api/auth/[action]` swaps credentials for a Sanctum token and stores it in an **httpOnly cookie**. `/api/backend/[...path]` forwards requests with the token and selected organization and passes response bodies through as streams. Browser JavaScript never sees the token.
- `proxy.ts` (Next 16's replacement for middleware) does the optimistic redirect for signed-out users. The API remains the authority.
- Pages: landing with live pricing, sign in and register, invitation accept, Overview (usage meters, 30-day chart), Chat (model picker, knowledge-base toggle, streaming, sources in a margin column), Knowledge base (drag-and-drop upload, live indexing status, retrieval tester), Team (roles, invite links), Plan & billing, Settings (default model, org instructions, create org), and Admin (MRR, plan mix, provider traffic, orgs with plan comps and suspension, users).

---

## API reference

All tenant routes accept `X-Organization-Id`. Auth is `Authorization: Bearer <token>`.

| Method | Path | |
| --- | --- | --- |
| POST | `/api/auth/register`, `/api/auth/login`, `/api/auth/logout` | Token auth |
| GET | `/api/auth/me` | User, organizations and roles |
| GET/POST | `/api/organizations`, `POST /api/organizations/{id}/switch` | Memberships |
| GET/PATCH | `/api/organization` | Current org and settings (PATCH: owner or admin) |
| GET | `/api/usage`, `/api/models`, `/api/plans` | Meters and charts, provider catalogue, public plans |
| GET/POST/DELETE | `/api/documents`, `POST /api/documents/{id}/reprocess` | Knowledge base |
| POST | `/api/search` | Semantic search |
| REST | `/api/conversations` | Chats (private per user) |
| POST | `/api/conversations/{id}/messages` | Send, SSE or JSON |
| GET/PATCH/DELETE | `/api/members`, `/api/invitations`, `POST /api/invitations/{token}/accept` | Team |
| GET/POST | `/api/billing`, `/api/billing/checkout`, `/api/billing/portal` | Stripe |
| GET/PATCH | `/api/admin/stats`, `/usage`, `/organizations`, `/users` | Platform admin |

---

## Stripe setup

1. Create two recurring prices in Stripe (Pro, Business) and set `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_PRICE_PRO` and `STRIPE_PRICE_BUSINESS`.
2. Point a webhook at `https://your-api/stripe/webhook` with the Cashier events (`customer.subscription.*`, `customer.updated`, `invoice.payment_action_required`), and set `STRIPE_WEBHOOK_SECRET`.
3. Locally: `stripe listen --forward-to localhost:8000/stripe/webhook`.

Without Stripe the billing page explains what's missing, and platform admins can still assign plans from **Admin → Organizations**.

## Testing

```bash
docker compose up -d postgres
cd backend && php artisan test    # uses the `testing` database created by docker/postgres/init.sql
cd frontend && npm run lint && npm run build
```

Feature tests run the whole RAG flow against real pgvector using the demo provider: upload, chunk, embed, semantic search, cited answer over JSON and SSE, quotas, provider gating, tenancy isolation, invitations and admin actions.

## Going to production

- Run the API under **Laravel Octane** (FrankenPHP or Swoole) or php-fpm with nginx. `php artisan serve` with workers is for development. Keep `X-Accel-Buffering: no` (already sent) so nginx doesn't buffer SSE.
- Move uploads to S3 (`FILESYSTEM_DISK=s3`) and run `queue:work` under a supervisor, scaled for indexing load.
- Send invitation emails by adding a Mailable in `InvitationController@store`. The accept link is already generated.
- Ideas for next steps: per-tenant bring-your-own API keys, OCR for scanned PDFs, hybrid keyword and vector search, a reranker, metered overage billing with Stripe meters.

## Project layout

```
backend/                     Laravel 12 API
  app/AI/                    AiManager, contracts, DTOs, providers, SSE reader
  app/Rag/                   TextExtractor, Chunker, PgVectorStore, Retriever, PromptBuilder
  app/Tenancy/               TenantContext, TenantScope, BelongsToTenant
  app/Billing/               Plan, Entitlements
  app/Http/Controllers/Api   Tenant API      app/Http/Controllers/Admin   Platform admin
  config/ai.php, plans.php   Providers, RAG tuning, plan limits
  tests/                     Unit + feature tests (PostgreSQL + pgvector)
frontend/                    Next.js 16 app
  src/app/api/               BFF route handlers (auth, backend proxy)
  src/app/dashboard, admin   Product and admin UI
  src/components, src/lib    UI kit, chat, API client, session
docker-compose.yml           postgres(pgvector), redis, api, queue, scheduler, web
```

MIT licensed.
