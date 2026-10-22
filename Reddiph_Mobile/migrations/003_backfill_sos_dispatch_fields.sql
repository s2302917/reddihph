-- Completes SOS incidents that the mobile app created before it filled the dispatch fields.
-- Safe to run more than once: only rows that are still missing a value are changed.

-- 1. Barangay: the nearest barangay (with coordinates) of the incident's hospital.
UPDATE incidents i
SET i.barangay_id = (
    SELECT b.id
    FROM barangays b
    WHERE b.hospital_id = i.hospital_id
      AND b.latitude IS NOT NULL AND b.longitude IS NOT NULL
    ORDER BY POW(b.latitude - i.latitude, 2) + POW(b.longitude - i.longitude, 2)
    LIMIT 1
)
WHERE i.notes = 'Sent from the Reddiph mobile app.'
  AND i.barangay_id IS NULL
  AND i.hospital_id IS NOT NULL
  AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL;

-- 2. Location text: show the barangay name instead of raw GPS text.
UPDATE incidents i
JOIN barangays b ON b.id = i.barangay_id
SET i.location = b.name
WHERE i.notes = 'Sent from the Reddiph mobile app.'
  AND i.location LIKE 'GPS %';

-- 3. First ETA from the hospital to the incident (same formula as the admin live map).
UPDATE incidents i
JOIN hospitals h ON h.id = i.hospital_id
SET i.eta_minutes = GREATEST(1, CEIL(
    6371 * ACOS(LEAST(1,
        COS(RADIANS(h.latitude)) * COS(RADIANS(i.latitude)) * COS(RADIANS(i.longitude) - RADIANS(h.longitude))
        + SIN(RADIANS(h.latitude)) * SIN(RADIANS(i.latitude))))
    * 1.3 / 35 * 60))
WHERE i.notes = 'Sent from the Reddiph mobile app.'
  AND i.eta_minutes IS NULL
  AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
  AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL;
