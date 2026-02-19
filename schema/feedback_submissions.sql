-- schema/feedback_submissions.sql
-- Feedback Widget - Local persistence for UAT feedback
-- Run manually: psql -U wecoza -d wecoza -f schema/feedback_submissions.sql

CREATE TABLE IF NOT EXISTS feedback_submissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    feedback_text TEXT NOT NULL,
    ai_conversation JSONB DEFAULT '[]'::jsonb,
    ai_generated_title VARCHAR(500),
    ai_suggested_priority VARCHAR(20),
    page_url TEXT,
    page_title VARCHAR(500),
    shortcode VARCHAR(255),
    url_params JSONB DEFAULT '{}'::jsonb,
    browser_info VARCHAR(500),
    viewport VARCHAR(50),
    screenshot_path VARCHAR(500),
    linear_issue_id VARCHAR(100),
    linear_issue_url VARCHAR(500),
    sync_status VARCHAR(20) DEFAULT 'pending',
    sync_attempts INTEGER DEFAULT 0,
    sync_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_feedback_sync_status ON feedback_submissions(sync_status);
CREATE INDEX IF NOT EXISTS idx_feedback_user ON feedback_submissions(user_id);
CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback_submissions(created_at);
