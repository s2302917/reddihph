import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/services/mobile_api_client.dart';
import 'package:reddiph/views/screens/login_screen.dart';
import 'package:reddiph/views/screens/signup_screen.dart';

void main() {
  testWidgets('Barangay Admin login exposes the code-gated signup form',
      (tester) async {
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async => http.Response(
            jsonEncode({
              'success': true,
              'hospitals': [
                {
                  'id': 1,
                  'name': 'District Hospital',
                  'barangays': [
                    {'id': 2, 'name': 'Barangay West'},
                  ],
                },
              ],
              'genders': ['male', 'female', 'other'],
            }),
            200,
          )),
    );

    await tester.pumpWidget(MaterialApp(home: LoginScreen(role: roles.last)));
    expect(find.text('New here? Create a Barangay Admin account.'),
        findsOneWidget);

    await tester.pumpWidget(MaterialApp(
      home: SignUpScreen(role: roles.last, apiClient: client),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Create Barangay Admin account'), findsOneWidget);
    expect(find.text('Signup code'), findsOneWidget);
    await tester.ensureVisible(find.text('Select hospital'));
    await tester.tap(find.byType(DropdownButtonFormField<int>).first);
    await tester.pumpAndSettle();
    expect(find.text('District Hospital'), findsOneWidget);
    await tester.tap(find.text('District Hospital').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Select barangay'));
    await tester.tap(find.byType(DropdownButtonFormField<int>).last);
    await tester.pumpAndSettle();
    expect(find.text('Barangay West'), findsOneWidget);
  });

  test('admin signup sends the role slug and code to the API', () async {
    Map<String, dynamic>? submitted;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        submitted = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(jsonEncode({'success': true, 'user_id': 16}), 201);
      }),
    );

    await client.createAccount(
      role: 'barangay_admin',
      fullName: 'Barangay Officer',
      workEmail: 'officer@example.test',
      phoneNumber: '09123456789',
      age: 32,
      gender: 'female',
      hospitalId: 1,
      barangayId: 2,
      password: 'private-password',
      confirmPassword: 'private-password',
      signupCode: 'test-only-signup-code-0123456789abcdef',
    );

    expect(submitted?['role'], 'barangay_admin');
    expect(submitted?['signup_code'], 'test-only-signup-code-0123456789abcdef');
    expect(submitted?['hospital_id'], 1);
    expect(submitted?['barangay_id'], 2);
  });
}
