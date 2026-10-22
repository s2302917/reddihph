import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/models/doctor_portal.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  test('doctor profile greets with the last word of the full name', () {
    final profile = DoctorProfile.fromJson({
      'id': 7,
      'full_name': 'Dr. Maya Santos',
      'hospital_id': 3,
      'hospital_name': 'Community Hospital',
      'is_available': true,
      'profile_image': null,
      'hospital_latitude': '10.1234',
      'hospital_longitude': '122.4567',
    });

    expect(profile.greetingName, 'Santos');
    expect(profile.initial, 'S');
  });

  test('dashboard request returns authenticated database case data', () async {
    late http.Request capturedRequest;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        capturedRequest = request;
        return http.Response(
          jsonEncode({
            'success': true,
            'profile': {
              'id': 7,
              'full_name': 'Dr. Maya Santos',
              'hospital_id': 3,
              'hospital_name': 'Community Hospital',
              'is_available': true,
              'profile_image': null,
              'hospital_latitude': '10.1234',
              'hospital_longitude': '122.4567',
            },
            'cases': [_caseJson()],
            'review_cases': [],
          }),
          200,
        );
      }),
    );

    final dashboard = await client.doctorDashboard(token: 'doctor-token');

    expect(
      capturedRequest.url.path,
      '/Reddiph_mobile_api/api/doctors/portal.php',
    );
    expect(jsonDecode(capturedRequest.body), {
      'token': 'doctor-token',
      'action': 'dashboard',
    });
    expect(dashboard.profile.greetingName, 'Santos');
    expect(dashboard.profile.hospitalLatitude, 10.1234);
    expect(dashboard.cases.single.id, 42);
    expect(dashboard.cases.single.heartRate, 102);
    expect(dashboard.cases.single.latitude, 10.2);
  });

  test('case review request trims and submits clinical data', () async {
    late http.Request capturedRequest;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        capturedRequest = request;
        return http.Response(
          jsonEncode({
            'success': true,
            'case': {
              ..._caseJson(),
              'clinical_note': 'Vitals reviewed.',
              'doctor_arrived_at': '2026-10-27 10:00:00',
            },
          }),
          200,
        );
      }),
    );

    final result = await client.saveDoctorCaseReview(
      token: 'doctor-token',
      incidentId: 42,
      clinicalNote: '  Vitals reviewed.  ',
      confirmArrival: true,
    );

    expect(jsonDecode(capturedRequest.body), {
      'token': 'doctor-token',
      'action': 'save_review',
      'incident_id': 42,
      'clinical_note': 'Vitals reviewed.',
      'confirm_arrival': true,
    });
    expect(result.clinicalNote, 'Vitals reviewed.');
    expect(result.doctorArrivedAt, isNotNull);
  });
}

Map<String, dynamic> _caseJson() => {
      'id': 42,
      'title': 'Medical emergency',
      'status': 'critical',
      'status_label': 'Critical',
      'location': 'Purok 4',
      'latitude': '10.2000',
      'longitude': '122.4000',
      'patient': 'Patient One',
      'patient_count': 1,
      'patient_age': 42,
      'patient_gender': 'female',
      'eta_minutes': 8,
      'responder': 'Responder One',
      'hospital': 'Community Hospital',
      'reported_at': '2026-10-27 09:30:00',
      'resolved_at': null,
      'notes': 'Needs immediate evaluation.',
      'heart_rate': 102,
      'spo2': 92,
      'blood_pressure': '90/60',
      'doctor_arrived_at': null,
      'clinical_note': '',
      'patients': [],
      'timeline': [],
    };
