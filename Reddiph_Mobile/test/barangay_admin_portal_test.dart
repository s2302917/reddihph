import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/core/philippine_time.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/dashboards/barangay_admin_home.dart';
import 'package:share_plus/share_plus.dart';

void main() {
  testWidgets('barangay admin can approve a pending responder signup',
      (tester) async {
    final previousUser = AuthController.I.user;
    AuthController.I.user = {'token': 'admin-token', 'full_name': 'Admin'};
    addTearDown(() => AuthController.I.user = previousUser);
    final requests = <http.Request>[];
    var approved = false;
    final client = _client((request, body) async {
      requests.add(request);
      switch (body['action']) {
        case 'dashboard':
          return _json({
            'success': true,
            'summary': {
              'sos_today': 2,
              'in_progress': 1,
              'closed': 3,
              'false_reports': 0,
            },
            'activity': <Object>[],
          });
        case 'notifications':
          return _json({'success': true, 'notifications': <Object>[]});
        case 'approvals':
          return _json({
            'success': true,
            'counts': {
              'pending': approved ? 0 : 1,
              'approved': approved ? 1 : 0,
              'rejected': 0,
            },
            'signups': approved
                ? <Object>[]
                : [
                    {
                      'id': 32,
                      'role': 'responder',
                      'full_name': 'Jamie Cruz',
                      'work_email': 'jamie@example.test',
                      'phone_number': '09123456789',
                      'hospital_name': 'District Hospital',
                      'created_at': '2026-10-24 10:00:00',
                      'approval_status': 'pending',
                    },
                  ],
          });
        case 'signup_list':
          return _json({
            'success': true,
            'items': approved
                ? <Object>[]
                : [
                    {
                      'id': 32,
                      'role': 'responder',
                      'full_name': 'Jamie Cruz',
                      'work_email': 'jamie@example.test',
                      'phone_number': '09123456789',
                      'hospital_name': 'District Hospital',
                      'created_at': '2026-10-24 10:00:00',
                      'approval_status': 'pending',
                    },
                  ],
            'total': approved ? 0 : 1,
            'total_pages': 1,
          });
        case 'logs':
          return _json({
            'success': true,
            'items': <Object>[],
            'total_pages': 1,
          });
        case 'review_signup':
          approved = true;
          return _json({'success': true, 'message': 'Signup review saved.'});
        default:
          return _json({'success': false, 'message': 'Unexpected action.'});
      }
    });
    await tester.pumpWidget(MaterialApp(
      home: BarangayAdminHome(onLogout: () {}, apiClient: client),
    ));
    await tester.pumpAndSettle();
    expect(
      find.text(PhilippineTime.greeting(PhilippineTime.now())),
      findsOneWidget,
    );
    expect(find.text('1 Approval waiting'), findsOneWidget);
    expect(
      find.text('Waiting to be authorized to help in your barangay'),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const ValueKey('approval-waiting-card')));
    await tester.pumpAndSettle();
    expect(find.text('Jamie Cruz'), findsOneWidget);
    expect(
      requests.any((request) =>
          request.url.path.endsWith('barangay_portal.php') &&
          (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
              'signup_list' &&
          (jsonDecode(request.body) as Map<String, dynamic>)['status'] ==
              'pending'),
      isTrue,
    );
    await tester.tap(find.byKey(const ValueKey('signup-row-32')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('APPROVE'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const ValueKey('signup-decision-confirm')),
    );
    await tester.pumpAndSettle();

    final review = requests.firstWhere(
      (request) =>
          request.url.path.endsWith('barangay_portal.php') &&
          (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
              'review_signup',
    );
    expect(jsonDecode(review.body), {
      'token': 'admin-token',
      'action': 'review_signup',
      'account_id': 32,
      'decision': 'approve',
      'current_status': 'pending',
    });
    expect(find.text('Signup approved. The account can now log in.'),
        findsOneWidget);
    await tester.tap(find.text('Home'));
    await tester.pumpAndSettle();
    expect(find.text('0 Approval waiting'), findsOneWidget);
  });

  testWidgets('admin reviews approved and rejected signups', (tester) async {
    final previousUser = AuthController.I.user;
    AuthController.I.user = {'token': 'admin-token', 'full_name': 'Admin'};
    addTearDown(() => AuthController.I.user = previousUser);
    final requests = <http.Request>[];
    final signupStatuses = <int, String>{41: 'approved', 42: 'rejected'};
    final signupProfiles = <int, Map<String, Object>>{
      41: {
        'role': 'volunteer',
        'full_name': 'Approved Volunteer',
        'work_email': 'approved@example.test',
      },
      42: {
        'role': 'responder',
        'full_name': 'Rejected Responder',
        'work_email': 'rejected@example.test',
      },
    };
    final client = _client((request, body) async {
      requests.add(request);
      switch (body['action']) {
        case 'dashboard':
          return _json({
            'success': true,
            'summary': {
              'sos_today': 0,
              'in_progress': 0,
              'closed': 0,
              'false_reports': 0,
            },
            'activity': <Object>[],
          });
        case 'notifications':
          return _json({'success': true, 'notifications': <Object>[]});
        case 'approvals':
          return _json({
            'success': true,
            'signups': <Object>[],
            'counts': {'pending': 0, 'approved': 1, 'rejected': 1},
          });
        case 'signup_list':
          final status = body['status'] as String;
          final items = signupStatuses.entries
              .where((entry) => entry.value == status)
              .map((entry) => {
                    'id': entry.key,
                    ...signupProfiles[entry.key]!,
                    'approval_status': entry.value,
                    'created_at': '2026-10-24 10:00:00',
                  })
              .toList();
          return _json({
            'success': true,
            'items': items,
            'total': items.length,
            'total_pages': 1,
          });
        case 'review_signup':
          final accountId = body['account_id'] as int;
          signupStatuses[accountId] =
              body['decision'] == 'revoke' || body['decision'] == 'reject'
                  ? 'rejected'
                  : 'approved';
          return _json({'success': true, 'message': 'Signup review saved.'});
        case 'logs':
          return _json({
            'success': true,
            'items': <Object>[],
            'total_pages': 1,
          });
        default:
          return _json({'success': true});
      }
    });

    await tester.pumpWidget(MaterialApp(
      home: BarangayAdminHome(onLogout: () {}, apiClient: client),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Approval'));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('signup-filter-approved')));
    await tester.pumpAndSettle();
    expect(find.text('Approved Volunteer'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('signup-row-41')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('REVOKE AUTHORIZATION'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const ValueKey('signup-decision-confirm')),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('signup-filter-rejected')));
    await tester.pumpAndSettle();
    expect(find.text('Approved Volunteer'), findsOneWidget);
    expect(find.text('Rejected Responder'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('signup-row-42')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('RESTORE AUTHORIZATION'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.byKey(const ValueKey('signup-decision-confirm')),
    );
    await tester.pumpAndSettle();
    expect(find.text('Approved Volunteer'), findsOneWidget);
    expect(find.text('Rejected Responder'), findsNothing);

    final listStatuses = requests
        .where((request) =>
            request.url.path.endsWith('barangay_portal.php') &&
            (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
                'signup_list')
        .map((request) =>
            (jsonDecode(request.body) as Map<String, dynamic>)['status'])
        .toList();
    expect(
      listStatuses,
      ['pending', 'approved', 'approved', 'rejected', 'rejected'],
    );

    final reviewRequests = requests
        .where((request) =>
            request.url.path.endsWith('barangay_portal.php') &&
            (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
                'review_signup')
        .map((request) => jsonDecode(request.body) as Map<String, dynamic>)
        .toList();
    expect(reviewRequests, [
      {
        'token': 'admin-token',
        'action': 'review_signup',
        'account_id': 41,
        'decision': 'revoke',
        'current_status': 'approved',
      },
      {
        'token': 'admin-token',
        'action': 'review_signup',
        'account_id': 42,
        'decision': 'approve',
        'current_status': 'rejected',
      },
    ]);
  });

  testWidgets('admin searches logs and opens incident details', (tester) async {
    final previousUser = AuthController.I.user;
    AuthController.I.user = {'token': 'admin-token', 'full_name': 'Admin'};
    addTearDown(() => AuthController.I.user = previousUser);
    final requests = <http.Request>[];
    final client = _client((request, body) async {
      requests.add(request);
      switch (body['action']) {
        case 'dashboard':
          return _json({
            'success': true,
            'summary': {
              'sos_today': 1,
              'in_progress': 1,
              'closed': 0,
              'false_reports': 0,
            },
            'activity': <Object>[],
          });
        case 'notifications':
          return _json({'success': true, 'notifications': <Object>[]});
        case 'approvals':
          return _json({'success': true, 'signups': <Object>[]});
        case 'logs':
          return _json({
            'success': true,
            'items': [
              {
                'incident_id': 23,
                'incident_type': 'Trauma',
                'status': 'dispatched',
                'location': 'Barangay West',
                'reported_at': '2026-10-24 09:00:00',
                'reporter_name': 'Alex Resident',
              },
            ],
            'total_pages': 1,
          });
        case 'incident_detail':
          return _json({
            'success': true,
            'incident': {
              'incident_id': 23,
              'incident_type': 'Trauma',
              'status': 'dispatched',
              'location': 'Barangay West',
              'barangay': 'Barangay West',
              'reported_at': '2026-10-24 09:00:00',
              'reporter': {
                'name': 'Alex Resident',
                'email': 'alex@example.test',
                'phone': '09123456789',
              },
              'patient_summary': {
                'name': 'Alex Resident',
                'age': 35,
                'gender': 'male',
                'eta_minutes': 12,
              },
              'patients': <Object>[],
              'responder': {
                'name': 'Jamie Responder',
                'email': 'jamie@example.test',
                'phone': '09999999999',
              },
              'hospital': 'District Hospital',
              'hospital_address': 'Main road',
              'transport': {
                'name': 'Ambulance 1',
                'type': 'ambulance',
                'driver': 'Driver Name',
              },
              'nurse_preparation': {
                'completed': true,
                'completed_at': '2026-10-24 09:20:00',
                'nurse_name': 'Nurse Name',
                'heart_rate': 90,
                'blood_pressure': '120/80',
                'spo2': 97,
                'gcs': 15,
                'notes': 'Prepared.',
              },
              'false_report': null,
            },
          });
        default:
          return _json(
              {'success': true, 'reports': <Object>[], 'alerts': <Object>[]});
      }
    });

    await tester.pumpWidget(MaterialApp(
      home: BarangayAdminHome(onLogout: () {}, apiClient: client),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Logs'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'trauma');
    await tester.tap(find.byIcon(Icons.arrow_forward));
    await tester.pumpAndSettle();
    expect(find.text('Trauma'), findsOneWidget);
    final logRequest = requests.firstWhere(
      (request) =>
          request.url.path.endsWith('barangay_portal.php') &&
          (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
              'logs' &&
          (jsonDecode(request.body) as Map<String, dynamic>)['search'] ==
              'trauma',
    );
    expect((jsonDecode(logRequest.body) as Map<String, dynamic>)['page'], 1);

    await tester.tap(find.text('Trauma'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Case #RS-23'), findsOneWidget);
    expect(find.text('Trauma'), findsOneWidget);
    expect(find.text('SOS received'), findsOneWidget);
    expect(find.text('EXPORT THIS CASE'), findsOneWidget);
  });

  testWidgets('admin exports selected barangay log records', (tester) async {
    List<String> sharedMimeTypes = [];
    final previousUser = AuthController.I.user;
    AuthController.I.user = {'token': 'admin-token', 'full_name': 'Admin'};
    addTearDown(() => AuthController.I.user = previousUser);
    final requests = <http.Request>[];
    final client = _client((request, body) async {
      requests.add(request);
      switch (body['action']) {
        case 'dashboard':
          return _json({
            'success': true,
            'summary': {
              'sos_today': 1,
              'in_progress': 0,
              'closed': 0,
              'false_reports': 0,
            },
            'activity': <Object>[],
          });
        case 'notifications':
          return _json({'success': true, 'notifications': <Object>[]});
        case 'approvals':
          return _json({'success': true, 'signups': <Object>[]});
        case 'logs':
          return _json({
            'success': true,
            'items': <Object>[],
            'total_pages': 1,
          });
        case 'logs_export':
          return _json({
            'success': true,
            'period': 'today',
            'includes': ['sos'],
            'records': {
              'sos': [
                {
                  'incident_id': 23,
                  'occurred_at': '2026-10-21 09:00:00',
                  'incident_type': 'Trauma',
                  'status': 'dispatched',
                  'location': 'Barangay West',
                },
              ],
            },
          });
        default:
          return _json(
              {'success': true, 'reports': <Object>[], 'alerts': <Object>[]});
      }
    });

    await tester.pumpWidget(MaterialApp(
      home: BarangayAdminHome(
        onLogout: () {},
        apiClient: client,
        shareLogs: (params) async {
          final files = params.files!;
          sharedMimeTypes = files.map((file) => file.mimeType!).toList();
          final pdfBytes = await files
              .firstWhere((file) => file.mimeType == 'application/pdf')
              .readAsBytes();
          final csvBytes = await files
              .firstWhere((file) => file.mimeType == 'text/csv')
              .readAsBytes();
          expect(pdfBytes.take(4), [0x25, 0x50, 0x44, 0x46]);
          expect(utf8.decode(csvBytes), contains('"SOS reports"'));
          return const ShareResult('success', ShareResultStatus.success);
        },
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Logs'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Export logs'));
    await tester.pumpAndSettle();

    expect(find.text('Export logs'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('export-period-today')));
    await tester.tap(find.byKey(const ValueKey('export-include-hospital')));
    await tester.tap(find.byKey(const ValueKey('export-include-volunteers')));
    await tester
        .tap(find.byKey(const ValueKey('export-include-false_reports')));
    await tester.tap(find.byKey(const ValueKey('export-format-csv')));
    await tester.tap(find.text('EXPORT'));
    await tester.pumpAndSettle();

    final exportRequest = requests.firstWhere(
      (request) =>
          request.url.path.endsWith('barangay_portal.php') &&
          (jsonDecode(request.body) as Map<String, dynamic>)['action'] ==
              'logs_export',
    );
    expect(jsonDecode(exportRequest.body), {
      'token': 'admin-token',
      'action': 'logs_export',
      'period': 'today',
      'includes': ['sos'],
    });
    expect(sharedMimeTypes, containsAll(['application/pdf', 'text/csv']));
  });
}

MobileApiClient _client(
  Future<http.Response> Function(
    http.Request request,
    Map<String, dynamic> body,
  ) handler,
) {
  return MobileApiClient(
    baseUrl: 'https://example.test/Reddiph_mobile_api/',
    client: MockClient((request) async {
      final body = request.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(request.body) as Map<String, dynamic>;
      if (request.url.path.endsWith('false_reports.php')) {
        if (request.url.queryParameters['action'] == 'queue') {
          return _json({'success': true, 'reports': <Object>[]});
        }
        return _json({'success': true, 'warning_count': 0});
      }
      if (request.url.path.endsWith('scene_arrival_alerts.php')) {
        return _json({'success': true, 'alerts': <Object>[]});
      }
      return handler(request, body);
    }),
  );
}

http.Response _json(Map<String, Object?> value) =>
    http.Response(jsonEncode(value), 200);
