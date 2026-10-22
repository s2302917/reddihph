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
            'priority': 'Critical',
            'status': 'en_route',
            'patient_name': 'Alex Patient',
            'patient_gender': 'male',
            'location': 'Purok 3',
            'responder': 'Casey Responder',
            'unit': 'Unit 1',
            'tasks': [
              {'id': 4, 'name': 'Oxygen ready', 'done': 1, 'done_at': null},
            ],
            'prep_done': 1,
            'prep_total': 5,
            'prep_complete': false,
            'arrival_confirmed': false,
            'patient_age': '34',
            'eta_minutes': 9,
            'bed_id': null,
            'bed_label': null,
            'note': '',
            'responder_notes': '',
            'heart_rate': null,
            'spo2': null,
            'blood_pressure': null,
            'reported_at': '2026-10-28 10:00:00',
            'arrived_at': null,
            'transport_type': 'Ambulance',
          },
        ],
        'summary': {
          'incoming': 1,
          'prep_complete': 0,
          'prep_total': 1,
          'free_er_beds': 2,
          'free_pediatric_beds': 1,
          'next_arrival': null,
        },
      });

      expect(dashboard.profile.firstName, 'Jamie');
      expect(dashboard.profile.hospitalName, 'District Hospital');
      expect(dashboard.cases.single.id, 42);
      expect(dashboard.cases.single.tasks.single.done, isTrue);
      expect(dashboard.cases.single.etaMinutes, 9);
      expect(dashboard.summary['free_er_beds'], 2);
    });

    test('rejects incomplete API case payloads', () {
      expect(
        () => NurseCaseRecord.fromJson({'id': 42}),
        throwsFormatException,
      );
    });
  });

  group('Nurse portal cache', () {
    test('updates one case without dropping the other cached cases', () {
      const first = NurseCaseRecord(
        id: 1,
        title: 'Medical',
        priority: 'Urgent',
        status: 'en_route',
        patientName: 'Patient One',
        patientGender: 'unknown',
        location: 'Location 1',
        responder: 'Responder',
        unit: 'Unit 1',
        tasks: [],
        prepDone: 0,
        prepTotal: 0,
        prepComplete: false,
        arrivalConfirmed: false,
      );
      const second = NurseCaseRecord(
        id: 2,
        title: 'Fall',
        priority: 'Stable',
        status: 'notified',
        patientName: 'Patient Two',
        patientGender: 'unknown',
        location: 'Location 2',
        responder: 'Responder',
        unit: 'Unit 2',
        tasks: [],
        prepDone: 0,
        prepTotal: 0,
        prepComplete: false,
        arrivalConfirmed: false,
      );

      NursePortalCache.saveCases('token-test', [first, second]);
      NursePortalCache.saveCase(
        'token-test',
        const NurseCaseRecord(
          id: 1,
          title: 'Medical',
          priority: 'Urgent',
          status: 'arrived',
          patientName: 'Patient One',
          patientGender: 'unknown',
          location: 'Location 1',
          responder: 'Responder',
          unit: 'Unit 1',
          tasks: [],
          prepDone: 0,
          prepTotal: 0,
          prepComplete: false,
          arrivalConfirmed: true,
        ),
      );

      expect(NursePortalCache.cases('token-test'), hasLength(2));
      expect(
        NursePortalCache.cases('token-test')!.first.arrivalConfirmed,
        isTrue,
      );
      NursePortalCache.clear('token-test');
      expect(NursePortalCache.cases('token-test'), isNull);
    });
  });
}
