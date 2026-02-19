-- Drop Linear integration columns from feedback_submissions
-- These are no longer used after removing Linear sync in favour of local dashboard.

ALTER TABLE feedback_submissions
    DROP COLUMN IF EXISTS linear_issue_id,
    DROP COLUMN IF EXISTS linear_issue_url,
    DROP COLUMN IF EXISTS sync_status,
    DROP COLUMN IF EXISTS sync_attempts,
    DROP COLUMN IF EXISTS sync_error;

-- Drop the sync_status index if it exists
DROP INDEX IF EXISTS idx_feedback_sync_status;
