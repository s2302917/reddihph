import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/models/hospital_arrival_summary.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/responder_hospital_arrival_screen.dart';

void main() {
  test('formats hospital arrival timeline and response time', () {
    final summary = HospitalArrivalSummary.fromJson({
      'hospital_name': 'District Hospital',
      'dispatched_at': '2026-10-19 11:14:00',
      'patient_picked_up_at': '2026-10-19 11:18:00',
      'arrived_at': '2026-10-19 11:26:00',
    });

    expect(summary.responseTimeMinutes, 4);
  });

  test('uses arrival time when pickup time was not recorded', () {
    final summary = HospitalArrivalSummary.fromJson({
      'hospital_name': 'District Hospital',
      'dispatched_at': '2026-10-19 11:14:00',
      'patient_picked_up_at': null,
      'arrived_at': '2026-10-19 11:26:00',
    });

    expect(summary.patientPickedUpAt, isNull);
    expect(summary.responseTimeMinutes, 12);
  });

  test('rejects malformed arrival timestamps', () {
    expect(
      () => HospitalArrivalSummary.fromJson({
        'hospital_name': 'District Hospital',
        'dispatched_at': 'not-a-time',
        'patient_picked_up_at': null,
        'arrived_at': '2026-10-19 11:26:00',
      }),
      throwsFormatException,
    );
  });

  test('parses a completed volunteer assignment and rating state', () {
    final summary = HospitalArrivalSummary.fromJson({
      'hospital_name': 'District Hospital',
      'dispatched_at': '2026-10-19 11:14:00',
      'patient_picked_up_at': '2026-10-19 11:18:00',
      'arrived_at': '2026-10-19 11:26:00',
      'volunteer_name': 'Rina Domingo',
      'volunteer_vehicle_type': 'motorcycle',
      'volunteer_assistance_status': 'completed',
      'volunteer_review_rating': null,
    });

    expect(summary.hasVolunteer, isTrue);
    expect(summary.canThankVolunteer, isTrue);
    expect(summary.volunteerName, 'Rina Domingo');
    expect(summary.volunteerVehicleType, 'motorcycle');
  });

  testWidgets('shows the hospital arrival completion page', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: ResponderHospitalArrivalScreen(
          incidentId: 17,
          summary: HospitalArrivalSummary.fromJson({
            'hospital_name': 'District Hospital',
            'dispatched_at': '2026-10-19 11:14:00',
            'patient_picked_up_at': '2026-10-19 11:18:00',
            'arrived_at': '2026-10-19 11:26:00',
          }),
        ),
      ),
    );

    expect(find.text('Arrived at the hospital'), findsOneWidget);
    expect(find.text('District Hospital · 11:26 AM · Arrival confirmed'),
        findsOneWidget);
    expect(find.text('Dispatched'), findsOneWidget);
    expect(find.text('Patient picked up'), findsOneWidget);
    expect(find.text('Arrived at hospital'), findsOneWidget);
    expect(find.text('Response time'), findsOneWidget);
    expect(find.text('4 min'), findsOneWidget);
    expect(find.text('DONE'), findsOneWidget);
  });

  testWidgets('sends selected volunteer rating and confirms thanks',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 693));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final oldUser = AuthController.I.user;
    AuthController.I.user = {'token': 'test-token'};
    addTearDown(() => AuthController.I.user = oldUser);

    late Map<String, dynamic> requestBody;
    final apiClient = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        requestBody = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({'success': true, 'thanks_sent': true}),
          201,
        );
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ResponderHospitalArrivalScreen(
          incidentId: 17,
          apiClient: apiClient,
          summary: HospitalArrivalSummary.fromJson({
            'hospital_name': 'District Hospital',
            'dispatched_at': '2026-10-19 11:14:00',
            'patient_picked_up_at': '2026-10-19 11:18:00',
            'arrived_at': '2026-10-19 11:26:00',
            'volunteer_name': 'Rina Domingo',
            'volunteer_vehicle_type': 'motorcycle',
            'volunteer_assistance_status': 'completed',
            'volunteer_review_rating': null,
          }),
        ),
      ),
    );

    expect(find.text('Thank Rina Domingo'), findsOneWidget);
    expect(find.text('Volunteer who brought a motorcycle'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('volunteer-rating-5')));
    await tester.tap(find.text('SEND THANKS'));
    await tester.pumpAndSettle();

    expect(requestBody, {
      'token': 'test-token',
      'incident_id': 17,
      'action': 'thank_volunteer',
      'rating': 5,
    });
    expect(find.text('THANKS SENT'), findsOneWidget);
  });
}
