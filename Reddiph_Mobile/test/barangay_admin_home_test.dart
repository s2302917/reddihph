import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/dashboards/barangay_admin_home.dart';

void main() {
  testWidgets('admin can choose a first-warning ban duration', (tester) async {
    tester.view.physicalSize = const Size(720, 1600);
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final oldUser = AuthController.I.user;
    AuthController.I.user = {'token': 'admin-token', 'full_name': 'Shine'};
    addTearDown(() => AuthController.I.user = oldUser);

    final requests = <http.Request>[];
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        requests.add(request);
        if (request.url.path.endsWith('barangay_portal.php')) {
          final action =
              (jsonDecode(request.body) as Map<String, dynamic>)['action'];
          return http.Response(
            jsonEncode(switch (action) {
              'dashboard' => {
                  'success': true,
                  'summary': {
                    'sos_today': 0,
                    'in_progress': 0,
                    'closed': 0,
                    'false_reports': 0,
                  },
                  'activity': <Object>[],
                },
              'notifications' => {
                  'success': true,
                  'notifications': <Object>[],
                },
              'approvals' => {'success': true, 'signups': <Object>[]},
              'logs' => {
                  'success': true,
                  'items': <Object>[],
                  'total_pages': 1,
                },
              _ => {'success': true},
            }),
            200,
          );
        }
        if (request.url.path.endsWith('scene_arrival_alerts.php')) {
          return http.Response(
            jsonEncode({'success': true, 'alerts': <Object>[]}),
            200,
          );
        }
        if (request.url.queryParameters['action'] == 'queue') {
          return http.Response(
            jsonEncode({
              'success': true,
              'reports': [
                {
                  'id': 4,
                  'incident_id': 12,
                  'reason': 'wrong_location',
                  'notes': 'The caller was not at the pin.',
                  'created_at': '2026-10-17 12:00:00',
                  'incident_type': 'SOS emergency alert',
                  'location': 'Barangay A',
                  'barangay_name': 'Barangay A',
                  'reporter_name': 'Jamie Cruz',
                  'reporter_email': 'jamie@example.test',
                  'warning_count': 0,
                  'banned_until': null,
                },
              ],
            }),
            200,
          );
        }
        return http.Response(
          jsonEncode({
            'success': true,
            'warning_count': 1,
            'banned_until': '2026-10-20 12:00:00',
            'ban_days': 5,
            'automatic_ban': false,
          }),
          200,
        );
      }),
    );

    await tester.pumpWidget(MaterialApp(
      home: BarangayAdminHome(onLogout: () {}, apiClient: client),
    ));
    await tester.pumpAndSettle();

    expect(find.text('SOS Today'), findsOneWidget);
    expect(find.text('Notifications'), findsNothing);
    await tester.tap(find.byTooltip('Notifications'));
    await tester.pumpAndSettle();
    expect(find.text('Case #RS-12'), findsOneWidget);
    expect(find.text('Jamie Cruz'), findsOneWidget);
    await tester.tap(find.text('REVIEW'));
    await tester.pumpAndSettle();
    expect(find.textContaining('first warning'), findsOneWidget);

    await tester.enterText(
      find.widgetWithText(TextField, 'Ban duration (days)'),
      '5',
    );
    await tester.tap(find.text('Ban resident'));
    await tester.pumpAndSettle();

    final reviewRequest = requests.last;
    expect(reviewRequest.url.queryParameters['action'], 'review');
    expect(jsonDecode(reviewRequest.body), {
      'token': 'admin-token',
      'false_report_id': 4,
      'decision': 'confirm',
      'ban_first_warning': true,
      'ban_days': 5,
    });
    expect(find.text('Case #RS-12'), findsNothing);
    expect(find.textContaining('5-day ban was applied'), findsOneWidget);
  });
}
