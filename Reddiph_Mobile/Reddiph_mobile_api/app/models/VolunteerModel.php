<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/DispatchModel.php';
require_once __DIR__ . '/RoadRoutingModel.php';

/** Volunteer roster, location and assistance persistence. */
final class VolunteerModel
{
    private const LOCATION_FRESH_SECONDS = 900;

    public static function createProfile(int $userId, ?string $vehicleType): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO volunteer_profiles (user_id, vehicle_type) VALUES (?, ?)'
        );
        $stmt->execute([$userId, $vehicleType]);
    }

    public static function roster(float $latitude, float $longitude): array
    {
        $stmt = Database::connection()->query(
            "SELECT u.id, u.full_name, p.vehicle_type, p.latitude, p.longitude,
                    p.location_updated_at, p.is_available,
                    (SELECT AVG(r.rating)
                     FROM volunteer_assistance_reviews r
                     JOIN volunteer_assistances a ON a.id = r.assistance_id
                     WHERE a.volunteer_user_id = u.id) AS rating,
                    (SELECT COUNT(*)
                     FROM volunteer_assistances a
                     WHERE a.volunteer_user_id = u.id AND a.status = 'completed')
                        AS assisted_incidents
             FROM user_type u
             JOIN volunteer_profiles p ON p.user_id = u.id
             WHERE u.role = 'volunteer'
             ORDER BY u.full_name, u.id"
        );

        $now = time();
        $volunteers = [];
        foreach ($stmt->fetchAll() as $row) {
            $available = (int) $row['is_available'] === 1;
            $updatedAt = $row['location_updated_at'] === null
                ? false
                : strtotime((string) $row['location_updated_at']);
            $distanceKm = null;
            if ($available && $updatedAt !== false
                && $updatedAt >= $now - self::LOCATION_FRESH_SECONDS
                && $row['latitude'] !== null && $row['longitude'] !== null) {
                $distanceKm = round(DispatchModel::distanceKm(
                    (float) $row['latitude'],
                    (float) $row['longitude'],
                    $latitude,
                    $longitude
                ), 1);
            }

            $volunteers[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['full_name'],
                'vehicle_type' => $row['vehicle_type'] !== null
                    ? (string) $row['vehicle_type']
                    : 'Not provided',
                'rating' => $row['rating'] !== null
                    ? round((float) $row['rating'], 1)
                    : null,
                'assisted_incidents' => (int) $row['assisted_incidents'],
                'distance_km' => $distanceKm,
                'available' => $available,
            ];
        }

        return $volunteers;
    }

    public static function updateLocation(int $userId, float $latitude, float $longitude): void
    {
        $profile = Database::connection()->prepare(
            "SELECT p.is_available
             FROM volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
             WHERE p.user_id = ? LIMIT 1"
        );
        $profile->execute([$userId]);
        $available = $profile->fetchColumn();
        if ($available === false) {
            throw new RuntimeException('Volunteer profile is not available.');
        }
        if ((int) $available !== 1) {
            throw new InvalidArgumentException(
                'Set your volunteer availability to available to share your location.'
            );
        }

        $stmt = Database::connection()->prepare(
            "UPDATE volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
             SET p.latitude = ?, p.longitude = ?, p.location_updated_at = NOW()
             WHERE p.user_id = ? AND p.is_available = 1"
        );
        $stmt->execute([$latitude, $longitude, $userId]);
    }

    public static function setAvailability(int $userId, bool $available): void
    {
        if ($available) {
            $active = Database::connection()->prepare(
                "SELECT 1 FROM volunteer_assistances
                 WHERE volunteer_user_id = ? AND status = 'accepted'
                 LIMIT 1"
            );
            $active->execute([$userId]);
            if ($active->fetchColumn() !== false) {
                throw new InvalidArgumentException(
                    'Complete your accepted request before becoming available.'
                );
            }
        }

        $stmt = Database::connection()->prepare(
            "UPDATE volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
             SET p.is_available = ?
             WHERE p.user_id = ?"
        );
        $stmt->execute([$available ? 1 : 0, $userId]);
        if ($stmt->rowCount() === 0) {
            $check = Database::connection()->prepare(
                "SELECT 1 FROM volunteer_profiles p
                 JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
                 WHERE p.user_id = ? LIMIT 1"
            );
            $check->execute([$userId]);
            if ($check->fetchColumn() !== false) {
                return;
            }
            throw new RuntimeException('Volunteer profile is not available.');
        }
    }

    public static function updateVehicle(int $userId, ?string $vehicleType): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
             SET p.vehicle_type = ?
             WHERE p.user_id = ?"
        );
        $stmt->execute([$vehicleType, $userId]);
        if ($stmt->rowCount() === 0) {
            $check = Database::connection()->prepare(
                "SELECT 1 FROM volunteer_profiles p
                 JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
                 WHERE p.user_id = ? LIMIT 1"
            );
            $check->execute([$userId]);
            if ($check->fetchColumn() !== false) {
                return;
            }
            throw new RuntimeException('Volunteer profile is not available.');
        }
    }

    public static function requestAssistance(
        PDO $pdo,
        int $incidentId,
        int $volunteerUserId,
        int $requesterUserId
    ): void {
        $stmt = $pdo->prepare(
            "SELECT p.is_available
             FROM volunteer_profiles p
             JOIN user_type u ON u.id = p.user_id AND u.role = 'volunteer'
             WHERE p.user_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$volunteerUserId]);
        $row = $stmt->fetch();
        if ($row === false || (int) $row['is_available'] !== 1) {
            throw new InvalidArgumentException(
                'That volunteer is no longer available. Choose another volunteer.'
            );
        }

        $insert = $pdo->prepare(
            "INSERT INTO volunteer_assistances
                (incident_id, volunteer_user_id, requested_by_user_id, status)
             VALUES (?, ?, ?, 'requested')"
        );
        $insert->execute([$incidentId, $volunteerUserId, $requesterUserId]);
    }

    public static function requests(int $volunteerUserId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id AS assistance_id, i.incident_id, i.incident_type,
                    i.status AS severity, i.location, i.latitude, i.longitude,
                    i.patient_name, i.patient_age, i.notes, i.transport_name,
                    a.status, a.created_at, requester.full_name AS requester_name,
                    hospital.name AS hospital_name, hospital.latitude AS hospital_latitude,
                    hospital.longitude AS hospital_longitude,
                    presence.latitude AS responder_latitude,
                    presence.longitude AS responder_longitude
             FROM volunteer_assistances a
             JOIN incidents i ON i.incident_id = a.incident_id
             LEFT JOIN user_type requester ON requester.id = a.requested_by_user_id
             LEFT JOIN hospitals hospital ON hospital.id = i.hospital_id
             LEFT JOIN responder_presence presence
                ON presence.user_id = i.assigned_responder_user_id
               AND presence.last_seen_at >= NOW() - INTERVAL 90 SECOND
             WHERE a.volunteer_user_id = ? AND a.status IN ('requested','accepted')
             ORDER BY a.created_at DESC, a.id DESC"
        );
        $stmt->execute([$volunteerUserId]);
        $rows = $stmt->fetchAll();

        return array_map(static fn (array $row): array => [
            'assistance_id' => (int) $row['assistance_id'],
            'incident_id' => (int) $row['incident_id'],
            'incident_type' => (string) $row['incident_type'],
            'severity' => (string) $row['severity'],
            'location' => (string) $row['location'],
            'patient_name' => $row['patient_name'] !== null
                ? (string) $row['patient_name']
                : 'Patient',
            'patient_age' => $row['patient_age'] !== null
                ? (int) $row['patient_age']
                : null,
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : '',
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'requester_name' => $row['requester_name'] !== null
                ? (string) $row['requester_name']
                : 'Resident',
            'transport_name' => $row['transport_name'] !== null
                ? (string) $row['transport_name']
                : null,
            'hospital_name' => $row['hospital_name'] !== null
                ? (string) $row['hospital_name']
                : null,
            'hospital_latitude' => $row['hospital_latitude'] !== null
                ? (float) $row['hospital_latitude']
                : null,
            'hospital_longitude' => $row['hospital_longitude'] !== null
                ? (float) $row['hospital_longitude']
                : null,
            'responder_latitude' => $row['responder_latitude'] !== null
                ? (float) $row['responder_latitude']
                : null,
            'responder_longitude' => $row['responder_longitude'] !== null
                ? (float) $row['responder_longitude']
                : null,
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ], $rows);
    }

    public static function profileSummary(int $volunteerUserId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.full_name, u.work_email, u.phone_number, p.vehicle_type,
                    p.is_available,
                    (SELECT AVG(r.rating)
                     FROM volunteer_assistance_reviews r
                     JOIN volunteer_assistances a ON a.id = r.assistance_id
                     WHERE a.volunteer_user_id = u.id) AS rating,
                    (SELECT COUNT(*)
                     FROM volunteer_assistances a
                     WHERE a.volunteer_user_id = u.id AND a.status = 'completed')
                        AS assisted_incidents,
                    (SELECT COUNT(*)
                     FROM volunteer_assistance_reviews r
                     JOIN volunteer_assistances a ON a.id = r.assistance_id
                     WHERE a.volunteer_user_id = u.id
                       AND r.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'))
                        AS thanks_this_month,
                    (SELECT COUNT(*)
                     FROM volunteer_assistance_reviews r
                     JOIN volunteer_assistances a ON a.id = r.assistance_id
                     WHERE a.volunteer_user_id = u.id) AS thanks_count
             FROM user_type u
             JOIN volunteer_profiles p ON p.user_id = u.id
             WHERE u.id = ? AND u.role = 'volunteer'
             LIMIT 1"
        );
        $stmt->execute([$volunteerUserId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Volunteer profile is not available.');
        }

        return [
            'full_name' => (string) $row['full_name'],
            'work_email' => (string) $row['work_email'],
            'phone_number' => (string) $row['phone_number'],
            'vehicle_type' => $row['vehicle_type'] !== null
                ? (string) $row['vehicle_type']
                : null,
            'is_available' => (bool) $row['is_available'],
            'rating' => $row['rating'] !== null ? round((float) $row['rating'], 1) : null,
            'assisted_incidents' => (int) $row['assisted_incidents'],
            'thanks_this_month' => (int) $row['thanks_this_month'],
            'thanks_count' => (int) $row['thanks_count'],
        ];
    }

    public static function history(int $volunteerUserId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id AS assistance_id, a.incident_id, a.updated_at AS completed_at,
                    i.incident_type, i.location, i.patient_name, i.transport_type,
                    r.rating, r.review
             FROM volunteer_assistances a
             JOIN incidents i ON i.incident_id = a.incident_id
             LEFT JOIN volunteer_assistance_reviews r ON r.assistance_id = a.id
             WHERE a.volunteer_user_id = ? AND a.status = 'completed'
             ORDER BY a.updated_at DESC, a.id DESC
             LIMIT 200"
        );
        $stmt->execute([$volunteerUserId]);

        return array_map(static fn (array $row): array => [
            'assistance_id' => (int) $row['assistance_id'],
            'incident_id' => (int) $row['incident_id'],
            'completed_at' => (string) $row['completed_at'],
            'incident_type' => (string) $row['incident_type'],
            'location' => (string) $row['location'],
            'patient_name' => $row['patient_name'] !== null
                ? (string) $row['patient_name']
                : 'Patient',
            'transport_type' => $row['transport_type'] !== null
                ? (string) $row['transport_type']
                : null,
            'rating' => $row['rating'] !== null ? (int) $row['rating'] : null,
            'review' => $row['review'] !== null ? (string) $row['review'] : null,
        ], $stmt->fetchAll());
    }

    public static function community(): array
    {
        $pdo = Database::connection();
        $stats = $pdo->query(
            "SELECT COUNT(*) AS thanks_this_month,
                    (SELECT AVG(rating) FROM volunteer_assistance_reviews)
                        AS community_rating
             FROM volunteer_assistance_reviews
             WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        )->fetch();
        $people = $pdo->query(
            "SELECT u.id, u.full_name, p.vehicle_type,
                    AVG(r.rating) AS rating,
                    COUNT(r.id) AS thanks_count,
                    SUM(r.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'))
                        AS thanks_this_month,
                    COUNT(DISTINCT CASE WHEN a.status = 'completed'
                        THEN a.id END) AS assisted_incidents
             FROM user_type u
             JOIN volunteer_profiles p ON p.user_id = u.id
             LEFT JOIN volunteer_assistances a ON a.volunteer_user_id = u.id
             LEFT JOIN volunteer_assistance_reviews r ON r.assistance_id = a.id
             WHERE u.role = 'volunteer' AND u.approval_status = 'approved'
             GROUP BY u.id, u.full_name, p.vehicle_type
             ORDER BY thanks_count DESC, rating DESC, u.full_name ASC
             LIMIT 100"
        )->fetchAll();
        $entries = $pdo->query(
            "SELECT r.id, r.rating, r.review, r.created_at,
                    volunteer.id AS volunteer_id, volunteer.full_name AS volunteer_name,
                    volunteer_profile.vehicle_type,
                    reviewer.full_name AS reviewer_name
             FROM volunteer_assistance_reviews r
             JOIN volunteer_assistances a ON a.id = r.assistance_id
             JOIN user_type volunteer ON volunteer.id = a.volunteer_user_id
             JOIN volunteer_profiles volunteer_profile
                ON volunteer_profile.user_id = volunteer.id
             JOIN user_type reviewer ON reviewer.id = r.reviewer_user_id
             WHERE volunteer.role = 'volunteer'
               AND volunteer.approval_status = 'approved'
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 100"
        )->fetchAll();

        return [
            'thanks_this_month' => (int) ($stats['thanks_this_month'] ?? 0),
            'community_rating' => $stats['community_rating'] !== null
                ? round((float) $stats['community_rating'], 1)
                : null,
            'people' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['full_name'],
                'vehicle_type' => $row['vehicle_type'] !== null
                    ? (string) $row['vehicle_type']
                    : null,
                'rating' => $row['rating'] !== null ? round((float) $row['rating'], 1) : null,
                'thanks_count' => (int) $row['thanks_count'],
                'thanks_this_month' => (int) $row['thanks_this_month'],
                'assisted_incidents' => (int) $row['assisted_incidents'],
            ], $people),
            'entries' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'volunteer_id' => (int) $row['volunteer_id'],
                'volunteer_name' => (string) $row['volunteer_name'],
                'vehicle_type' => $row['vehicle_type'] !== null
                    ? (string) $row['vehicle_type']
                    : null,
                'reviewer_name' => (string) $row['reviewer_name'],
                'rating' => (int) $row['rating'],
                'review' => $row['review'] !== null ? (string) $row['review'] : '',
                'created_at' => (string) $row['created_at'],
            ], $entries),
        ];
    }

    public static function volunteerDetails(int $volunteerId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.full_name, p.vehicle_type,
                    AVG(r.rating) AS rating,
                    COUNT(DISTINCT CASE WHEN a.status = 'completed'
                        THEN a.id END) AS assisted_incidents,
                    SUM(r.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'))
                        AS thanks_this_month,
                    COUNT(r.id) AS thanks_count
             FROM user_type u
             JOIN volunteer_profiles p ON p.user_id = u.id
             LEFT JOIN volunteer_assistances a ON a.volunteer_user_id = u.id
             LEFT JOIN volunteer_assistance_reviews r ON r.assistance_id = a.id
             WHERE u.id = ? AND u.role = 'volunteer'
               AND u.approval_status = 'approved'
             GROUP BY u.id, u.full_name, p.vehicle_type
             LIMIT 1"
        );
        $stmt->execute([$volunteerId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $reviewStmt = Database::connection()->prepare(
            "SELECT reviewer.full_name AS reviewer_name, r.rating, r.review, r.created_at
             FROM volunteer_assistance_reviews r
             JOIN volunteer_assistances a ON a.id = r.assistance_id
             JOIN user_type reviewer ON reviewer.id = r.reviewer_user_id
             WHERE a.volunteer_user_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 10"
        );
        $reviewStmt->execute([$volunteerId]);
        $reviews = $reviewStmt->fetchAll();

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['full_name'],
            'vehicle_type' => $row['vehicle_type'] !== null
                ? (string) $row['vehicle_type']
                : null,
            'rating' => $row['rating'] !== null ? round((float) $row['rating'], 1) : null,
            'assisted_incidents' => (int) $row['assisted_incidents'],
            'thanks_this_month' => (int) $row['thanks_this_month'],
            'thanks_count' => (int) $row['thanks_count'],
            'reviews' => array_map(static fn (array $review): array => [
                'reviewer_name' => (string) $review['reviewer_name'],
                'rating' => (int) $review['rating'],
                'review' => $review['review'] !== null
                    ? (string) $review['review']
                    : '',
                'created_at' => (string) $review['created_at'],
            ], $reviews),
        ];
    }

    public static function assistanceRouteData(
        int $volunteerUserId,
        int $assistanceId,
        float $latitude,
        float $longitude
    ): ?array {
        $stmt = Database::connection()->prepare(
            "SELECT a.status, i.incident_id, i.incident_type, i.location,
                    i.latitude, i.longitude, i.patient_name, hospital.name AS hospital_name,
                    responder.full_name AS responder_name,
                    presence.latitude AS responder_latitude,
                    presence.longitude AS responder_longitude
             FROM volunteer_assistances a
             JOIN incidents i ON i.incident_id = a.incident_id
             LEFT JOIN hospitals hospital ON hospital.id = i.hospital_id
             LEFT JOIN user_type responder ON responder.id = i.assigned_responder_user_id
             LEFT JOIN responder_presence presence
                ON presence.user_id = i.assigned_responder_user_id
               AND presence.last_seen_at >= NOW() - INTERVAL 90 SECOND
             WHERE a.id = ? AND a.volunteer_user_id = ?
               AND a.status = 'accepted'
             LIMIT 1"
        );
        $stmt->execute([$assistanceId, $volunteerUserId]);
        $row = $stmt->fetch();
        if ($row === false || $row['latitude'] === null || $row['longitude'] === null) {
            return null;
        }
        $route = RoadRoutingModel::route(
            $latitude,
            $longitude,
            (float) $row['latitude'],
            (float) $row['longitude']
        );
        $route['traffic_aware'] = false;

        return [
            'success' => true,
            'destination' => [
                'name' => 'Incident',
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
            ],
            'transport_assigned' => false,
            'route' => $route,
            'incident' => [
                'incident_id' => (int) $row['incident_id'],
                'incident_type' => (string) $row['incident_type'],
                'location' => (string) $row['location'],
                'patient_name' => $row['patient_name'] !== null
                    ? (string) $row['patient_name']
                    : 'Patient',
                'hospital_name' => $row['hospital_name'] !== null
                    ? (string) $row['hospital_name']
                    : 'Hospital not assigned',
                'responder_name' => $row['responder_name'] !== null
                    ? (string) $row['responder_name']
                    : null,
                'responder_latitude' => $row['responder_latitude'] !== null
                    ? (float) $row['responder_latitude']
                    : null,
                'responder_longitude' => $row['responder_longitude'] !== null
                    ? (float) $row['responder_longitude']
                    : null,
            ],
        ];
    }

    public static function respondToAssistance(
        int $volunteerUserId,
        int $assistanceId,
        string $action
    ): void {
        $nextStatus = match ($action) {
            'accept' => 'accepted',
            'decline' => 'declined',
            'complete' => 'completed',
            default => throw new InvalidArgumentException('Choose a valid request action.'),
        };
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT status FROM volunteer_assistances
                 WHERE id = ? AND volunteer_user_id = ? FOR UPDATE'
            );
            $stmt->execute([$assistanceId, $volunteerUserId]);
            $currentStatus = $stmt->fetchColumn();
            $validTransition = ($action === 'accept' || $action === 'decline')
                ? $currentStatus === 'requested'
                : $currentStatus === 'accepted';
            if (!$validTransition) {
                throw new InvalidArgumentException(
                    'This volunteer request has already changed.'
                );
            }
            if ($action === 'accept') {
                $availability = $pdo->prepare(
                    'SELECT is_available FROM volunteer_profiles
                     WHERE user_id = ? FOR UPDATE'
                );
                $availability->execute([$volunteerUserId]);
                if ((int) $availability->fetchColumn() !== 1) {
                    throw new InvalidArgumentException(
                        'You are unavailable for another request.'
                    );
                }
            }

            $update = $pdo->prepare(
                'UPDATE volunteer_assistances SET status = ? WHERE id = ?'
            );
            $update->execute([$nextStatus, $assistanceId]);
            if ($action === 'accept' || $action === 'complete') {
                $availability = $action === 'complete' ? 1 : 0;
                $pdo->prepare(
                    'UPDATE volunteer_profiles SET is_available = ? WHERE user_id = ?'
                )->execute([$availability, $volunteerUserId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function rateIncident(
        int $residentUserId,
        int $incidentId,
        int $rating,
        ?string $review
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.status
                 FROM volunteer_assistances a
                 JOIN incidents i ON i.incident_id = a.incident_id
                 WHERE i.incident_id = ? AND i.reporter_user_id = ?
                 FOR UPDATE'
            );
            $stmt->execute([$incidentId, $residentUserId]);
            $assistance = $stmt->fetch();
            if ($assistance === false || $assistance['status'] !== 'completed') {
                throw new InvalidArgumentException(
                    'You can rate a volunteer after the assistance is complete.'
                );
            }

            $insert = $pdo->prepare(
                'INSERT INTO volunteer_assistance_reviews
                    (assistance_id, reviewer_user_id, rating, review)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([
                (int) $assistance['id'],
                $residentUserId,
                $rating,
                $review,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) $e->getCode() === 23000) {
                throw new InvalidArgumentException(
                    'You have already rated this volunteer.'
                );
            }
            throw $e;
        }
    }
}
