ALTER TABLE `user_type`
  MODIFY COLUMN `role` ENUM(
    'hospital_admin',
    'barangay_admin',
    'doctor',
    'nurse',
    'resident',
    'responder',
    'volunteer'
  ) NOT NULL;
