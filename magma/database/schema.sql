-- Magma Core Database Schema
--
-- Purpose:
-- - Define the core data structures for identity, multi-tenant branding, and global lookups.
-- - Serve as the pure, underlying framework architecture for the Magma platform.

CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Section: 1. Identity & Auth

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'remember_me' or 'password_reset'
    selector CHAR(24) UNIQUE,  -- Used only by 'remember_me'
    token_hash VARCHAR(255) NOT NULL, -- Stores the hashed validator or reset token
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Section: 2. Subscriptions & Feature Flags

CREATE TABLE IF NOT EXISTS plans (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    monthly_price DECIMAL(10, 2) NOT NULL,
    stripe_product_id VARCHAR(255)
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

-- Section: 3. Multi-Tenancy (Vendors)

CREATE TABLE IF NOT EXISTS vendors (
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

CREATE TABLE IF NOT EXISTS vendor_addons (
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    feature_id INTEGER NOT NULL REFERENCES features(id) ON DELETE CASCADE,
    active_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_until TIMESTAMP,
    prorated_charge_applied BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (vendor_id, feature_id)
);

-- Section: 4. Global Lookups

CREATE TABLE IF NOT EXISTS countries (
    iso_code VARCHAR(2) PRIMARY KEY, 
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Section: Indexes

CREATE INDEX IF NOT EXISTS idx_user_tokens_user_id ON user_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_vendors_plan_id ON vendors(plan_id);
CREATE INDEX IF NOT EXISTS idx_user_tokens_lookup ON user_tokens(type, expires_at);
CREATE INDEX IF NOT EXISTS idx_user_tokens_hash_lookup ON user_tokens(token_hash) WHERE type = 'password_reset';
CREATE INDEX IF NOT EXISTS idx_user_tokens_user_type ON user_tokens(user_id, type);

-- Section: Triggers

DROP TRIGGER IF EXISTS set_users_updated_at ON users;
CREATE TRIGGER set_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

DROP TRIGGER IF EXISTS set_vendors_updated_at ON vendors;
CREATE TRIGGER set_vendors_updated_at BEFORE UPDATE ON vendors FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
