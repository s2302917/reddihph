SET @nurse_bed_column_exists = (
  SELECT COUNT(*)
  FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'incidents'
    AND `COLUMN_NAME` = 'nurse_bed_id'
);
SET @nurse_bed_column_sql = IF(
  @nurse_bed_column_exists = 0,
  'ALTER TABLE `incidents` ADD COLUMN `nurse_bed_id` int(10) UNSIGNED DEFAULT NULL AFTER `nurse_prep_completed_at`',
  'SELECT 1'
);
PREPARE nurse_bed_stmt FROM @nurse_bed_column_sql;
EXECUTE nurse_bed_stmt;
DEALLOCATE PREPARE nurse_bed_stmt;

SET @nurse_bed_index_exists = (
  SELECT COUNT(*)
  FROM `information_schema`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'incidents'
    AND `INDEX_NAME` = 'idx_incidents_nurse_bed'
);
SET @nurse_bed_index_sql = IF(
  @nurse_bed_index_exists = 0,
  'ALTER TABLE `incidents` ADD KEY `idx_incidents_nurse_bed` (`nurse_bed_id`)',
  'SELECT 1'
);
PREPARE nurse_bed_stmt FROM @nurse_bed_index_sql;
EXECUTE nurse_bed_stmt;
DEALLOCATE PREPARE nurse_bed_stmt;

SET @nurse_bed_foreign_key_exists = (
  SELECT COUNT(*)
  FROM `information_schema`.`TABLE_CONSTRAINTS`
  WHERE `CONSTRAINT_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'incidents'
    AND `CONSTRAINT_NAME` = 'fk_incidents_nurse_bed'
    AND `CONSTRAINT_TYPE` = 'FOREIGN KEY'
);
SET @nurse_bed_foreign_key_sql = IF(
  @nurse_bed_foreign_key_exists = 0,
  'ALTER TABLE `incidents` ADD CONSTRAINT `fk_incidents_nurse_bed` FOREIGN KEY (`nurse_bed_id`) REFERENCES `hospital_beds` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE nurse_bed_stmt FROM @nurse_bed_foreign_key_sql;
EXECUTE nurse_bed_stmt;
DEALLOCATE PREPARE nurse_bed_stmt;
