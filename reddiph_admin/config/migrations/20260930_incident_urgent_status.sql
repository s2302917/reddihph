-- Add the urgent classification while retaining dispatched for the existing
-- dispatch and live-mapping workflows. Run once before deploying the update.
ALTER TABLE incidents
  MODIFY COLUMN status ENUM('critical', 'dispatched', 'urgent', 'stable', 'resolved') NOT NULL DEFAULT 'critical';