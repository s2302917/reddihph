import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/models/emergency.dart';

void main() {
  test('parses incident progress timestamps for the live status timeline', () {
    final details = IncidentDetails.fromJson({
      'incident_id': 12,
      'incident_type': 'Medical',
      'location': 'Batuan',
      'reported_at': '2026-10-21 01:00:00',
      'responder_assigned_at': '2026-10-21 01:02:00',
      'responder_acknowledged_at': '2026-10-21 01:03:00',
      'responder_arrived_at': '2026-10-21 01:05:00',
      'patient_picked_up_at': '2026-10-21 01:08:00',
      'hospital_sent_at': '2026-10-21 01:10:00',
      'hospital_arrived_at': '2026-10-21 01:30:00',
    });

    expect(details.reportedAt, DateTime(2026, 10, 21, 1));
    expect(details.responderAssignedAt, DateTime(2026, 10, 21, 1, 2));
    expect(details.responderAcknowledgedAt, DateTime(2026, 10, 21, 1, 3));
    expect(details.responderArrivedAt, DateTime(2026, 10, 21, 1, 5));
    expect(details.patientPickedUpAt, DateTime(2026, 10, 21, 1, 8));
    expect(details.hospitalSentAt, DateTime(2026, 10, 21, 1, 10));
    expect(details.hospitalArrivedAt, DateTime(2026, 10, 21, 1, 30));
  });

  test('keeps timeline timestamps optional for older API responses', () {
    final details = IncidentDetails.fromJson({
      'incident_id': 12,
      'incident_type': 'Medical',
      'location': 'Batuan',
    });

    expect(details.reportedAt, isNull);
    expect(details.responderAssignedAt, isNull);
    expect(details.responderAcknowledgedAt, isNull);
    expect(details.responderArrivedAt, isNull);
    expect(details.patientPickedUpAt, isNull);
    expect(details.hospitalSentAt, isNull);
    expect(details.hospitalArrivedAt, isNull);
  });
}
