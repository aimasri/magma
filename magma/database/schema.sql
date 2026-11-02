-- Magma Core Database Schema
--
-- Purpose:
-- - Define the core data structures for identity, multi-tenant branding, and global lookups.
-- - Serve as the pure, underlying framework architecture for the Magma platform.
--
-- Why / Why this design:
-- - Centralizes authentication and multi-tenancy rules at the lowest level, ensuring modular domains (like Inventory) can plug in without redefining foundational auth logic.
--
-- Teaching notes:
-- - Framework Purity: Notice how there are no domain-specific tables (like products or reviews) in this file. This strict separation teaches developers how to avoid monolithic coupling.

CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Section: 1. Subscriptions & Feature Flags

CREATE TABLE IF NOT EXISTS plans (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    monthly_price DECIMAL(10, 2) NOT NULL,
    stripe_product_id VARCHAR(255),
    is_internal BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS features (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    parent_feature_id INTEGER REFERENCES features(id) ON DELETE SET NULL,
    a_la_carte_price DECIMAL(10, 2)
);

CREATE TABLE IF NOT EXISTS plan_features (
    plan_id INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    feature_id INTEGER NOT NULL REFERENCES features(id) ON DELETE CASCADE,
    PRIMARY KEY (plan_id, feature_id)
);

-- Section: 2. Multi-Tenancy (Tenants)

CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tagline TEXT,
    email VARCHAR(255),
    plan_id INTEGER REFERENCES plans(id) ON DELETE SET NULL,
    subscription_status VARCHAR(50) DEFAULT 'active',
    billing_cycle_anchor TIMESTAMP,
    payment_gateway_customer_id VARCHAR(255),
    theme_settings JSONB DEFAULT '{}'::jsonb,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenant_addons (
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    feature_id INTEGER NOT NULL REFERENCES features(id) ON DELETE CASCADE,
    active_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_until TIMESTAMP,
    prorated_charge_applied BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (tenant_id, feature_id)
);

CREATE TABLE IF NOT EXISTS tenant_domains (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    domain VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Section: 3. Identity & Auth

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'user',
    password_changed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS user_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'remember_me' or 'password_reset'
    selector CHAR(24) UNIQUE,  -- Used only by 'remember_me'
    token_hash VARCHAR(255) NOT NULL, -- Stores the hashed validator or reset token
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- Section: 4. Global Lookups

CREATE TABLE IF NOT EXISTS countries (
    iso_code VARCHAR(2) PRIMARY KEY, 
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Section: 5. Asynchronous Outbox & Projections

CREATE TABLE IF NOT EXISTS outbox_jobs (
    id BIGSERIAL PRIMARY KEY,
    tenant_id INTEGER,
    queue VARCHAR(255) NOT NULL,
    handler VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL,
    headers JSONB DEFAULT '{}'::jsonb,
    attempts INTEGER DEFAULT 0,
    locked_at TIMESTAMP,
    locked_by VARCHAR(255),
    lease_expires_at TIMESTAMP,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) WITH (autovacuum_vacuum_scale_factor = 0.05, autovacuum_vacuum_cost_limit = 500);

CREATE TABLE IF NOT EXISTS projection_checkpoints (
    tenant_id INTEGER NOT NULL DEFAULT 0,
    projection_name VARCHAR(255) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'completed', -- 'in_flight', 'completed', 'failed'
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSONB DEFAULT '{}'::jsonb,
    PRIMARY KEY (tenant_id, projection_name, event_id)
);

-- Section: Indexes

CREATE INDEX IF NOT EXISTS idx_users_tenant_id ON users(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenant_domains_tenant_id ON tenant_domains(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenant_addons_feature_id ON tenant_addons(feature_id);
CREATE INDEX IF NOT EXISTS idx_user_tokens_user_id ON user_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_tenants_plan_id ON tenants(plan_id);
CREATE INDEX IF NOT EXISTS idx_user_tokens_lookup ON user_tokens(type, expires_at);
CREATE INDEX IF NOT EXISTS idx_user_tokens_hash_lookup ON user_tokens(token_hash) WHERE type = 'password_reset';
CREATE INDEX IF NOT EXISTS idx_user_tokens_user_type ON user_tokens(user_id, type);
CREATE INDEX IF NOT EXISTS idx_outbox_jobs_pending ON outbox_jobs(id) WHERE locked_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_outbox_jobs_polling ON outbox_jobs(locked_at, attempts);
CREATE INDEX IF NOT EXISTS idx_outbox_jobs_lease ON outbox_jobs(lease_expires_at, attempts);
CREATE INDEX IF NOT EXISTS idx_outbox_jobs_claim_sweep ON outbox_jobs(lease_expires_at, id ASC) WHERE attempts >= 0;
CREATE INDEX IF NOT EXISTS idx_outbox_jobs_dead_letter ON outbox_jobs(id) WHERE attempts < 0;
CREATE INDEX IF NOT EXISTS idx_projection_checkpoints_lookup ON projection_checkpoints(tenant_id, projection_name, status);
CREATE INDEX IF NOT EXISTS idx_projection_checkpoints_prune ON projection_checkpoints(applied_at) WHERE status IN ('completed', 'failed');

-- Section: Triggers

DROP TRIGGER IF EXISTS set_tenants_updated_at ON tenants;
CREATE TRIGGER set_tenants_updated_at BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
