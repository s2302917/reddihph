import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/controllers/sos_controller.dart';
import 'package:reddiph/main.dart';
import 'package:reddiph/models/emergency.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/views/screens/opening_screen.dart';
import 'package:reddiph/views/screens/login_screen.dart';
import 'package:reddiph/views/screens/portal_screen.dart';
import 'package:reddiph/views/screens/sos_confirmed_screen.dart';
import 'package:reddiph/views/screens/sos_sending_screen.dart';

void main() {
  testWidgets('Reddiph app launches and displays the portal screen',
      (WidgetTester tester) async {
    await tester.pumpWidget(const ReddiphApp());

    expect(find.byType(OpeningScreen), findsOneWidget);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
    final heartbeat = tester.widget<ScaleTransition>(
      find.byKey(const ValueKey('opening-heartbeat')),
    );
    expect(heartbeat.scale.value, greaterThan(1));

    await tester.pump(const Duration(milliseconds: 450));
    expect(heartbeat.scale.value, greaterThan(1));

    await tester.pump(const Duration(milliseconds: 700));
    expect(heartbeat.scale.value, greaterThan(1));

    await tester.pump(const Duration(milliseconds: 1900));
    await tester.pumpAndSettle();

    expect(find.byType(ReddiphApp), findsOneWidget);
    expect(find.byType(PortalScreen), findsOneWidget);
  });

  testWidgets('Portal screen fits on a compact phone viewport without overflow',
      (WidgetTester tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 640));
    addTearDown(() async => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(const MaterialApp(home: PortalScreen()));
    await tester.pumpAndSettle();

    expect(find.byType(PortalScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Barangay Admin is available from the role selection screen',
      (WidgetTester tester) async {
    await tester.binding.setSurfaceSize(const Size(402, 874));
    addTearDown(() async => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(const MaterialApp(home: PortalScreen()));

    expect(find.text('Barangay Admin'), findsOneWidget);
    expect(roles.last.apiRole, 'barangay_admin');

    await tester.tap(find.text('Barangay Admin'));
    await tester.pumpAndSettle();

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Barangay Admin login'), findsOneWidget);
    expect(
        find.textContaining('Create a Barangay Admin account'), findsNothing);
  });

  testWidgets('Every role uses the same clean email and password login form',
      (WidgetTester tester) async {
    for (final role in roles) {
      await tester.pumpWidget(
        MaterialApp(home: LoginScreen(role: role)),
      );
      await tester.pumpAndSettle();

      expect(find.text(role.loginTitle), findsOneWidget);
      expect(find.text('Email'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
      expect(find.byType(TextField), findsNWidgets(2));
      expect(find.text('Forgot password?'), findsOneWidget);
      final forgotPassword = tester.widget<Text>(
        find.text('Forgot password?'),
      );
      expect(forgotPassword.style?.fontSize, 13);
      expect(forgotPassword.style?.decoration, isNot(TextDecoration.underline));
      expect(find.text('Report your own emergency'), findsNothing);
      expect(
        find.text('Don Salvador Benedicto Memorial\nDistrict Hospital'),
        findsNothing,
      );
      if (role.name != 'Barangay Admin') {
        final createAccount = tester.widget<Text>(
          find.textContaining('Create a ${role.name} account'),
        );
        expect(createAccount.style?.fontSize, 13);
        expect(
          createAccount.style?.decoration,
          isNot(TextDecoration.underline),
        );
      }

      await tester.tap(find.text('Forgot password?'));
      await tester.pumpAndSettle();
      expect(find.text('Forgot password'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
    }
  });

  testWidgets('SOS sending screen auto-confirms after the 3 second countdown',
      (WidgetTester tester) async {
    SosController.I.reset();
    SosController.I.send();

    await tester.pumpWidget(const MaterialApp(home: SosSendingScreen()));
    await tester.pump();
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();

    expect(SosController.I.stage, EmergencyStage.confirmed);
    expect(find.byType(SosConfirmedScreen), findsOneWidget);
  });
}
