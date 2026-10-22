import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/models/incident_patient.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/report_next_screen.dart';
import 'package:reddiph/views/screens/volunteer_picker_screen.dart';

void main() {
  testWidgets('ask for help opens the volunteer picker page', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    AuthController.I.user = {'token': 'test-token'};
    addTearDown(AuthController.I.logout);

    const draft = ResponderIncidentDraft(
      incidentType: 'Medical',
      status: 'critical',
      reportedBy: 'on_scene',
      location: 'GPS 10, 122',
      latitude: 10,
      longitude: 122,
    );
    final apiClient = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        if (request.url.path.endsWith('api/volunteers/roster.php')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'volunteers': [
                {
                  'id': 4,
                  'name': 'Alex Volunteer',
                  'vehicle_type': 'Tricycle',
                  'rating': 4.8,
                  'assisted_incidents': 3,
                  'distance_km': 0.8,
                  'available': true,
                },
              ],
            }),
            200,
          );
        }
        expect(request.url.queryParameters, {'action': 'preview'});
        return http.Response(
          jsonEncode({
            'success': true,
            'hospital': {
              'id': 2,
              'name': 'District Hospital',
              'latitude': 10.5,
              'longitude': 122.5,
            },
            'estimated_road_distance_km': 3.1,
            'eta_minutes': 6,
          }),
          200,
        );
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ReportNextScreen(draft: draft, apiClient: apiClient),
      ),
    );
    await tester.enterText(find.byType(TextField).at(0), 'Jamie Cruz');
    await tester.enterText(find.byType(TextField).at(1), '27');
    await tester.tap(find.text('Male'));
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('ASK FOR HELP'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('ASK FOR HELP'));
    await tester.pumpAndSettle();

    final sosCenter = tester.getCenter(
      find.descendant(
        of: find.byType(VolunteerPickerScreen),
        matching: find.byType(FloatingActionButton),
      ),
    );
    expect(sosCenter.dx, greaterThan(300));
    expect(sosCenter.dy, lessThan(640));
    expect(sosCenter.dy, greaterThan(500));

    expect(find.text('NEXT: REVIEW AND SEND'), findsOneWidget);
    final nextCenter = tester.getCenter(find.text('NEXT: REVIEW AND SEND'));
    expect(nextCenter.dy, lessThan(550));
    await tester.tap(find.text('NEXT: REVIEW AND SEND'));
    await tester.pumpAndSettle();
    expect(find.text('Send to hospital'), findsOneWidget);
    expect(find.text('Alex Volunteer'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('SEND TO HOSPITAL'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('SEND TO HOSPITAL'), findsOneWidget);
  });

  testWidgets('patient report form stays usable on a compact phone',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const draft = ResponderIncidentDraft(
      incidentType: 'Medical',
      status: 'critical',
      reportedBy: 'on_scene',
      location: 'GPS 10, 122',
      latitude: 10,
      longitude: 122,
    );
    late http.Request previewRequest;
    final apiClient = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        previewRequest = request;
        return http.Response(
          jsonEncode({
            'success': true,
            'hospital': {
              'id': 2,
              'name': 'District Hospital',
              'latitude': 10.5,
              'longitude': 122.5,
            },
            'estimated_road_distance_km': 3.1,
            'eta_minutes': 6,
          }),
          200,
        );
      }),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: ReportNextScreen(draft: draft, apiClient: apiClient),
      ),
    );

    expect(find.text('Patient details'), findsOneWidget);
    expect(find.text('Incident'), findsOneWidget);
    expect(find.text('Patient'), findsOneWidget);
    expect(find.text('Send'), findsOneWidget);
    expect(find.text('Add patient photo'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.enterText(find.byType(TextField).at(0), 'Jamie Cruz');
    await tester.enterText(find.byType(TextField).at(1), '27');
    tester.testTextInput.hide();
    await tester.pump();
    await tester.tap(find.text('Female'));
    await tester.scrollUntilVisible(
      find.text('Conscious'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Conscious'));
    await tester.tap(find.text('Breathing difficulty'));
    await tester.scrollUntilVisible(
      find.text('ASK FOR HELP'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('ASK FOR HELP'), findsOneWidget);
    await tester.tap(find.text('+ ANOTHER PATIENT'));
    await tester.pump();

    await tester.scrollUntilVisible(
      find.text('Patients in this draft (1)'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Patients in this draft (1)'), findsOneWidget);
    expect(find.text('Jamie Cruz'), findsOneWidget);

    await tester.ensureVisible(find.text('NEXT'));
    await tester.tap(find.text('NEXT'));
    await tester.pumpAndSettle();

    expect(previewRequest.url.queryParameters, {'action': 'preview'});
    expect(find.text('Send to hospital'), findsOneWidget);
    expect(find.text('SEND TO HOSPITAL'), findsOneWidget);
    expect(find.text('District Hospital'), findsOneWidget);
    expect(find.text('6 min'), findsOneWidget);
    expect(find.text('Jamie Cruz, 27'), findsOneWidget);
    expect(find.text('Conscious, Breathing difficulty'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
