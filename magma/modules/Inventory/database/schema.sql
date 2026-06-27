-- Inventory & Manufacturing Schema (Catering Module)
--
-- Purpose:
-- - Define the data structures for global catalog, event-sourced inventory, and manufacturing workflows.
-- - Used by specific applications requiring catering and inventory capabilities.
--
-- Dependencies:
-- - Requires magma_core schema (users, vendors)

-- Section: 1. Food & Catering Lookups

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

-- Section: 2. Global Product Catalog (Master List)

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

-- Section: 3. Vendor Inventory & Ledger (Warehouse)

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
    
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

CREATE TABLE IF NOT EXISTS inventory_transactions_2026_06 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-06-01') TO ('2026-07-01');
    
CREATE TABLE IF NOT EXISTS inventory_transactions_2026_07 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
    
CREATE TABLE IF NOT EXISTS inventory_transactions_2026_08 PARTITION OF inventory_transactions 
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');

CREATE TABLE IF NOT EXISTS inventory_transactions_default PARTITION OF inventory_transactions DEFAULT;

-- Section: 4. Inventory Audits (Physical Reconciliation)

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

-- Section: 5. Manufacturing (Labor & Bill of Materials)

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

-- Section: 6. Operations & Analytics

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

-- Section: Indexes

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

-- Section: Triggers

DROP TRIGGER IF EXISTS set_vendor_inventory_updated_at ON vendor_inventory;
CREATE TRIGGER set_vendor_inventory_updated_at BEFORE UPDATE ON vendor_inventory FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();

DROP TRIGGER IF EXISTS set_recipes_updated_at ON recipes;
CREATE TRIGGER set_recipes_updated_at BEFORE UPDATE ON recipes FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();
