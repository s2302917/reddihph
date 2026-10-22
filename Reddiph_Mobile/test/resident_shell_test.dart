import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/controllers/sos_controller.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/views/screens/shell.dart';

void main() {
  final auth = AuthController.I;
  final sos = SosController.I;

  tearDown(() {
    sos.reset();
    auth.logout();
  });

  testWidgets(
      'resident Status shows report-history access separately from live status',
      (tester) async {
    auth.role = roles.first;
    auth.user = {'full_name': 'Jamie Cruz'};

    await tester.pumpWidget(const MaterialApp(home: Shell()));
    await tester.pump(const Duration(milliseconds: 400));

    await tester.tap(find.text('Status'));
    await tester.pump(const Duration(milliseconds: 350));

    expect(find.text('Sign in to view your report history.'), findsOneWidget);
    expect(find.text('No active emergency'), findsNothing);
    expect(find.text('Home'), findsOneWidget);
    expect(find.text('Profile'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
