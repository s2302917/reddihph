import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:latlong2/latlong.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/models/responder_dashboard.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/report_next_screen.dart';
import 'package:reddiph/views/screens/responder_sos_incident_details_screen.dart';

void main() {
  testWidgets('sent incident details flow prefills patient for review',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    AuthController.I.user = {'token': 'test-token'};
    addTearDown(AuthController.I.logout);

    final apiClient = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((_) async => http.Response(
            jsonEncode({'success': true}),
            200,
          )),
    );
    const incident = ResponderIncident(
      id: 52,
      type: 'SOS emergency alert',
      status: 'open',
      location: 'Batuan',
      minutesAgo: 1,
      reportedBy: 'resident_sos',
      triageCategory: 'emergent',
      patientName: 'Jamie Cruz',
      patientAge: 29,
      patientGender: 'female',
      point: LatLng(10.5, 122.8),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ResponderSosIncidentDetailsScreen(
          incidentId: incident.id,
          incidentType: incident.type,
          triageCategory: incident.triageCategory,
          location: incident.location,
          point: incident.point!,
          incident: incident,
          alreadySentToHospital: true,
          apiClient: apiClient,
        ),
      ),
    );

    expect(find.text('Update incident details'), findsOneWidget);
    expect(find.text('Patient details'), findsNothing);
    expect(find.text('SOS emergency alert'), findsNothing);
    for (final type in ['Vehicular', 'Fall', 'Medical', 'Fire', 'Other']) {
      expect(find.widgetWithText(ChoiceChip, type), findsOneWidget);
    }
    for (final condition in ['Emergent', 'Urgent', 'Non-Urgent', 'Expectant']) {
      expect(find.widgetWithText(ChoiceChip, condition), findsOneWidget);
    }

    await tester.tap(find.widgetWithText(ChoiceChip, 'Other'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextFormField), 'Electrical burn');
    await tester.tap(find.widgetWithText(ChoiceChip, 'Urgent'));
    await tester.tap(find.text('CONTINUE TO PATIENT DETAILS'));
    await tester.pumpAndSettle();

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
    expect(find.text('SEND UPDATED INCIDENT DETAILS'), findsOneWidget);
    expect(find.text('SAVE TO THIS INCIDENT'), findsNothing);
    expect(find.text('Urgent'), findsOneWidget);
    expect(find.text('Jamie Cruz, 29'), findsOneWidget);
    expect(find.text('Female'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
