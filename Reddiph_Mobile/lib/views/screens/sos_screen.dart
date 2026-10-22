import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../models/emergency.dart';
import '../../models/role.dart';
import '../dashboards/resident_home.dart';
import 'sos_sending_screen.dart';
import 'tracking_screen.dart';

/// SOS page for every signed-in role, reusing the resident reporting widgets.
class SosScreen extends StatelessWidget {
  final Role role;
  const SosScreen({super.key, required this.role});

  void _send(BuildContext context) {
    final controller = SosController.I;
    if (controller.incidentId != null &&
        controller.stage != EmergencyStage.idle &&
        controller.stage != EmergencyStage.completed) {
      Nav.push(context, const TrackingScreen());
      return;
    }
    HapticFeedback.heavyImpact();
    controller.send();
    Nav.push(context, const SosSendingScreen());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('SOS')),
        body: ListView(
          children: [
            const SizedBox(height: 10),
            SosButton(onSos: () => _send(context)),
            const Padding(
              padding: EdgeInsets.all(14),
              child: Text(
                'Sends your location to your barangay team, '
                'then the nearest available responder.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 9, color: Colors.black38),
              ),
            ),
            ReportSection(role: role),
            const SizedBox(height: 30),
          ],
        ),
      );
}
