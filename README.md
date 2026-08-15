# 0x79

An independent, self-hosted URL shortener written in plain PHP.

[![Release](https://img.shields.io/github/v/release/HyperGaming99/0x79?style=flat-square&color=b8ff31&label=release)](https://github.com/HyperGaming99/0x79/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Views](https://hits.sh/github.com/HyperGaming99/0x79.svg?style=flat-square&label=views&color=b8ff31)](https://hits.sh/github.com/HyperGaming99/0x79/)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-b8ff31?style=flat-square)](LICENSE)

**Live:** [0x79.one](https://0x79.one)

## Features

A single-purpose URL shortener: paste a link, get a short one back. No accounts, no login.

| Area | Included |
| --- | --- |
| URL shortener | Anonymous creation, branded QR codes, optional emoji output |
| Platform | Anonymous API and admin dashboard |

Links created on the homepage never expire. The API additionally accepts `password`, `expires_at`, `max_clicks` and `custom_code`, and the admin dashboard can edit any of these on an existing link.

## Requirements

- PHP 8 or newer
- Supabase or PostgreSQL
- Supabase Storage or an S3-compatible service

There is no framework or build step. The frontend is server-rendered HTML with Tailwind loaded from its CDN.

## Quick start

Clone the repository:

```sh
git clone https://github.com/HyperGaming99/0x79.git
cd 0x79
```

Copy the example configuration:

```sh
cp .env.sample .env
```

At minimum, configure an admin key and one database/storage backend:

```env
ADMIN_API_KEY=change-me

DB_DRIVER=supabase
STORAGE_DRIVER=supabase

SUPABASE_URL=https://your-project.supabase.co
SUPABASE_KEY=your-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
```

Start the development server:

```sh
php -S localhost:8000 index.php
```

Open [localhost:8000](http://localhost:8000). There is no framework, package installation or frontend build step.

## Configuration

The shortener can be switched off entirely (the homepage and `/api` return `404`):

```env
TOOL_SHORTENER_ENABLED=true
```

For PostgreSQL, set `DB_DRIVER=postgres` and configure `POSTGRES_DSN` or the individual `POSTGRES_*` values. Run `schema.sql` to create the tables.

For S3 or MinIO, set `STORAGE_DRIVER=s3` and provide:

```env
S3_ENDPOINT=http://localhost:9000
S3_REGION=us-east-1
S3_BUCKET=files
S3_ACCESS_KEY=your-key
S3_SECRET_KEY=your-secret
S3_USE_PATH_STYLE=true
```

The storage bucket must allow public reads.

## Docker

Create the `.env` file described above, then run:

```sh
docker compose up --build
```

The app will be available at [localhost:8080](http://localhost:8080). Set `APP_PORT` to use a different host port:

```sh
APP_PORT=9000 docker compose up --build
```

Prebuilt images are published to GitHub Container Registry on every push to `main`:

```sh
docker pull ghcr.io/hypergaming99/0x79:latest
```

## API

No account or key required.

| Endpoint | Purpose |
| --- | --- |
| `POST /api` | Create a short link. JSON or form body: `long_url` (required), `domain`, `password`, `expires_at`, `max_clicks`, `custom_code`, `preview_enabled` |
| `GET /api?code=…` | Look up a short link |

## Main routes

| Route | Page |
| --- | --- |
| `/` | The shortener |
| `/admin` | Administration dashboard (password login, no accounts) |

## Star history

[![0x79 Star History](assets/star-history.svg)](https://github.com/HyperGaming99/0x79/stargazers)

The chart is generated inside this repository and updated daily by GitHub Actions. No public access token is embedded in the README.

## Security

Do not commit `.env`. Uploads are type-checked, SVG uploads are blocked, and short-link targets are checked against private and blocked hosts.

## License and attribution

0x79 is licensed under the [BSD 3-Clause License](LICENSE). You may use, modify and redistribute the software, including commercially, as long as the copyright notice and license text remain included.

The attribution requirement is fulfilled by retaining the copyright notice and complete license text in source distributions or accompanying documentation. A visible project credit can use:

```text
0x79 by HyperGaming99 — https://github.com/HyperGaming99/0x79
```
