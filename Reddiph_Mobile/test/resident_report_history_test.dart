import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/resident_report_history_screen.dart';

Map<String, dynamic> _incident({
  required int id,
  required String status,
  required String patient,
  required String reportedBy,
  String? hospitalArrivedAt,
  int? falseReportId,
  String? falseReportReason,
}) =>
    {
      'incident_id': id,
      'incident_type': 'Medical emergency',
      'reported_by': reportedBy,
      'status': status,
      'location': 'Barangay San Isidro',
      'reported_at': '2026-10-18 09:30:00',
      'resolved_at': status == 'resolved' ? '2026-10-18 10:30:00' : null,
      'patient_name': patient,
      'patient_age': 34,
      'patient_gender': 'female',
      'eta_minutes': 8,
      'responder_name': 'Alex Responder',
      'transport_name': 'RB-04',
      'transport_type': 'Ambulance',
      'driver_name': 'Taylor Driver',
      'hospital': 'La Carlota District Hospital',
      'barangay': 'San Isidro',
      'responder_assigned_at': '2026-10-18 09:32:00',
      'responder_acknowledged_at': '2026-10-18 09:33:00',
      'responder_arrived_at': '2026-10-18 09:40:00',
      'patient_picked_up_at': '2026-10-18 09:45:00',
      'hospital_sent_at': '2026-10-18 09:50:00',
      'hospital_arrived_at': hospitalArrivedAt,
      'false_report_id': falseReportId,
      'false_report_reason': falseReportReason,
      'false_report_notes': null,
    };

void main() {
  testWidgets('shows resident report history filters and both closed outcomes',
      (tester) async {
    late http.Request request;
    final api = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((value) async {
        request = value;
        return http.Response(
          jsonEncode({
            'success': true,
            'incidents': [
              _incident(
                id: 8,
                status: 'critical',
                patient: 'Jordan Lee',
                reportedBy: 'on_scene',
              ),
              _incident(
                id: 10,
                status: 'urgent',
                patient: 'Morgan Ellis',
                reportedBy: 'on_scene',
              ),
              _incident(
                id: 9,
                status: 'stable',
                patient: 'Avery Santos',
                reportedBy: 'on_scene',
              ),
              _incident(
                id: 7,
                status: 'resolved',
                patient: 'Riley Morgan',
                reportedBy: 'resident_sos',
                falseReportId: 3,
                falseReportReason: 'wrong_location',
              ),
              _incident(
                id: 6,
                status: 'resolved',
                patient: 'Casey Cruz',
                reportedBy: 'resident_sos',
                hospitalArrivedAt: '2026-10-18 10:30:00',
              ),
            ],
          }),
          200,
        );
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ResidentReportHistoryScreen(
              token: 'resident-token', apiClient: api),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(request.url.path, '/Reddiph_mobile_api/api/incidents/history.php');
    expect(request.method, 'POST');
    expect(jsonDecode(request.body), {'token': 'resident-token'});
    expect(find.text('Total reports'), findsOneWidget);
    expect(find.text('Medical emergency — Jordan Lee'), findsOneWidget);
    expect(find.text('Medical emergency — Riley Morgan'), findsOneWidget);
    expect(find.text('Critical'), findsOneWidget);
    expect(find.text('Urgent'), findsOneWidget);
    expect(find.text('Stable'), findsOneWidget);
    expect(
      (tester.widget<Text>(find.text('Critical')).style?.color),
      const Color(0xFFC62828),
    );
    expect(
      (tester.widget<Text>(find.text('Urgent')).style?.color),
      const Color(0xFFEF6C00),
    );
    expect(
      (tester.widget<Text>(find.text('Stable')).style?.color),
      const Color(0xFF2E7D32),
    );
    await tester.tap(find.text('Medical emergency — Jordan Lee'));
    await tester.pumpAndSettle();
    expect(find.text('Critical'), findsOneWidget);
    expect(
      tester.widget<Text>(find.text('Critical')).style?.color,
      const Color(0xFFC62828),
    );
    await tester.tap(find.byTooltip('Back to report history'));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('Case #RS-7 · Oct 18, 2026 · Brgy. San Isidro'),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Medical emergency — Casey Cruz'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Medical emergency — Casey Cruz'), findsOneWidget);
    await tester.drag(find.byType(ListView).first, const Offset(0, 1200));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ChoiceChip, 'Active'));
    await tester.pumpAndSettle();
    expect(find.text('Medical emergency — Jordan Lee'), findsOneWidget);
    expect(find.text('Medical emergency — Riley Morgan'), findsNothing);
    expect(find.text('Critical'), findsOneWidget);
    expect(find.text('Urgent'), findsOneWidget);
    expect(find.text('Stable'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Closed'));
    await tester.pumpAndSettle();
    expect(find.text('Medical emergency — Jordan Lee'), findsNothing);
    expect(find.text('Medical emergency — Riley Morgan'), findsOneWidget);

    await tester.tap(find.text('Medical emergency — Riley Morgan'));
    await tester.pumpAndSettle();
    expect(
      find.text('Medical emergency — Riley Morgan'),
      findsOneWidget,
    );
    expect(find.text('Report closed'), findsOneWidget);
    expect(
      find.text('A responder checked and found no emergency'),
      findsOneWidget,
    );
    expect(
      find.text(
        'False reports take help away from people who need it. '
        'Repeated false reports can pause SOS. Reason: Wrong location.',
      ),
      findsOneWidget,
    );
    expect(find.text('Responder checking on you'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byTooltip('Back to report history'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Medical emergency — Casey Cruz'));
    await tester.tap(find.text('Medical emergency — Casey Cruz'));
    await tester.pumpAndSettle();
    expect(
        find.text('Arrived at La Carlota District Hospital'), findsOneWidget);
    expect(find.text('Response time'), findsOneWidget);
    expect(find.text('10 min'), findsOneWidget);
    expect(find.text('Hospital'), findsOneWidget);
    expect(find.text('Arrived at hospital'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
