# 0x79

A self-hosted URL shortener, file host, and paste service written in plain PHP.

Live at [0x79.one](https://0x79.one).

## Features

- Short links with aliases, passwords, expiry dates, click limits, and QR codes
- File and image uploads
- Text and encrypted pastes
- Music landing pages
- Local EXIF metadata removal
- User accounts, analytics, API keys, and an admin dashboard
- German and English interface
- Light and dark themes

Guest content expires after 14 days. Content created with an account stays available unless an expiry date is set.

## Requirements

- PHP 8 or newer
- Supabase or PostgreSQL
- Supabase Storage or an S3-compatible service

There is no framework or build step. The frontend is server-rendered HTML with Tailwind loaded from its CDN.

## Setup

Clone the repository:

```sh
git clone https://github.com/HyperGaming99/0x79.git
cd 0x79
```

Create a `.env` file:

```env
ADMIN_API_KEY=change-me

DB_DRIVER=supabase
STORAGE_DRIVER=supabase

SUPABASE_URL=https://your-project.supabase.co
SUPABASE_KEY=your-key
SUPABASE_SERVICE_ROLE_KEY=your-service-role-key
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

Start the development server:

```sh
php -S localhost:8000 index.php
```

Open [localhost:8000](http://localhost:8000).

## API

API documentation is available at `/api/docs`. Create an account to generate an API key.

## Security

Do not commit `.env`. Uploads are type-checked, SVG uploads are blocked, and short-link targets are checked against private and blocked hosts.
