-- Veckopeng database schema
-- PostgreSQL 14+

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS children (
    id            SERIAL PRIMARY KEY,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name          VARCHAR(100) NOT NULL,
    avatar_color  VARCHAR(7) DEFAULT '#6366f1',
    weekly_amount NUMERIC(10,2) DEFAULT 50.00 NOT NULL,
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_children_user_id ON children(user_id);

CREATE TABLE IF NOT EXISTS requirements (
    id         SERIAL PRIMARY KEY,
    child_id   INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    name       VARCHAR(200) NOT NULL,
    active     BOOLEAN DEFAULT true NOT NULL,
    sort_order INTEGER DEFAULT 0 NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_requirements_child_id ON requirements(child_id);

-- Migration: weekly requirements (safe to run multiple times)
ALTER TABLE requirements ADD COLUMN IF NOT EXISTS frequency VARCHAR(10) DEFAULT 'daily' NOT NULL;
ALTER TABLE children    ADD COLUMN IF NOT EXISTS swish_number VARCHAR(20);
ALTER TABLE requirements ADD COLUMN IF NOT EXISTS type VARCHAR(20) DEFAULT 'checkbox' NOT NULL;
ALTER TABLE requirements ADD COLUMN IF NOT EXISTS weekly_target_minutes INTEGER;
ALTER TABLE daily_logs   ADD COLUMN IF NOT EXISTS minutes INTEGER;

-- Child account support
ALTER TABLE children    ADD COLUMN IF NOT EXISTS child_can_self_report  BOOLEAN DEFAULT false NOT NULL;
ALTER TABLE family_members ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 0 NOT NULL;
ALTER TABLE weekly_summaries ADD COLUMN IF NOT EXISTS paid_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE children    ADD COLUMN IF NOT EXISTS child_can_self_adjust BOOLEAN DEFAULT false NOT NULL;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'parent' NOT NULL;
DO $$ BEGIN
    ALTER TABLE family_members DROP CONSTRAINT family_members_role_check;
EXCEPTION WHEN OTHERS THEN NULL; END $$;
ALTER TABLE family_members ADD CONSTRAINT family_members_role_check
    CHECK (role IN ('owner', 'parent', 'child'));

-- Migrate to family-level requirements (user_id replaces child_id as grouping key)
ALTER TABLE requirements    ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE deduction_types ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
UPDATE requirements    r SET user_id = c.user_id FROM children c WHERE c.id = r.child_id AND r.user_id IS NULL;
UPDATE deduction_types d SET user_id = c.user_id FROM children c WHERE c.id = d.child_id AND d.user_id IS NULL;
ALTER TABLE requirements    ALTER COLUMN child_id DROP NOT NULL;
ALTER TABLE deduction_types ALTER COLUMN child_id DROP NOT NULL;

CREATE TABLE IF NOT EXISTS deduction_types (
    id         SERIAL PRIMARY KEY,
    child_id   INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    name       VARCHAR(200) NOT NULL,
    amount     NUMERIC(10,2) NOT NULL,
    active     BOOLEAN DEFAULT true NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_deduction_types_child_id ON deduction_types(child_id);

CREATE TABLE IF NOT EXISTS daily_logs (
    id             SERIAL PRIMARY KEY,
    child_id       INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    requirement_id INTEGER NOT NULL REFERENCES requirements(id) ON DELETE CASCADE,
    log_date       DATE NOT NULL,
    completed      BOOLEAN DEFAULT false NOT NULL,
    created_at     TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (child_id, requirement_id, log_date)
);

CREATE INDEX IF NOT EXISTS idx_daily_logs_child_date ON daily_logs(child_id, log_date);

CREATE TABLE IF NOT EXISTS adjustments (
    id                SERIAL PRIMARY KEY,
    child_id          INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    deduction_type_id INTEGER REFERENCES deduction_types(id) ON DELETE SET NULL,
    amount            NUMERIC(10,2) NOT NULL,
    description       VARCHAR(200) NOT NULL,
    log_date          DATE NOT NULL,
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_adjustments_child_date ON adjustments(child_id, log_date);

CREATE TABLE IF NOT EXISTS weekly_summaries (
    id                     SERIAL PRIMARY KEY,
    child_id               INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    week_start             DATE NOT NULL,
    week_end               DATE NOT NULL,
    base_amount            NUMERIC(10,2) NOT NULL,
    total_adjustments      NUMERIC(10,2) DEFAULT 0 NOT NULL,
    final_amount           NUMERIC(10,2) NOT NULL,
    requirements_completed INTEGER DEFAULT 0 NOT NULL,
    requirements_total     INTEGER DEFAULT 0 NOT NULL,
    status                 VARCHAR(20) DEFAULT 'pending' NOT NULL
                               CHECK (status IN ('pending','paid','sent','owed')),
    notes                  TEXT,
    generated_at           TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (child_id, week_start)
);

CREATE INDEX IF NOT EXISTS idx_weekly_summaries_child ON weekly_summaries(child_id, week_start DESC);

-- Family sharing
CREATE TABLE IF NOT EXISTS family_members (
    id         SERIAL PRIMARY KEY,
    child_id   INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role       VARCHAR(20) DEFAULT 'parent' NOT NULL CHECK (role IN ('owner', 'parent')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(child_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_family_members_user ON family_members(user_id);
CREATE INDEX IF NOT EXISTS idx_family_members_child ON family_members(child_id);

-- Migrate existing owners
INSERT INTO family_members (child_id, user_id, role)
SELECT id, user_id, 'owner' FROM children
ON CONFLICT (child_id, user_id) DO NOTHING;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_remember_tokens_hash ON remember_tokens(token_hash);

CREATE TABLE IF NOT EXISTS child_requirement_exclusions (
    child_id       INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    requirement_id INTEGER NOT NULL REFERENCES requirements(id) ON DELETE CASCADE,
    PRIMARY KEY (child_id, requirement_id)
);

-- Familjeregler: vad som händer med bas-veckopengen när krav missas.
-- Lagras på familjens ägarkonto (samma nyckel som kraven, users.id = children.user_id).
-- req_policy: none = basen betalas alltid | all = 0 kr i bas om något krav missas
--             percent = req_penalty % av basen dras per missat krav
--             fixed   = req_penalty kr dras per missat krav
ALTER TABLE users ADD COLUMN IF NOT EXISTS req_policy  VARCHAR(10)   DEFAULT 'none' NOT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS req_penalty NUMERIC(10,2) DEFAULT 0 NOT NULL;

-- Skärmtid som valuta:
--   children.screen_budget_minutes: veckopott i minuter (NULL = skärmtid avstängd för barnet)
--   users.screen_overage_fee: kr som dras per påbörjad 10 min över potten (familjeregel, 0 = gratis)
--   adjustments/deduction_types.unit: 'kr' (pengar) eller 'min' (skärmtid, fyller på/minskar potten)
--   screen_logs: använd skärmtid per barn och dag
ALTER TABLE children ADD COLUMN IF NOT EXISTS screen_budget_minutes INTEGER;
ALTER TABLE users    ADD COLUMN IF NOT EXISTS screen_overage_fee NUMERIC(10,2) DEFAULT 0 NOT NULL;
ALTER TABLE adjustments     ADD COLUMN IF NOT EXISTS unit VARCHAR(3) DEFAULT 'kr' NOT NULL;
ALTER TABLE deduction_types ADD COLUMN IF NOT EXISTS unit VARCHAR(3) DEFAULT 'kr' NOT NULL;

CREATE TABLE IF NOT EXISTS screen_logs (
    id       SERIAL PRIMARY KEY,
    child_id INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    log_date DATE NOT NULL,
    minutes  INTEGER DEFAULT 0 NOT NULL,
    UNIQUE (child_id, log_date)
);

CREATE INDEX IF NOT EXISTS idx_screen_logs_child_date ON screen_logs(child_id, log_date);

-- Appinställningar (nyckel/värde) - bl.a. standardvärden för nya konton
CREATE TABLE IF NOT EXISTS app_settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

INSERT INTO app_settings (key, value) VALUES
    ('default_weekly_amount', '50'),
    ('default_screen_budget', '600')
ON CONFLICT (key) DO NOTHING;

-- Standardkrav och standardknappar som sås in när ett nytt konto skapar sitt
-- första barn - hanteras på admin-sidan
CREATE TABLE IF NOT EXISTS default_requirements (
    id                    SERIAL PRIMARY KEY,
    name                  VARCHAR(200) NOT NULL,
    type                  VARCHAR(20) DEFAULT 'checkbox' NOT NULL,
    frequency             VARCHAR(10) DEFAULT 'daily' NOT NULL,
    weekly_target_minutes INTEGER,
    created_at            TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO default_requirements (name, type, frequency, weekly_target_minutes)
SELECT v.n, v.t, v.f, v.m FROM (VALUES
    ('Städa rummet', 'checkbox', 'weekly', NULL::integer),
    ('Läsa böcker',  'minutes',  'weekly', 120)
) AS v(n, t, f, m)
WHERE NOT EXISTS (SELECT 1 FROM default_requirements);

CREATE TABLE IF NOT EXISTS default_deduction_types (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    amount     NUMERIC(10,2) NOT NULL,
    unit       VARCHAR(3) DEFAULT 'kr' NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO default_deduction_types (name, amount, unit)
SELECT v.n, v.a, v.u FROM (VALUES
    ('Ej dukat av tallriken',   -1::numeric,  'kr'),
    ('Skärmtid över gränsen',   -10::numeric, 'kr'),
    ('Bonus: Läxa klar tidigt',  10::numeric, 'kr')
) AS v(n, a, u)
WHERE NOT EXISTS (SELECT 1 FROM default_deduction_types);

-- Skärmtidskategorier: dagsbudget per barn och kategori (game/video/social/learning).
-- Ersätter den gamla veckopotten children.screen_budget_minutes (kolumnen lämnas kvar oanvänd).
CREATE TABLE IF NOT EXISTS child_screen_budgets (
    child_id      INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    category      VARCHAR(10) NOT NULL,
    daily_minutes INTEGER NOT NULL,
    PRIMARY KEY (child_id, category)
);

ALTER TABLE screen_logs ADD COLUMN IF NOT EXISTS category VARCHAR(10) DEFAULT 'game' NOT NULL;
ALTER TABLE screen_logs DROP CONSTRAINT IF EXISTS screen_logs_child_id_log_date_key;
DO $$ BEGIN
    ALTER TABLE screen_logs ADD CONSTRAINT screen_logs_child_date_cat UNIQUE (child_id, log_date, category);
EXCEPTION WHEN OTHERS THEN NULL; END $$;

INSERT INTO app_settings (key, value) VALUES
    ('default_screen_game',     '90'),
    ('default_screen_video',    '60'),
    ('default_screen_social',   '15'),
    ('default_screen_learning', '60')
ON CONFLICT (key) DO NOTHING;

-- Engångs (2026-07-12): befintliga barn får startbudgetar per kategori
-- (flaggan mig_screen_cats_done gör att detta bara körs en gång)
INSERT INTO child_screen_budgets (child_id, category, daily_minutes)
SELECT c.id, v.cat, v.dm FROM children c
CROSS JOIN (VALUES ('game', 90), ('video', 60), ('social', 15), ('learning', 60)) AS v(cat, dm)
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE key = 'mig_screen_cats_done')
  AND NOT EXISTS (SELECT 1 FROM child_screen_budgets b WHERE b.child_id = c.id);

INSERT INTO app_settings (key, value) VALUES ('mig_screen_cats_done', '1')
ON CONFLICT (key) DO NOTHING;

-- Förslagslådan: förbättringstips från användarna, läses på admin-sidan
CREATE TABLE IF NOT EXISTS suggestions (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    message    TEXT NOT NULL,
    done       BOOLEAN DEFAULT false NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Aktivitetslogg: sidvisningar och API-anrop per användare, visas på admin-sidan
CREATE TABLE IF NOT EXISTS activity_log (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER REFERENCES users(id) ON DELETE CASCADE,
    page       VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_activity_log_created ON activity_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_log_user ON activity_log(user_id);

-- Login attempt log (admin page shows failures; pruned on successful logins)
CREATE TABLE IF NOT EXISTS login_attempts (
    id         SERIAL PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    ip         VARCHAR(45),
    user_agent VARCHAR(255),
    success    BOOLEAN NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_created ON login_attempts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email ON login_attempts(email);

CREATE TABLE IF NOT EXISTS invitations (
    id         SERIAL PRIMARY KEY,
    child_id   INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    invited_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      VARCHAR(64) UNIQUE NOT NULL,
    accepted   BOOLEAN DEFAULT false NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '7 days')
);
