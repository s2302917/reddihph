import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/models/volunteer.dart';

void main() {
  test('parses volunteer reputation, vehicle and live distance', () {
    final volunteer = VolunteerProfile.fromJson({
      'id': 12,
      'name': 'A. Volunteer',
      'vehicle_type': 'Motorcycle',
      'rating': 4.7,
      'assisted_incidents': 8,
      'distance_km': 2.4,
      'available': true,
    });

    expect(volunteer.id, 12);
    expect(volunteer.vehicleType, 'Motorcycle');
    expect(volunteer.rating, 4.7);
    expect(volunteer.assistedIncidents, 8);
    expect(volunteer.distanceKm, 2.4);
    expect(volunteer.available, isTrue);
  });

  test('parses unknown reputation and stale location without inventing values',
      () {
    final volunteer = VolunteerProfile.fromJson({
      'id': 13,
      'name': 'B. Volunteer',
      'vehicle_type': 'Not provided',
      'rating': null,
      'assisted_incidents': 0,
      'distance_km': null,
      'available': true,
    });

    expect(volunteer.rating, isNull);
    expect(volunteer.distanceKm, isNull);
    expect(volunteer.assistedIncidents, 0);
  });

  test('parses MariaDB decimal strings in hospital previews', () {
    final preview = ResidentHospitalPreview.fromJson({
      'hospital': {
        'id': 3,
        'name': 'Community Hospital',
        'latitude': '10.12340000',
        'longitude': '122.45670000',
      },
      'estimated_road_distance_km': 4.2,
      'eta_minutes': 9,
    });

    expect(preview.hospitalId, 3);
    expect(preview.name, 'Community Hospital');
    expect(preview.latitude, 10.1234);
    expect(preview.longitude, 122.4567);
    expect(preview.etaMinutes, 9);
  });

  test('parses authenticated volunteer assistance requests', () {
    final request = VolunteerAssistanceRequest.fromJson({
      'assistance_id': 7,
      'incident_id': 42,
      'incident_type': 'Medical',
      'severity': 'urgent',
      'location': 'GPS 10.123400, 122.456700',
      'patient_name': 'Patient',
      'patient_age': 32,
      'notes': 'Needs transport',
      'status': 'requested',
      'created_at': '2026-10-18 12:00:00',
    });

    expect(request.assistanceId, 7);
    expect(request.incidentId, 42);
    expect(request.status, 'requested');
    expect(request.patientName, 'Patient');
    expect(request.patientAge, 32);
  });

  test('parses completed assistance history with resident feedback', () {
    final history = VolunteerHistoryItem.fromJson({
      'assistance_id': 9,
      'incident_id': 42,
      'completed_at': '2026-10-18 12:00:00',
      'incident_type': 'Medical',
      'location': 'Purok 7',
      'patient_name': 'Resident',
      'transport_type': 'Van',
      'rating': 5,
      'review': 'Thank you for the quick help.',
    });

    expect(history.assistanceId, 9);
    expect(history.rating, 5);
    expect(history.review, 'Thank you for the quick help.');
    expect(history.transportType, 'Van');
  });

  test('parses database-backed community reviews and volunteer details', () {
    final entry = VolunteerCommunityEntry.fromJson({
      'id': 11,
      'volunteer_id': 12,
      'volunteer_name': 'A. Volunteer',
      'reviewer_name': 'Resident',
      'rating': 4,
      'review': 'Very helpful.',
      'created_at': '2026-10-18 12:00:00',
    });
    final details = VolunteerDetails.fromJson({
      'id': 12,
      'name': 'A. Volunteer',
      'vehicle_type': 'Van',
      'rating': 4.5,
      'assisted_incidents': 8,
      'thanks_count': 2,
      'thanks_this_month': 1,
      'reviews': [
        {
          'reviewer_name': 'Resident',
          'rating': 5,
          'review': 'Great help.',
          'created_at': '2026-10-18 12:00:00',
        },
      ],
    });

    expect(entry.volunteerId, 12);
    expect(entry.reviewerName, 'Resident');
    expect(details.vehicleType, 'Van');
    expect(details.assistedIncidents, 8);
    expect(details.reviews.single.rating, 5);
  });
}
