import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  test('nurse cases include authenticated incident and hospital details',
      () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'cases': [
              {
                'id': 42,
                'title': 'Medical emergency',
                'triage_category_label': 'Urgent',
                'status': 'en_route',
                'incident_status': 'dispatched',
                'patient_name': 'Alex Patient',
                'patient_gender': 'female',
                'location': 'Purok 3',
                'hospital': 'District Hospital',
                'responder': 'Casey Responder',
                'unit': 'Unit 1',
                'eta_minutes': 8,
                'gcs': 12,
              },
            ],
          }),
          200,
        );
      }),
    );

    final cases = await client.nurseCases(token: 'signed-token');

    expect(request.url.path, '/Reddiph_mobile_api/api/nurses/portal.php');
    expect(jsonDecode(request.body), {
      'token': 'signed-token',
      'action': 'cases',
    });
    expect(cases.single.incidentStatus, 'dispatched');
    expect(cases.single.hospitalName, 'District Hospital');
    expect(cases.single.gcs, 12);
  });

  test('nurse history uses the authenticated nurse portal endpoint', () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'history': [
              {
                'id': 42,
                'title': 'Medical emergency',
                'status': 'resolved',
                'triage_category_label': 'Urgent',
                'patient_name': 'Alex Patient',
                'patient_gender': 'female',
                'patient_age': 34,
                'location': 'Purok 3',
                'reported_at': '2026-10-28 10:00:00',
                'updated_at': '2026-10-28 12:00:00',
                'notified_at': '2026-10-28 10:05:00',
                'responder': 'Casey Responder',
                'unit': 'Unit 1',
                'note': '',
                'responder_notes': '',
                'heart_rate': null,
                'spo2': null,
                'blood_pressure': null,
              },
            ],
          }),
          200,
        );
      }),
    );

    final history = await client.nurseCaseHistory(token: 'signed-token');

    expect(request.method, 'POST');
    expect(jsonDecode(request.body), {
      'token': 'signed-token',
      'action': 'case_history',
    });
    expect(history.single.id, 42);
    expect(history.single.status, 'resolved');
  });

  test('nurse profile uses the authenticated nurse portal endpoint', () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'profile': {
              'id': 17,
              'full_name': 'Jamie Nurse',
              'hospital_name': 'District Hospital',
              'department': 'Emergency',
              'is_available': true,
              'notifications_enabled': false,
              'language': 'Filipino',
              'license_number': 'RN-17',
              'next_shift': null,
            },
          }),
          200,
        );
      }),
    );

    final profile = await client.nurseProfile(token: 'signed-token');

    expect(
      request.url.toString(),
      'https://example.test/Reddiph_mobile_api/api/nurses/portal.php',
    );
    expect(request.method, 'POST');
    expect(
      jsonDecode(request.body),
      {'token': 'signed-token', 'action': 'settings'},
    );
    expect(profile.fullName, 'Jamie Nurse');
    expect(profile.notificationsEnabled, isFalse);
  });
}
