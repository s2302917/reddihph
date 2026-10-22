-- Allow the emergency-response staff roles and support hospital-scoped name resolution.
ALTER TABLE user_type
  MODIFY COLUMN role ENUM(
    'hospital_admin',
    'doctor',
    'nurse',
    'resident',
    'responder',
    'volunteer'
  ) NOT NULL,
  ADD KEY idx_user_type_hospital_name_role (hospital_id, full_name, role);