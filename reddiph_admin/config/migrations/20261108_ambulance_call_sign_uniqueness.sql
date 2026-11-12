-- Before applying, review and reconcile any existing duplicate call signs:
-- SELECT hospital_id, call_sign, COUNT(*) AS record_count
-- FROM ambulances
-- GROUP BY hospital_id, call_sign
-- HAVING COUNT(*) > 1;
--
-- Do not remove duplicate records until their incident and audit references
-- have been reviewed.
-- Rollback: ALTER TABLE ambulances DROP INDEX uq_ambulances_hospital_call_sign;
ALTER TABLE ambulances
  ADD UNIQUE INDEX IF NOT EXISTS uq_ambulances_hospital_call_sign (hospital_id, call_sign);
