import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/controllers/auth_controller.dart';
import 'package:reddiph/controllers/sos_controller.dart';
import 'package:reddiph/core/theme.dart';
import 'package:reddiph/models/emergency.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/views/dashboards/resident_home.dart';

void main() {
  final auth = AuthController.I;
  final sos = SosController.I;

  setUp(() {
    sos.reset();
  });

  tearDown(() {
    sos.reset();
    auth.user = null;
  });

  testWidgets('shows the signed-in resident name and registered barangay',
      (tester) async {
    var reportRequested = false;
    auth.user = {
      'full_name': 'Maya Santos Rivera',
      'barangay_name': 'Brgy. San Isidro',
    };

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ResidentHome(
            role: roles.first,
            onSos: () {},
            onReportIncident: () => reportRequested = true,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Hi, Maya'), findsOneWidget);
    expect(find.textContaining('Brgy. San Isidro'), findsOneWidget);
    expect(find.textContaining('Brgy. Brgy.'), findsNothing);
    expect(find.text('Report for someone else'), findsOneWidget);
    expect(find.text('M'), findsOneWidget);
    final reportButton = find.ancestor(
      of: find.text('Report for someone else'),
      matching: find.byType(OutlinedButton),
    );
    expect(reportButton, findsOneWidget);
    expect(find.descendant(of: reportButton, matching: find.byType(Icon)),
        findsNothing);
    expect(
      tester.widget<Text>(find.text('Report for someone else')).style,
      const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
    );
    expect(roles.first.color, kResidentBrand);
    expect(roles.map((role) => role.color), [
      kResidentBrand,
      kResponderBrand,
      kDoctorBrand,
      kNurseBrand,
      kVolunteerBrand,
      kBarangayAdminBrand,
    ]);
    expect(kResidentBrand, const Color(0xFFA8B0B7));
    expect(kResponderBrand, const Color(0xFFA6623D));
    expect(kDoctorBrand, const Color(0xFF020544));
    expect(kNurseBrand, const Color(0xFF3A4545));
    expect(kVolunteerBrand, const Color(0xFF1F2A37));
    expect(kBarangayAdminBrand, const Color(0xFF491717));
    expect(
      roleButtonShade(kResponderBrand),
      Color.alphaBlend(const Color(0x26000000), kResponderBrand),
    );

    await tester.tap(find.text('Report for someone else'));
    expect(reportRequested, isTrue);
  });

  testWidgets('active report shows only responder and ETA in its status card',
      (tester) async {
    var liveStatusRequested = false;
    auth.user = {
      'full_name': 'Maya Santos Rivera',
      'barangay_name': 'San Isidro',
    };
    sos.incidentId = 19;
    sos.stage = EmergencyStage.assigned;
    sos.details = const IncidentDetails(
      incidentId: 19,
      type: 'Medical emergency',
      location: 'San Isidro',
      responderName: 'Jordan Cruz',
      etaMinutes: 4,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ResidentHome(
            role: roles.first,
            onSos: () {},
            onReportIncident: () {},
            onViewLiveStatus: () => liveStatusRequested = true,
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Jordan Cruz\nETA 4 min'), findsOneWidget);
    expect(find.text('Hospital: Not assigned'), findsNothing);
    expect(find.text('VIEW LIVE STATUS'), findsOneWidget);
    expect(find.text('Report for someone else'), findsNothing);
    await tester.tap(find.text('VIEW LIVE STATUS'));
    expect(liveStatusRequested, isTrue);
  });
}
