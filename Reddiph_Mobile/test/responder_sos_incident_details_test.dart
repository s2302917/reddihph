import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:latlong2/latlong.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/models/responder_dashboard.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/incident_arrival_screen.dart';
import 'package:reddiph/views/screens/report_next_screen.dart';

void main() {
  testWidgets('real emergency opens incident details, then prefills patient',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    AuthController.I.user = {'token': 'test-token'};
    addTearDown(AuthController.I.logout);

    late Map<String, dynamic> action;
    final apiClient = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        expect(request.url.path, endsWith('api/responder/incident_action.php'));
        action = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({'success': true, 'incident_type': 'Medical'}),
          200,
        );
      }),
    );
    const incident = ResponderIncident(
      id: 52,
      type: 'SOS emergency alert',
      status: 'critical',
      location: 'Batuan',
      minutesAgo: 1,
      reportedBy: 'resident_sos',
      patientName: 'Jamie Cruz',
      patientAge: 29,
      patientGender: 'female',
      point: LatLng(10.5, 122.8),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: IncidentArrivalScreen(
          incidentId: incident.id,
          incidentType: incident.type,
          location: incident.location,
          incidentPoint: incident.point,
          incident: incident,
          apiClient: apiClient,
        ),
      ),
    );
    await tester.tap(find.text('REAL EMERGENCY'));
    await tester.pumpAndSettle();

    expect(find.text('Incident details'), findsOneWidget);
    expect(find.text('Patient details'), findsNothing);
    expect(find.text('SOS emergency alert'), findsNothing);
    for (final type in ['Vehicular', 'Fall', 'Medical', 'Fire', 'Other']) {
      expect(find.widgetWithText(ChoiceChip, type), findsOneWidget);
    }
    for (final condition in ['Critical', 'Urgent', 'Stable']) {
      expect(find.widgetWithText(ChoiceChip, condition), findsOneWidget);
    }

    await tester.tap(find.widgetWithText(ChoiceChip, 'Other'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField), 'Electrical burn');
    await tester.tap(find.widgetWithText(ChoiceChip, 'Urgent'));
    await tester.tap(find.text('CONTINUE TO PATIENT DETAILS'));
    await tester.pumpAndSettle();

    expect(action['incident_id'], incident.id);
    expect(action['action'], 'update_incident_type');
    expect(action['incident_type'], 'Electrical burn');
    expect(find.byType(ReportNextScreen), findsOneWidget);
    expect(find.text('Patient details'), findsOneWidget);
    expect(
        tester
            .widget<EditableText>(find.byType(EditableText).at(0))
            .controller
            .text,
        'Jamie Cruz');
    expect(
        tester
            .widget<EditableText>(find.byType(EditableText).at(1))
            .controller
            .text,
        '29');
    expect(find.text('Unknown'), findsOneWidget);
    expect(find.text('ASK FOR HELP'), findsOneWidget);

    await tester.drag(find.byType(ListView).last, const Offset(0, -500));
    await tester.pumpAndSettle();
    await tester.tap(find.text('NEXT'));
    await tester.pumpAndSettle();
    expect(find.text('Confirm patient details'), findsOneWidget);
    expect(find.text('SEND TO HOSPITAL'), findsOneWidget);
    expect(find.text('SAVE TO THIS INCIDENT'), findsNothing);
    expect(find.text('URGENT'), findsOneWidget);
    expect(find.text('Jamie Cruz, 29'), findsOneWidget);
    expect(find.text('Female'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
