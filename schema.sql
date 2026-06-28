-- 0x79.one — PostgreSQL schema (for DB_DRIVER=postgres)
-- Run against your database, e.g.:  psql "$POSTGRES_DSN" -f schema.sql
-- The same table/column names are used by the Supabase (PostgREST) backend.

CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- for gen_random_uuid()

-- Registered user accounts (optional; guests can use the app without one).
CREATE TABLE IF NOT EXISTS app_users (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email          text UNIQUE NOT NULL,
    password_hash  text NOT NULL,
    api_key_hash   text,
    api_key_prefix text,
    created_at     timestamptz NOT NULL DEFAULT now()
);

-- Short links + hosted files (files are stored as a long_url pointing at storage).
CREATE TABLE IF NOT EXISTS urls (
    id              bigserial PRIMARY KEY,
    long_url        text NOT NULL,
    short_code      text UNIQUE NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    expires_at      timestamptz,
    click_count     integer NOT NULL DEFAULT 0,
    max_clicks      integer,
    password_hash   text,
    owner_user_id   uuid REFERENCES app_users(id) ON DELETE SET NULL,
    preview_enabled boolean NOT NULL DEFAULT false
);
CREATE INDEX IF NOT EXISTS urls_owner_idx ON urls(owner_user_id);

-- Text/code pastes.
CREATE TABLE IF NOT EXISTS pastes (
    id            bigserial PRIMARY KEY,
    paste_code    text UNIQUE NOT NULL,
    content       text NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    expires_at    timestamptz,
    view_count    integer NOT NULL DEFAULT 0,
    max_views     integer,
    password_hash text,
    owner_user_id uuid REFERENCES app_users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS pastes_owner_idx ON pastes(owner_user_id);

-- Music Promoter landing pages.
CREATE TABLE IF NOT EXISTS music_promos (
    id            bigserial PRIMARY KEY,
    music_code    text UNIQUE NOT NULL,
    title         text NOT NULL,
    artist        text,
    cover_url     text,
    banner_url    text,
    links         jsonb NOT NULL DEFAULT '[]'::jsonb,
    created_at    timestamptz NOT NULL DEFAULT now(),
    expires_at    timestamptz,
    view_count    integer NOT NULL DEFAULT 0,
    owner_user_id uuid REFERENCES app_users(id) ON DELETE SET NULL
);

-- Abuse reports.
CREATE TABLE IF NOT EXISTS abuse_reports (
    id            bigserial PRIMARY KEY,
    reported_link text NOT NULL,
    reason        text,
    status        text DEFAULT 'open',
    created_at    timestamptz NOT NULL DEFAULT now()
);

-- Per-click analytics events (optional; logging fails silently if absent).
CREATE TABLE IF NOT EXISTS link_clicks (
    id            bigserial PRIMARY KEY,
    short_code    text NOT NULL,
    clicked_at    timestamptz NOT NULL DEFAULT now(),
    referrer_host text,
    device        text,   -- mobile | desktop | bot | other
    country       text    -- 2-letter ISO (e.g. from Cloudflare CF-IPCountry)
);
CREATE INDEX IF NOT EXISTS link_clicks_code_idx ON link_clicks(short_code, clicked_at DESC);

-- RSS / blog posts.
CREATE TABLE IF NOT EXISTS posts (
    id          bigserial PRIMARY KEY,
    title       text NOT NULL,
    description text,
    image       text,
    pub_date    timestamptz NOT NULL DEFAULT now()
);
