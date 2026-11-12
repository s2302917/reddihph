-- Rename the four-level emergency classification column without changing its values.
ALTER TABLE `incidents`
    CHANGE COLUMN `triage_status` `triage_category`
        ENUM('emergent','urgent','non_urgent','expectant')
        DEFAULT NULL;
