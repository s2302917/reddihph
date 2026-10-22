ALTER TABLE incidents
    ADD COLUMN hospital_sent_at datetime DEFAULT NULL
        AFTER responder_arrived_at;
