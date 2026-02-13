-- learner_sponsors: Many-to-many linking learners to sponsor employers
-- Each learner can have multiple sponsors (employers table)
-- Run this SQL manually in PostgreSQL

CREATE TABLE IF NOT EXISTS learner_sponsors (
    id SERIAL PRIMARY KEY,
    learner_id INTEGER NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
    employer_id INTEGER NOT NULL REFERENCES employers(employer_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (learner_id, employer_id)
);

CREATE INDEX IF NOT EXISTS idx_learner_sponsors_learner_id ON learner_sponsors(learner_id);
CREATE INDEX IF NOT EXISTS idx_learner_sponsors_employer_id ON learner_sponsors(employer_id);
