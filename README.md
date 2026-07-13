# 0x79

A self-hosted URL shortener, file host, and paste service written in plain PHP.

Live at [0x79.one](https://0x79.one).

## Features

- Short links with aliases, passwords, expiry dates, click limits, and QR codes
- File and image uploads
- Text and encrypted pastes
- Music landing pages
- Discord presence with REST and WebSocket APIs
- Minecraft Java server status with MOTD, players, version, icon, and ping
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

### Discord Presence bot

The optional `/discord` tool uses your own Discord bot and only sees presence for members of the configured server. Create a bot in the Discord Developer Portal, add it to the server, and enable both **Presence Intent** and **Server Members Intent** under **Privileged Gateway Intents**. The worker uses the same member/presence snapshot flow as Lanyard. Then configure:

```env
TOOL_DISCORD_ENABLED=true
DISCORD_BOT_TOKEN=your-bot-token
DISCORD_GUILD_ID=your-server-id
DISCORD_PRESENCE_CACHE=.discord-presence.json
DISCORD_WS_ENABLED=true
DISCORD_WS_PORT=8090
DISCORD_WS_PUBLIC_URL=wss://your-domain.example/discord/socket
```

The default cache is stored inside the shared project directory. This is important when the worker runs on the host while the PHP website runs inside a container, because their `/tmp` directories are separate.

Never use a Discord user token. Docker starts the Gateway worker automatically. For local development without Docker, run the worker and web server in separate terminals:

```sh
php discord-worker.php
php discord-socket.php
php -S localhost:8000 index.php
```

The socket server speaks a Lanyard-style subscribe protocol on port `8090`. Put it behind a WebSocket-capable reverse proxy and set `DISCORD_WS_PUBLIC_URL` to the public `wss://` address. `subscribe_to_all` stays disabled by default; enable it explicitly with `DISCORD_WS_ALLOW_SUBSCRIBE_ALL=true` only if exposing every cached member is intended.

### Minecraft server status

The optional `/minecraft` tool queries public Minecraft Java servers directly and follows `_minecraft._tcp` SRV records. Private and reserved network targets are rejected.

```env
TOOL_MINECRAFT_ENABLED=true
MINECRAFT_QUERY_TIMEOUT=4
```

The JSON endpoint is available at `/api/minecraft?server=play.example.net`.

### Tool dashboard and status

- `/tools` provides a searchable, categorized directory of every enabled utility.
- `/status` shows the current application, database, storage, Discord worker and tool states and refreshes every 30 seconds.

Start the development server:

```sh
php -S localhost:8000 index.php
```

Open [localhost:8000](http://localhost:8000).

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

API documentation is available at `/api/docs`. Create an account to generate an API key.

## Security

Do not commit `.env`. Uploads are type-checked, SVG uploads are blocked, and short-link targets are checked against private and blocked hosts.
