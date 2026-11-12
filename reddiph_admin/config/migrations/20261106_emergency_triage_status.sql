-- Separate emergency triage classification from incident workflow state.
-- Legacy classification mapping:
--   critical -> emergent
--   urgent   -> urgent
--   stable   -> non_urgent
-- Historical dispatched/resolved incidents have no stored triage value and
-- remain NULL so staff can classify them without inventing clinical data.

ALTER TABLE `incidents`
    MODIFY COLUMN `status`
        ENUM('critical','dispatched','urgent','stable','resolved','referred','open')
        NOT NULL DEFAULT 'open';

ALTER TABLE `incidents`
    ADD COLUMN `triage_status`
        ENUM('emergent','urgent','non_urgent','expectant')
        DEFAULT NULL
        AFTER `status`;

UPDATE `incidents`
SET `triage_status` = CASE `status`
    WHEN 'critical' THEN 'emergent'
    WHEN 'urgent' THEN 'urgent'
    WHEN 'stable' THEN 'non_urgent'
    ELSE NULL
END
WHERE `status` IN ('critical','urgent','stable');

UPDATE `incidents`
SET `status` = 'open'
WHERE `status` IN ('critical','urgent','stable');

ALTER TABLE `incidents`
    MODIFY COLUMN `status`
        ENUM('open','dispatched','referred','resolved')
        NOT NULL DEFAULT 'open';

-- Rollback is intentionally documented rather than automated: the new
-- expectant classification and NULL legacy classifications cannot be mapped
-- losslessly to the old single status column. Restore a pre-migration backup
-- to roll back after triage data has been entered.
