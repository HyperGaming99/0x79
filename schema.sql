-- 0x79.one — PostgreSQL schema (for DB_DRIVER=postgres)
-- Run against your database, e.g.:  psql "$POSTGRES_DSN" -f schema.sql
-- The same table/column names are used by the Supabase (PostgREST) backend.

CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- for gen_random_uuid()

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
    preview_enabled boolean NOT NULL DEFAULT false
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

-- Privacy-friendly monthly unique visitors for the public landing page.
-- No raw IP or User-Agent is stored. The application writes only a salted,
-- month-specific hash and keeps one row per browser/network fingerprint.
CREATE TABLE IF NOT EXISTS site_visits (
    id               bigserial PRIMARY KEY,
    visitor_hash     char(64) NOT NULL,
    visit_month      date NOT NULL,
    first_visited_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (visitor_hash, visit_month)
);
CREATE INDEX IF NOT EXISTS site_visits_month_idx ON site_visits(visit_month, first_visited_at DESC);

-- Aggregate counts imported from a previous analytics provider. Keeping these
-- separately avoids creating fake URL and click-event records just to preserve
-- historical dashboard totals.
CREATE TABLE IF NOT EXISTS analytics_monthly_imports (
    month       date PRIMARY KEY,
    visitors    bigint NOT NULL DEFAULT 0 CHECK (visitors >= 0),
    clicks      bigint NOT NULL DEFAULT 0 CHECK (clicks >= 0),
    links       bigint NOT NULL DEFAULT 0 CHECK (links >= 0),
    updated_at  timestamptz NOT NULL DEFAULT now()
);
-- On Supabase, keep hashes inaccessible to browser/anon clients. Plain
-- PostgreSQL installations retain their normal application-role behaviour.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'service_role') THEN
        ALTER TABLE site_visits ENABLE ROW LEVEL SECURITY;
        ALTER TABLE analytics_monthly_imports ENABLE ROW LEVEL SECURITY;
    END IF;
END
$$;
