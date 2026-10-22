import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/false_report_screen.dart';

void main() {
  testWidgets('false-report screen submits a reason and notes', (tester) async {
    late http.Request request;
    bool? closed;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((incoming) async {
        request = incoming;
        return http.Response(
          jsonEncode({'success': true, 'false_report_id': 3}),
          201,
        );
      }),
    );

    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () async {
              closed = await Navigator.push<bool>(
                context,
                MaterialPageRoute<bool>(
                  builder: (_) => FalseReportScreen(
                    incidentId: 12,
                    location: 'Barangay A',
                    apiClient: client,
                  ),
                ),
              );
            },
            child: const Text('Open Case'),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('Open Case'));
    await tester.pumpAndSettle();
    expect(find.text('Case #RS-12 · Barangay A'), findsOneWidget);

    await tester.tap(find.text('Wrong location'));
    await tester.enterText(find.byType(TextField), 'Address does not match.');
    await tester.tap(find.text('CLOSE AS FALSE REPORT'));
    await tester.pumpAndSettle();

    expect(request.url.path, endsWith('api/responder/false_report.php'));
    expect(jsonDecode(request.body), {
      'token': '',
      'incident_id': 12,
      'reason': 'wrong_location',
      'notes': 'Address does not match.',
    });
    expect(closed, isTrue);
    expect(find.text('Open Case'), findsOneWidget);
    expect(find.textContaining('sent to your Barangay Admin'), findsOneWidget);
  });
}
