DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    user_id INTEGER DEFAULT 0,
    author_name TEXT NOT NULL,
    author_email TEXT,
    content TEXT NOT NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    pros TEXT, -- JSON array
    cons TEXT, -- JSON array
    country_code TEXT,
    helpful_votes INTEGER DEFAULT 0,
    unhelpful_votes INTEGER DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'approved', -- 'approved', 'pending', 'spam'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS review_votes;
CREATE TABLE review_votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    review_id INTEGER NOT NULL,
    ip_address TEXT NOT NULL,
    user_id INTEGER DEFAULT 0,
    vote_type TEXT NOT NULL, -- 'helpful' or 'unhelpful'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(review_id, ip_address)
);

CREATE INDEX idx_reviews_product_id ON reviews(product_id);
CREATE INDEX idx_reviews_status ON reviews(status);
