ALTER TABLE incidents
    ADD COLUMN patient_picked_up_at datetime DEFAULT NULL
        AFTER hospital_sent_at,
    ADD COLUMN hospital_arrived_at datetime DEFAULT NULL
        AFTER patient_picked_up_at;
