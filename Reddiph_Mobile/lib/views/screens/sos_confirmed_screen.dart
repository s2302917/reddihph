import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../widgets/common.dart';
import 'shell.dart';
import 'tracking_screen.dart';

class SosConfirmedScreen extends StatefulWidget {
  const SosConfirmedScreen({super.key});

  @override
  State<SosConfirmedScreen> createState() => _SosConfirmedScreenState();
}

class _SosConfirmedScreenState extends State<SosConfirmedScreen>
    with SingleTickerProviderStateMixin {
  final controller = SosController.I;

  // One controller drives the whole entrance sequence.
  late final AnimationController _enter;
  late final Animation<double> _checkScale;

  @override
  void initState() {
    super.initState();
    _enter = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..forward();
    _checkScale = CurveTween(
      curve: const Interval(0, .5, curve: Curves.easeOutBack),
    ).animate(_enter);
  }

  @override
  void dispose() {
    _enter.dispose();
    super.dispose();
  }

  void openTracking() {
    controller.startTracking();
    if (AuthController.I.role?.name == 'Resident') {
      Nav.replace(context, const ResidentLiveStatusScreen());
      return;
    }
    Nav.replace(context, const TrackingScreen());
  }

  void openTab(int tab) {
    Nav.resetTo(context, Shell(initialTab: tab));
  }

  @override
  Widget build(BuildContext context) {
    final isResident = AuthController.I.role?.name == 'Resident';
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: ListenableBuilder(
          listenable: controller,
          builder: (context, _) => Column(
            children: [
              Expanded(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 28),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        ScaleTransition(
                          scale: _checkScale,
                          child: const DecoratedBox(
                            decoration: BoxDecoration(
                              color: kResidentBrand,
                              shape: BoxShape.circle,
                            ),
                            child: SizedBox(
                              width: 70,
                              height: 70,
                              child: Icon(Icons.check,
                                  color: Colors.white, size: 42),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        StaggerIn(
                          animation: _enter,
                          begin: .15,
                          end: .5,
                          child: Column(
                            children: [
                              Text(
                                controller.details == null
                                    ? 'Sending your SOS'
                                    : controller.details!.responderName == null
                                        ? 'Request received'
                                        : 'Help is on the way',
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                controller.details == null
                                    ? 'Sending your emergency details…'
                                    : 'Case #${controller.details!.incidentId}'
                                        ' · ${controller.details!.barangay ?? controller.details!.location}',
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                    fontSize: 9, color: Colors.black45),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        StaggerIn(
                          animation: _enter,
                          begin: .3,
                          end: .6,
                          child: _InfoRow(
                            '1',
                            controller.details?.responderName == null
                                ? 'Your request is queued for local dispatch'
                                : '${controller.details!.responderName} is assigned to your case',
                          ),
                        ),
                        StaggerIn(
                          animation: _enter,
                          begin: .4,
                          end: .7,
                          child: const _InfoRow('2',
                              'You may get a confirmation call for safety details'),
                        ),
                        StaggerIn(
                          animation: _enter,
                          begin: .5,
                          end: .8,
                          child: const _InfoRow(
                              '3', 'Track their location and ETA anytime'),
                        ),
                        const SizedBox(height: 12),
                        if (controller.reportError != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Column(
                              children: [
                                Text(
                                  controller.reportError!,
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                      color: Colors.red, fontSize: 11),
                                ),
                                TextButton(
                                  onPressed: controller.submitReport,
                                  child: const Text('RETRY SOS'),
                                ),
                              ],
                            ),
                          ),
                        StaggerIn(
                          animation: _enter,
                          begin: .6,
                          end: 1,
                          child: Column(
                            children: [
                              if (controller.details != null)
                                Pressable(
                                  child: SizedBox(
                                    width: double.infinity,
                                    height: 42,
                                    child: FilledButton(
                                      onPressed: openTracking,
                                      style: FilledButton.styleFrom(
                                        backgroundColor: kResidentBrand,
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(6),
                                        ),
                                      ),
                                      child: const Text(
                                        'VIEW LIVE STATUS',
                                        style: TextStyle(fontSize: 10),
                                      ),
                                    ),
                                  ),
                                ),
                              const SizedBox(height: 6),
                              Pressable(
                                child: SizedBox(
                                  width: double.infinity,
                                  height: 42,
                                  child: OutlinedButton(
                                    onPressed: () => openTab(0),
                                    style: OutlinedButton.styleFrom(
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                    ),
                                    child: const Text(
                                      'BACK TO HOME',
                                      style: TextStyle(fontSize: 10),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              if (isResident)
                ResidentBottomBar(
                  onHome: () => openTab(0),
                  onStatus: openTracking,
                  onProfile: () => openTab(2),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String number, text;
  const _InfoRow(this.number, this.text);

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.all(9),
        decoration: const BoxDecoration(
          border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
          borderRadius: BorderRadius.all(Radius.circular(8)),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 10,
              backgroundColor: Colors.white,
              child: Text(number, style: const TextStyle(fontSize: 9)),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(text, style: const TextStyle(fontSize: 8)),
            ),
          ],
        ),
      );
}
