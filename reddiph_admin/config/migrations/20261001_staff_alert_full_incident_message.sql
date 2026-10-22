-- Preserve the full incident details in queued staff notifications.
ALTER TABLE staff_alerts
  MODIFY COLUMN message TEXT NOT NULL;