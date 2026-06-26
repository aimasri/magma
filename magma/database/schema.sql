-- Complete Unified Database Schema
--
-- Purpose:
-- - Define the core data structures for identity, multi-tenant branding, global catalog, 
--   event-sourced inventory, and manufacturing workflows.
-- - Serve as the single source of truth for the database architecture of the Fussy Baby Cakes application.
--
-- Teaching notes:
-- - Event Sourcing: Inventory uses a ledger (transactions) instead of just updating totals, 
--   ensuring an immutable audit trail.
-- - Multi-Tenancy: Vendors hold design tokens to allow dynamic re-skinning without code changes.
-- - Manufacturing: Labor is tracked as a primary cost alongside ingredients, and scalable vs fixed time is modeled.
-- - Keep heavy logic in application services; the database should enforce integrity via constraints and foreign keys.
--
-- Tables:
-- @table users                   Manages secure authentication.
-- @table user_tokens             Unified table for password resets and remember-me tokens.
-- @table plans                   Subscription tier definitions.
-- @table features                Granular feature flags for vendors.
-- @table vendors                 Tenant records holding branding tokens and business details.
-- @table vendor_addons           A la carte features enabled for a vendor.
-- @table countries               Global lookup for countries.
-- @table allergens               Global lookup for allergens.
-- @table product_types           Categorization for global products.
-- @table brands                  Global catalog of brands.
-- @table products                Global catalog of items.
-- @table product_packages        Unit conversion definitions.
-- @table product_allergens       Many-to-many relationship mapping products to allergens.
-- @table vendor_suppliers        Vendor-specific supplier records.
-- @table vendor_inventory        Cached state of available materials per vendor.
-- @table inventory_transactions  Immutable ledger of all stock movements per vendor.
-- @table inventory_audits        Records of physical stock reconciliations.
-- @table audit_items             Line items for physical stock reconciliations.
-- @table staff_roles             Definitions of staff roles and hourly rates per vendor.
-- @table recipes                 Manufacturing Bill of Materials (BOM).
-- @table recipe_sub_assemblies   Recursive mapping for complex nested recipes.
-- @table recipe_ingredients      Ingredients required for a recipe.
-- @table recipe_labor_steps      Labor cost definition per recipe.
-- @table order_labor_logs        Actual tracking of time spent on order fulfillment.
-- @table vendor_pricing_rules    Automated markup and pricing multipliers per vendor.
-- @table site_reviews            Customer reviews pending approval.

-- Section: 1. Identity & Auth

CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

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

-- Section: 4. Global Lookups and Classifications

CREATE TABLE IF NOT EXISTS countries (
    iso_code VARCHAR(2) PRIMARY KEY, 
    name VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS dietary_certifications (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(50) CHECK (category IN ('lifestyle', 'religious', 'warning'))
);

CREATE TABLE IF NOT EXISTS allergens (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    regulatory_tier VARCHAR(50) DEFAULT 'voluntary'
);

CREATE TABLE IF NOT EXISTS product_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(100) 
);

-- Section: 5. Global Product Catalog (Master List)

CREATE TABLE IF NOT EXISTS brands (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    country_code VARCHAR(2) REFERENCES countries(iso_code) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    brand_id INTEGER REFERENCES brands(id) ON DELETE SET NULL,
    product_type_id INTEGER REFERENCES product_types(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL, 
    variant VARCHAR(255),       
    gtin VARCHAR(14) UNIQUE, 
    base_unit VARCHAR(20) NOT NULL, 
    default_yield_percentage DECIMAL(5, 2) DEFAULT 100.00,
    storage_requirement VARCHAR(50) DEFAULT 'ambient' CHECK (storage_requirement IN ('ambient', 'chilled', 'frozen')),
    shelf_life_days INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS product_packages (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    package_name VARCHAR(50) NOT NULL, 
    conversion_factor DECIMAL(10, 4) NOT NULL, 
    is_default_purchase_unit BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS product_allergens (
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    allergen_id INTEGER NOT NULL REFERENCES allergens(id) ON DELETE CASCADE,
    presence_type VARCHAR(50) DEFAULT 'contains' CHECK (presence_type IN ('contains', 'may_contain', 'facility_shared')),
    PRIMARY KEY (product_id, allergen_id)
);

CREATE TABLE IF NOT EXISTS product_certifications (
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    certification_id INTEGER NOT NULL REFERENCES dietary_certifications(id) ON DELETE CASCADE,
    PRIMARY KEY (product_id, certification_id)
);

-- Section: 6. Vendor Inventory & Ledger (Warehouse)

CREATE TABLE IF NOT EXISTS vendor_suppliers (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendor_inventory (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity_available DECIMAL(10, 2) DEFAULT 0.00, 
    quantity_reserved DECIMAL(10, 2) DEFAULT 0.00,  
    reorder_point DECIMAL(10, 2) DEFAULT 0.00,      
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(vendor_id, product_id)
);

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id SERIAL,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    supplier_id INTEGER REFERENCES vendor_suppliers(id) ON DELETE SET NULL,
    transaction_type VARCHAR(50) NOT NULL, 
    quantity DECIMAL(10, 2) NOT NULL, 
    unit_price DECIMAL(10, 4),        
    currency VARCHAR(3) DEFAULT 'USD',
    reference_id INTEGER,             
    notes TEXT,                       
    batch_number VARCHAR(255),
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- In a partitioned table, the partition key must be part of the primary key
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

-- Initial Partitions for the remainder of 2026
CREATE TABLE IF NOT EXISTS inventory_transactions_2026_06 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-06-01') TO ('2026-07-01');
    
CREATE TABLE IF NOT EXISTS inventory_transactions_2026_07 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
    
CREATE TABLE IF NOT EXISTS inventory_transactions_2026_08 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');

-- The default partition catches any records outside the explicitly defined ranges
CREATE TABLE IF NOT EXISTS inventory_transactions_default PARTITION OF inventory_transactions DEFAULT;

-- Section: 7. Inventory Audits (Physical Reconciliation)

CREATE TABLE IF NOT EXISTS inventory_audits (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    conducted_by INTEGER NOT NULL REFERENCES users(id), 
    status VARCHAR(50) DEFAULT 'draft', 
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_items (
    id SERIAL PRIMARY KEY,
    audit_id INTEGER NOT NULL REFERENCES inventory_audits(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    expected_quantity DECIMAL(10, 2) NOT NULL,
    actual_quantity DECIMAL(10, 2) NOT NULL,
    reason_code VARCHAR(100), 
    UNIQUE(audit_id, product_id)
);

-- Section: 8. Manufacturing (Labor & Bill of Materials)

CREATE TABLE IF NOT EXISTS staff_roles (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    title VARCHAR(100) NOT NULL, 
    hourly_rate DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipes (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    yield_amount DECIMAL(10, 2) NOT NULL, 
    yield_unit VARCHAR(50) NOT NULL, 
    is_archived BOOLEAN DEFAULT FALSE,
    storage_requirement VARCHAR(50) DEFAULT 'ambient' CHECK (storage_requirement IN ('ambient', 'chilled', 'frozen')),
    shelf_life_days INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipe_sub_assemblies (
    id SERIAL PRIMARY KEY,
    parent_recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    child_recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    quantity_required DECIMAL(10, 2) NOT NULL 
);

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id SERIAL PRIMARY KEY,
    recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity_required DECIMAL(10, 2) NOT NULL, 
    custom_yield_percentage DECIMAL(5, 2) NULL, 
    notes VARCHAR(255) 
);

CREATE TABLE IF NOT EXISTS recipe_labor_steps (
    id SERIAL PRIMARY KEY,
    recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    role_id INTEGER NOT NULL REFERENCES staff_roles(id),
    task_name VARCHAR(255) NOT NULL, 
    estimated_minutes INTEGER NOT NULL,
    is_scalable BOOLEAN DEFAULT TRUE 
);

-- Section: 9. Operations & Analytics

CREATE TABLE IF NOT EXISTS order_labor_logs (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL, 
    recipe_labor_step_id INTEGER REFERENCES recipe_labor_steps(id),
    user_id INTEGER NOT NULL REFERENCES users(id), 
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP,
    actual_minutes INTEGER GENERATED ALWAYS AS (ROUND(EXTRACT(EPOCH FROM (end_time - start_time)) / 60.0)) STORED
);

CREATE TABLE IF NOT EXISTS vendor_pricing_rules (
    id SERIAL PRIMARY KEY,
    vendor_id INTEGER NOT NULL REFERENCES vendors(id) ON DELETE CASCADE,
    target_margin_percentage DECIMAL(5, 2) NOT NULL, 
    rush_order_multiplier DECIMAL(4, 2) DEFAULT 1.50, 
    weekend_delivery_multiplier DECIMAL(4, 2) DEFAULT 1.20,
    overhead_rate_per_minute DECIMAL(10, 4) DEFAULT 0.00
);

-- Section: 10. Site Reviews

CREATE TABLE IF NOT EXISTS site_reviews (
    id SERIAL PRIMARY KEY,
    author VARCHAR(255) NOT NULL,
    comment TEXT NOT NULL,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Section: 11. Performance Indexes

-- Foreign Key Indexes
-- (PostgreSQL does not automatically index foreign keys, which can cause severe performance issues on JOINs and cascading deletes)
CREATE INDEX IF NOT EXISTS idx_user_tokens_user_id ON user_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_vendors_plan_id ON vendors(plan_id);
CREATE INDEX IF NOT EXISTS idx_products_brand_id ON products(brand_id);
CREATE INDEX IF NOT EXISTS idx_products_product_type_id ON products(product_type_id);
CREATE INDEX IF NOT EXISTS idx_product_packages_product_id ON product_packages(product_id);
CREATE INDEX IF NOT EXISTS idx_vendor_suppliers_vendor_id ON vendor_suppliers(vendor_id);
CREATE INDEX IF NOT EXISTS idx_inventory_transactions_vendor_product ON inventory_transactions(vendor_id, product_id);
CREATE INDEX IF NOT EXISTS idx_inventory_transactions_product_id ON inventory_transactions(product_id);
CREATE INDEX IF NOT EXISTS idx_inventory_transactions_supplier_id ON inventory_transactions(supplier_id);
CREATE INDEX IF NOT EXISTS idx_inventory_audits_vendor_id ON inventory_audits(vendor_id);
CREATE INDEX IF NOT EXISTS idx_audit_items_audit_id ON audit_items(audit_id);
CREATE INDEX IF NOT EXISTS idx_audit_items_product_id ON audit_items(product_id);
CREATE INDEX IF NOT EXISTS idx_staff_roles_vendor_id ON staff_roles(vendor_id);
CREATE INDEX IF NOT EXISTS idx_recipes_vendor_id ON recipes(vendor_id);
CREATE INDEX IF NOT EXISTS idx_recipe_ingredients_recipe_id ON recipe_ingredients(recipe_id);
CREATE INDEX IF NOT EXISTS idx_recipe_ingredients_product_id ON recipe_ingredients(product_id);
CREATE INDEX IF NOT EXISTS idx_recipe_labor_steps_recipe_id ON recipe_labor_steps(recipe_id);
CREATE INDEX IF NOT EXISTS idx_order_labor_logs_vendor_id ON order_labor_logs(vendor_id);

-- Composite Indexes & Moderation Sorting
-- Speed up querying by combinations of frequently queried columns
CREATE INDEX IF NOT EXISTS idx_user_tokens_lookup ON user_tokens(type, expires_at);
CREATE INDEX IF NOT EXISTS idx_user_tokens_hash_lookup ON user_tokens(token_hash) WHERE type = 'password_reset';
CREATE INDEX IF NOT EXISTS idx_user_tokens_user_type ON user_tokens(user_id, type);
CREATE INDEX IF NOT EXISTS idx_reviews_status_id ON site_reviews(status, id DESC);
CREATE INDEX IF NOT EXISTS idx_reviews_pending_created ON site_reviews(created_at DESC) WHERE status = 'pending';

-- Section: 12. Triggers

DROP TRIGGER IF EXISTS set_users_updated_at ON users;
CREATE TRIGGER set_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

DROP TRIGGER IF EXISTS set_vendors_updated_at ON vendors;
CREATE TRIGGER set_vendors_updated_at BEFORE UPDATE ON vendors FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

DROP TRIGGER IF EXISTS set_vendor_inventory_updated_at ON vendor_inventory;
CREATE TRIGGER set_vendor_inventory_updated_at BEFORE UPDATE ON vendor_inventory FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

DROP TRIGGER IF EXISTS set_recipes_updated_at ON recipes;
CREATE TRIGGER set_recipes_updated_at BEFORE UPDATE ON recipes FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

