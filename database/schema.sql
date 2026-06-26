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

-- Migrate to family-level requirements (user_id replaces child_id as grouping key)
ALTER TABLE requirements    ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE deduction_types ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE CASCADE;
UPDATE requirements    r SET user_id = c.user_id FROM children c WHERE c.id = r.child_id AND r.user_id IS NULL;
UPDATE deduction_types d SET user_id = c.user_id FROM children c WHERE c.id = d.child_id AND d.user_id IS NULL;

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

CREATE TABLE IF NOT EXISTS invitations (
    id         SERIAL PRIMARY KEY,
    child_id   INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
    invited_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      VARCHAR(64) UNIQUE NOT NULL,
    accepted   BOOLEAN DEFAULT false NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '7 days')
);
