import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  test('nurse profile uses the authenticated nurse portal endpoint', () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'profile': {
              'id': 17,
              'full_name': 'Jamie Nurse',
              'hospital_name': 'District Hospital',
              'department': 'Emergency',
              'is_available': true,
              'notifications_enabled': false,
              'language': 'Filipino',
              'license_number': 'RN-17',
              'next_shift': null,
            },
          }),
          200,
        );
      }),
    );

    final profile = await client.nurseProfile(token: 'signed-token');

    expect(
      request.url.toString(),
      'https://example.test/Reddiph_mobile_api/api/nurses/portal.php',
    );
    expect(request.method, 'POST');
    expect(
      jsonDecode(request.body),
      {'token': 'signed-token', 'action': 'settings'},
    );
    expect(profile.fullName, 'Jamie Nurse');
    expect(profile.notificationsEnabled, isFalse);
  });
}
