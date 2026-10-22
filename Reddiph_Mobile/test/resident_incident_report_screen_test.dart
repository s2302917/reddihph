import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/views/screens/resident_incident_report_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const locationChannel = MethodChannel('flutter.baseflow.com/geolocator');

  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(locationChannel, (call) async {
      switch (call.method) {
        case 'isLocationServiceEnabled':
          return true;
        case 'checkPermission':
          return 2;
        case 'getCurrentPosition':
          return {
            'latitude': 10.0,
            'longitude': 122.0,
            'accuracy': 1.0,
            'altitude': 0.0,
            'altitude_accuracy': 0.0,
            'heading': 0.0,
            'heading_accuracy': 0.0,
            'speed': 0.0,
            'speed_accuracy': 0.0,
          };
        default:
          throw MissingPluginException(
            'Unexpected geolocator method: ${call.method}',
          );
      }
    });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(locationChannel, null);
  });

  testWidgets('patient name and Age/Sex layout match responder proportions',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      const MaterialApp(home: ResidentIncidentReportScreen()),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Medical'));
    await tester.tap(find.text('NEXT'));
    await tester.pumpAndSettle();

    final nameFieldDecoration = tester.widget<InputDecorator>(
      find.descendant(
        of: find.byKey(const ValueKey('resident-patient-name-field')),
        matching: find.byType(InputDecorator),
      ),
    );
    expect(nameFieldDecoration.decoration.hintText, 'Patient name');
    expect(
      find.byKey(const ValueKey('resident-patient-age-field')),
      findsOneWidget,
    );
    expect(find.text('Sex'), findsOneWidget);
    expect(find.text('Male'), findsOneWidget);
    expect(find.text('Female'), findsOneWidget);

    final nameSize = tester.getSize(
      find.byKey(const ValueKey('resident-patient-name-field')),
    );
    final ageSize = tester.getSize(
      find.byKey(const ValueKey('resident-patient-age-field')),
    );
    final ageColumnSize = tester.getSize(
      find.byKey(const ValueKey('resident-patient-age-column')),
    );
    final sexColumnSize = tester.getSize(
      find.byKey(const ValueKey('resident-patient-sex-column')),
    );

    expect(
        nameSize.width, greaterThan(ageColumnSize.width + sexColumnSize.width));
    expect(ageSize.height, closeTo(nameSize.height, 0.1));
    expect(ageColumnSize.width / sexColumnSize.width, closeTo(4 / 6, 0.02));
    expect(tester.takeException(), isNull);
  });
}
