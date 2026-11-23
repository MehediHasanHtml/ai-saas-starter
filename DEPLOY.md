# Deploying: frontend on Vercel, API on cPanel

```
Browser ──▶ Vercel (Next.js, yourdomain.com) ──▶ cPanel (Laravel API, api.yourdomain.com) ──▶ PostgreSQL + pgvector
```

The browser only talks to Vercel. Vercel forwards API calls server-to-server,
so there is no CORS setup and the login token stays in an httpOnly cookie.

Replace `yourdomain.com` and `USER` (your cPanel username) everywhere below.

---

## 1. Build the API bundle (on your PC)

Docker must be running. In the project folder:

```bash
bash deploy/build-cpanel.sh
```

This creates **`deploy/dist/margin-api-cpanel.zip`**: the API with its PHP
packages already installed, so the server needs no Composer. It contains no
`.env` and no secrets.

## 2. Prepare cPanel

| Where in cPanel | Set |
|---|---|
| **MultiPHP Manager** | PHP **8.3** for `api.yourdomain.com` |
| **Select PHP Version → Extensions** | `pdo_pgsql`, `pgsql`, `zip`, `intl`, `bcmath`, `mbstring`, `fileinfo`, `curl`, `openssl` |
| **MultiPHP INI Editor** | `upload_max_filesize = 25M`, `post_max_size = 30M`, `max_execution_time = 300`, `memory_limit = 256M` |
| **PostgreSQL Databases** | a database + user with all privileges, and pgvector enabled in that database (`CREATE EXTENSION vector;`) |

## 3. Create the API subdomain

**Domains → Create A New Domain**: `api.yourdomain.com`, untick "share document
root", and set the document root to:

```
margin-api/public
```

Only `public/` is visible on the web; code and `.env` stay private.

> Can't change the document root? Extract the zip into the folder the domain
> already uses. The included root `.htaccess` forwards everything to `public/`
> and blocks `.env`, logs and source files. (Tested.)

## 4. Upload and configure

1. **File Manager** → create `/home/USER/margin-api` → upload the zip into it →
   right-click → **Extract**. Then delete the zip.
2. **Settings (top right) → Show Hidden Files**, so `.env` and `.htaccess` are visible.
3. Copy `.env.production.example` to **`.env`** and edit every `CHANGE ME`:
   database name/user/password, `APP_URL=https://api.yourdomain.com`,
   `FRONTEND_URL=https://yourdomain.com`, mail settings, and a **new** Gemini key.
4. **Advanced → Terminal**, then:

```bash
cd ~/margin-api
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Do **not** run `db:seed`. Demo users have the password `password`; in
production the seeder refuses to create them anyway.

> No Terminal on your plan? Add each command as a one-off **Cron Job**
> (`cd /home/USER/margin-api && php artisan migrate --force`), wait a minute,
> then delete the cron job.

## 5. Cron jobs (replace Redis and the queue worker)

**Cron Jobs → Add New Cron Job**, "Once Per Minute" (`* * * * *`), two entries:

```bash
cd /home/USER/margin-api && php artisan queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=300 >> /dev/null 2>&1
```
```bash
cd /home/USER/margin-api && php artisan schedule:run >> /dev/null 2>&1
```

The first processes uploaded documents (they show **Ready** within about a
minute). The second runs clean-up tasks.

If `php` on your server is not 8.3, use the full path, usually
`/opt/cpanel/ea-php83/root/usr/bin/php`.

## 6. HTTPS and a quick check

**SSL/TLS Status → Run AutoSSL** for `api.yourdomain.com`, then open:

- `https://api.yourdomain.com/up` → a "Application up" page
- `https://api.yourdomain.com/.env` → **403 Forbidden** (must not show your settings)

## 7. Frontend on Vercel

1. Push the project to GitHub. `.env` files are git-ignored. Check that GitHub
   shows no `.env` file before going further.
2. **Vercel → Add New → Project →** import the repo.
3. **Root Directory: `frontend`** (Framework: Next.js is detected).
4. **Environment Variables:**

   | Name | Value |
   |---|---|
   | `API_URL` | `https://api.yourdomain.com` |
   | `NEXT_PUBLIC_APP_NAME` | `Margin` |

   Do not add `NEXT_PUBLIC_SHOW_DEMO_LOGIN`: it prints demo passwords on the login page.
5. **Deploy**, then **Settings → Domains** → add `yourdomain.com`.

`frontend/vercel.json` lets the chat route stream for up to 300 seconds.

## 8. Create your admin account

1. Register on `https://yourdomain.com/register`.
2. In the cPanel Terminal:

```bash
cd ~/margin-api && php artisan admin:grant you@yourdomain.com
```

Sign out and in again: **Platform admin** appears in the sidebar.

## 9. Final test

- Sign in, upload a small PDF or `.md` file → **Ready** within ~1 minute.
- Ask a question about it in Chat with **Use knowledge base** on → the answer
  streams in and cites the file.

---

## Plans and AI providers

New workspaces start on the **Free** plan (100 messages a month), which may
use the providers in `PLAN_FREE_PROVIDERS`: by default the demo model,
**Gemini**, OpenAI and Ollama (only those with a key are offered). To make
Gemini paid-only, remove it:

```
PLAN_FREE_PROVIDERS=fake,openai,ollama
```

then `php artisan config:cache`. Pro and Business always get every configured provider.

## Updating later

```bash
bash deploy/build-cpanel.sh
```

Upload the new zip to `~/margin-api` and **Extract** (overwrite). Your `.env`
and uploaded files are kept: they are not in the zip. Then:

```bash
cd ~/margin-api && php artisan migrate --force && php artisan config:cache && php artisan route:cache
```

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| 500 error | Read the newest `storage/logs/laravel-*.log`. Usually wrong DB details in `.env`. After any `.env` change run `php artisan config:cache`. |
| "could not find driver" | `pdo_pgsql` not enabled (step 2). |
| "type vector does not exist" | pgvector not enabled in this database: `CREATE EXTENSION vector;` |
| Signed in, but every page says Unauthenticated | `public/.htaccess` is missing (hidden file, not uploaded). It passes the login token to PHP. |
| Documents stay "Pending" | The queue cron job isn't running or uses the wrong PHP path (step 5). |
| Chat answer appears all at once instead of typing | The host is buffering or compressing the stream. It still works. Ask the host to disable gzip/buffering for `/api/conversations/*/messages`. |
| Vercel shows "The API is unreachable" | `API_URL` is wrong, or the API's SSL certificate isn't issued yet. Redeploy after fixing env vars. |
| "not included in the Free plan" | See *Plans and AI providers* above. |
