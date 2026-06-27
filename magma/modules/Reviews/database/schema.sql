-- Ratings & Reviews Schema
--
-- Purpose:
-- - Define the data structures for managing customer ratings and reviews.
-- - Extract reviews into a separate module for clean reusability.

-- Section: 1. Site Reviews

CREATE TABLE IF NOT EXISTS site_reviews (
    id SERIAL PRIMARY KEY,
    author VARCHAR(255) NOT NULL,
    comment TEXT NOT NULL,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Section: Indexes

CREATE INDEX IF NOT EXISTS idx_reviews_status_id ON site_reviews(status, id DESC);
CREATE INDEX IF NOT EXISTS idx_reviews_pending_created ON site_reviews(created_at DESC) WHERE status = 'pending';
