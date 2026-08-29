-- Ratings & Reviews Schema
--
-- Purpose:
-- - Define the data structures for managing customer ratings and reviews.
-- - Extract reviews into a separate module for clean reusability.
--
-- Why / Why this design:
-- - Decouples the reviews module from the core framework, adhering to Domain-Driven Design principles.
--
-- Teaching notes:
-- - Status tracking and moderation sorting are natively indexed to optimize queries.

-- Section: 1. Site Reviews

CREATE TABLE IF NOT EXISTS site_reviews (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    author VARCHAR(255) NOT NULL,
    comment TEXT NOT NULL,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Section: Indexes

CREATE INDEX IF NOT EXISTS idx_site_reviews_tenant_status ON site_reviews(tenant_id, status, id DESC);
CREATE INDEX IF NOT EXISTS idx_reviews_pending_created ON site_reviews(created_at DESC) WHERE status = 'pending';
