import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/controllers/sos_controller.dart';
import 'package:reddiph/models/emergency.dart';
import 'package:reddiph/models/role.dart';
import 'package:reddiph/views/dashboards/resident_home.dart';

void main() {
  Future<int> pressFor(WidgetTester tester, Duration hold) async {
    var sent = 0;
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: SosButton(onSos: () => sent++)),
    ));
    final gesture =
        await tester.startGesture(tester.getCenter(find.text('SOS')));
    await tester.pump();
    await tester.pump(hold);
    await gesture.up();
    await tester.pump();
    return sent;
  }

  testWidgets('releasing before 3 seconds does not send', (tester) async {
    expect(await pressFor(tester, const Duration(seconds: 2)), 0);
  });

  testWidgets('holding for 3 seconds sends once', (tester) async {
    expect(await pressFor(tester, const Duration(milliseconds: 3100)), 1);
  });

  testWidgets('does not show a static active report without an incident',
      (tester) async {
    final sos = SosController.I..reset();

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: ReportSection(role: roles.first)),
    ));

    expect(find.text('Your active report'), findsNothing);
    expect(find.text('Your emergency request'), findsNothing);
    sos.reset();
  });

  testWidgets('shows the database incident and assigned responder details',
      (tester) async {
    final sos = SosController.I
      ..stage = EmergencyStage.assigned
      ..incidentId = 22
      ..details = const IncidentDetails(
        incidentId: 22,
        type: 'SOS emergency alert',
        location: 'Purok 4',
        barangay: 'San Isidro',
        hospital: 'District Hospital',
        responderName: 'Responder One',
        transportName: 'RB-04',
        transportType: 'Ambulance',
        etaMinutes: 6,
      );

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: ReportSection(role: roles.first)),
    ));

    expect(find.text('Your active report'), findsOneWidget);
    expect(find.textContaining('Responder: Responder One'), findsOneWidget);
    expect(find.textContaining('Hospital: District Hospital'), findsOneWidget);
    expect(find.text('VIEW LIVE STATUS'), findsOneWidget);
    sos.reset();
  });
}
