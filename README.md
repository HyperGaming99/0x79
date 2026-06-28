# 0x79.one

A minimal, self-hosted **URL shortener, file/image host, and paste host** — written in plain PHP, backed by [Supabase](https://supabase.com) (Postgres + Storage). No framework, no build step.

Live: **https://0x79.one**

---

## Features

- **URL shortener** — custom aliases, optional password, expiry date, click limits (burn-after-N), and link-preview pages.
- **File & image host** — drag & drop upload (JPG · PNG · WEBP · GIF · AVIF · ZIP), served through a proxy. SVG is blocked for safety.
- **Paste host** — share text/code via a short link, with a raw view.
- **Secure share & metadata stripper** — extra privacy tools.
- **Music landing pages** — one link, all streaming platforms.
- **Multi-language UI** — German & English.
- **JSON API** — see `/api/docs`.
- **Admin dashboard** — link management & CSV export.

---

## ⚠️ Hinweis: Gast-Uploads laufen nach 14 Tagen ab

> Links und Dateien, die **ohne Account** (als Gast) erstellt werden, laufen **automatisch nach 14 Tagen ab** — der Kurzlink funktioniert danach nicht mehr.
>
> Wenn du **dauerhafte** Links willst, lege einen Account an (`/register`) — dann laufen deine Links, Dateien und Pastes nicht automatisch ab, und du bekommst einen API-Key.

> **Note (EN):** Links and files created **without an account** (as a guest) **expire automatically after 14 days**. Register an account for permanent links and an API key.

---

## Tech stack

| Layer    | Used                                  |
|----------|---------------------------------------|
| Backend  | PHP 8.x (no framework)                |
| Database | Supabase / Postgres (via REST)        |
| Storage  | Supabase Storage (public bucket)      |
| Frontend | Server-rendered HTML + Tailwind (CDN) |

### Layout

```
index.php         Front controller / router
config.php        Config + language metadata (reads from .env)
helpers.php       Core helpers (validation, upload, link logic, i18n)
supabase.php      Supabase REST/Storage calls
views.php         Page render functions
rss.php           RSS feed
generate_post.js  Post generation helper
lang/             de.json, en.json translations
```

---

## Setup

1. **Clone**
   ```bash
   git clone https://github.com/HyperGaming99/0x79.one.git
   cd 0x79.one
   ```

2. **Configure** — create a `.env` (this file is gitignored and must **never** be committed):
   ```env
   SUPABASE_URL=https://<your-project>.supabase.co
   SUPABASE_KEY=<service-or-anon-key>
   ADMIN_API_KEY=<your-admin-key>
   GEMINI_API_KEY=<optional, for AI features>
   ```

3. **Supabase** — create the `urls` and `pastes` tables, plus a public Storage bucket named `images`.

4. **Run**
   ```bash
   php -S localhost:8000
   ```
   Then open http://localhost:8000

---

## Security notes

- **Never commit `.env`** — it holds your Supabase and admin keys. It is excluded via `.gitignore`.
- Uploads are type-checked; SVG is rejected (it can carry scripts).
- Shortener targets are validated against private IPs / blocked hosts (SSRF protection).

---

## Contributing

Du hast eine Idee für 0x79.one? Dann öffne gerne ein [Issue](https://github.com/HyperGaming99/0x79.one/issues).
