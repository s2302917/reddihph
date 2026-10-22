ALTER TABLE ambulances
  ADD COLUMN IF NOT EXISTS plate_number VARCHAR(30) DEFAULT NULL AFTER driver_name,
  ADD UNIQUE INDEX IF NOT EXISTS uq_ambulances_hospital_plate (hospital_id, plate_number);
