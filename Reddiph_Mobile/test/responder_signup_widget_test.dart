import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/signup_screen.dart';

void main() {
  testWidgets(
    'signup form fills hospital, barangay and gender from server data',
    (tester) async {
      Map<String, dynamic>? signupBody;
      // Tall viewport so the whole form is on screen.
      tester.view.physicalSize = const Size(800, 2400);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          if (request.method == 'POST') {
            signupBody = jsonDecode(request.body) as Map<String, dynamic>;
            return http.Response(
              jsonEncode({'success': true, 'user_id': 9}),
              201,
            );
          }
          return http.Response(
            jsonEncode({
              'success': true,
              'hospitals': [
                {
                  'id': 1,
                  'name': 'Test District Hospital',
                  'barangays': [
                    {'id': 5, 'name': 'Test Barangay'},
                  ],
                },
              ],
              'genders': ['male', 'female', 'other'],
            }),
            200,
          );
        }),
      );

      await tester.pumpWidget(
        MaterialApp(home: SignUpScreen(role: roles[1], apiClient: client)),
      );
      await tester.pumpAndSettle();

      // Nothing is hardcoded: options appear only after the server answers.
      expect(find.text('Test District Hospital'), findsNothing);

      final fields = find.byType(TextFormField);
      await tester.enterText(fields.at(0), 'Test Responder');
      await tester.enterText(fields.at(1), 'responder@gmail.com');
      await tester.enterText(fields.at(2), '09123456789');
      await tester.enterText(fields.at(3), '31');
      await tester.enterText(fields.at(4), 'Password!123');
      await tester.enterText(fields.at(5), 'Password!123');

      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Female').last);
      await tester.pumpAndSettle();

      final intDropdowns = find.byType(DropdownButtonFormField<int>);
      await tester.tap(intDropdowns.first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Test District Hospital').last);
      await tester.pumpAndSettle();

      await tester.tap(intDropdowns.last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Test Barangay').last);
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byType(Checkbox));
      await tester.tap(find.byType(Checkbox));
      await tester.pump();
      await tester.ensureVisible(find.text('Sign Up'));
      await tester.tap(find.text('Sign Up'));
      await tester.pumpAndSettle();

      expect(signupBody?['work_email'], 'responder@gmail.com');
      expect(signupBody?['age'], 31);
      expect(signupBody?['gender'], 'female');
      expect(signupBody?['hospital_id'], 1);
      expect(signupBody?['barangay_id'], 5);
    },
  );

  testWidgets('every role posts role, age, gender, hospital and barangay',
      (tester) async {
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    Future<Map<String, dynamic>?> signUp(Role role, List<String> texts) async {
      Map<String, dynamic>? body;
      Uri? posted;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          if (request.method == 'POST') {
            posted = request.url;
            body = jsonDecode(request.body) as Map<String, dynamic>;
            return http.Response(
                jsonEncode({'success': true, 'user_id': 12}), 201);
          }
          return http.Response(
            jsonEncode({
              'success': true,
              'hospitals': [
                {
                  'id': 1,
                  'name': 'Test District Hospital',
                  'barangays': [
                    {'id': 5, 'name': 'Test Barangay'},
                  ],
                },
              ],
              'genders': ['male', 'female'],
            }),
            200,
          );
        }),
      );
      // The screen pops itself after success, so it needs a route below it.
      final navigator = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        MaterialApp(navigatorKey: navigator, home: const SizedBox()),
      );
      unawaited(navigator.currentState!.push(MaterialPageRoute<void>(
        builder: (_) => SignUpScreen(role: role, apiClient: client),
      )));
      await tester.pumpAndSettle();

      expect(find.text('Age'), findsOneWidget);
      expect(find.text('Gender'), findsOneWidget);
      expect(find.text('Barangay'), findsOneWidget);

      final fields = find.byType(TextFormField);
      for (var i = 0; i < texts.length; i++) {
        await tester.enterText(fields.at(i), texts[i]);
      }
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Male').last);
      await tester.pumpAndSettle();
      final intDropdowns = find.byType(DropdownButtonFormField<int>);
      await tester.tap(intDropdowns.first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Test District Hospital').last);
      await tester.pumpAndSettle();
      await tester.tap(intDropdowns.last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Test Barangay').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.tap(find.byType(Checkbox));
      await tester.pump();
      await tester.ensureVisible(find.text('Sign Up'));
      await tester.tap(find.text('Sign Up'));
      await tester.pumpAndSettle();

      expect(posted?.path, endsWith('api/auth/signup.php'));
      return body;
    }

    // Text field order: name, [license], email, phone, age, [vehicle], password, confirm.
    final resident = await signUp(roles[0], [
      'Res Test',
      'res@reddiph.com',
      '09123456789',
      '22',
      'Password!123',
      'Password!123',
    ]);
    final doctor = await signUp(roles[2], [
      'Dr Test',
      'PRC-1',
      'doc@reddiph.com',
      '09123456789',
      '40',
      'Password!123',
      'Password!123',
    ]);
    final nurse = await signUp(roles[3], [
      'Nurse Test',
      'nurse@reddiph.com',
      '09123456789',
      '28',
      'Password!123',
      'Password!123',
    ]);
    final volunteer = await signUp(roles[4], [
      'Vol Test',
      'vol@reddiph.com',
      '09123456789',
      '19',
      '',
      'Password!123',
      'Password!123',
    ]);

    expect(resident?['role'], 'resident');
    expect(doctor?['role'], 'doctor');
    expect(doctor?['license_number'], 'PRC-1');
    expect(nurse?['role'], 'nurse');
    expect(nurse?.containsKey('license_number'), isFalse);
    expect(volunteer?['role'], 'volunteer');
    for (final body in [resident, doctor, nurse, volunteer]) {
      expect(body?['hospital_id'], 1);
      expect(body?['barangay_id'], 5);
      expect(body?['gender'], 'male');
      expect(body?['age'], isA<int>());
    }
  });
  testWidgets('signup shows a retry when options cannot be loaded',
      (tester) async {
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient(
        (_) async => http.Response(jsonEncode({'message': 'Down'}), 500),
      ),
    );

    await tester.pumpWidget(
      MaterialApp(home: SignUpScreen(role: roles[1], apiClient: client)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Retry loading locations'), findsOneWidget);
    expect(find.text('Yubo'), findsNothing);
  });
}
