import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/models/nurse_portal.dart';

void main() {
  group('Nurse portal models', () {
    test('parses dashboard profile and case records from API JSON', () {
      final dashboard = NurseDashboardData.fromJson({
        'profile': {
          'id': 17,
          'full_name': 'Jamie Nurse',
          'hospital_name': 'District Hospital',
          'department': 'Emergency',
          'is_available': true,
          'notifications_enabled': true,
          'language': 'English',
          'license_number': 'RN-17',
          'next_shift': '2026-10-28 06:00:00',
        },
        'cases': [
          {
            'id': 42,
            'title': 'Vehicular collision',
            'triage_category_label': 'Emergent',
            'status': 'en_route',
            'patient_name': 'Alex Patient',
            'patient_gender': 'male',
            'location': 'Purok 3',
            'hospital': 'District Hospital',
            'incident_status': 'dispatched',
            'responder': 'Casey Responder',
            'unit': 'Unit 1',
            'patient_age': '34',
            'eta_minutes': 9,
            'note': '',
            'responder_notes': '',
            'heart_rate': null,
            'spo2': null,
            'blood_pressure': null,
            'gcs': 12,
            'reported_at': '2026-10-28 10:00:00',
            'transport_type': 'Ambulance',
          },
        ],
        'summary': {
          'incoming': 1,
          'active_cases': 1,
          'next_arrival': null,
        },
      });

      expect(dashboard.profile.firstName, 'Jamie');
      expect(dashboard.profile.hospitalName, 'District Hospital');
      expect(dashboard.cases.single.id, 42);
      expect(dashboard.cases.single.etaMinutes, 9);
      expect(dashboard.cases.single.hospitalName, 'District Hospital');
      expect(dashboard.cases.single.incidentStatus, 'dispatched');
      expect(dashboard.cases.single.gcs, 12);
      expect(dashboard.summary['active_cases'], 1);
    });

    test('parses notified historical cases with incident timestamps', () {
      final item = NurseCaseHistoryRecord.fromJson({
        'id': 42,
        'title': 'Vehicular collision',
        'status': 'resolved',
        'triage_category_label': 'Urgent',
        'patient_name': 'Alex Patient',
        'patient_gender': 'male',
        'patient_age': 34,
        'location': 'Purok 3',
        'reported_at': '2026-10-28 10:00:00',
        'updated_at': '2026-10-28 12:00:00',
        'notified_at': '2026-10-28 10:05:00',
        'responder': 'Casey Responder',
        'unit': 'Unit 1',
        'note': 'Assessment complete',
        'responder_notes': '',
        'heart_rate': 80,
        'spo2': 98,
        'blood_pressure': '120/80',
      });

      expect(item.id, 42);
      expect(item.status, 'resolved');
      expect(item.patientName, 'Alex Patient');
      expect(item.updatedAt, '2026-10-28 12:00:00');
      expect(item.heartRate, 80);
    });

    test('rejects incomplete API case payloads', () {
      expect(
        () => NurseCaseRecord.fromJson({'id': 'not-an-id'}),
        throwsFormatException,
      );
    });
  });

  group('Nurse portal cache', () {
    test('updates one case without dropping the other cached cases', () {
      const first = NurseCaseRecord(
        id: 1,
        title: 'Medical',
        triageCategoryLabel: 'Urgent',
        status: 'en_route',
        patientName: 'Patient One',
        patientGender: 'unknown',
        location: 'Location 1',
        responder: 'Responder',
        unit: 'Unit 1',
      );
      const second = NurseCaseRecord(
        id: 2,
        title: 'Fall',
        triageCategoryLabel: 'Non-Urgent',
        status: 'notified',
        patientName: 'Patient Two',
        patientGender: 'unknown',
        location: 'Location 2',
        responder: 'Responder',
        unit: 'Unit 2',
      );

      NursePortalCache.saveCases('token-test', [first, second]);
      NursePortalCache.saveCase(
        'token-test',
        const NurseCaseRecord(
          id: 1,
          title: 'Medical',
          triageCategoryLabel: 'Urgent',
          status: 'arrived',
          patientName: 'Patient One',
          patientGender: 'unknown',
          location: 'Location 1',
          responder: 'Responder',
          unit: 'Unit 1',
        ),
      );

      expect(NursePortalCache.cases('token-test'), hasLength(2));
      expect(
        NursePortalCache.cases('token-test')!.first.status,
        'arrived',
      );
      NursePortalCache.clear('token-test');
      expect(NursePortalCache.cases('token-test'), isNull);
    });
  });
}
