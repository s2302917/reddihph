import 'dart:async';

import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../widgets/common.dart';
import 'shell.dart';
import 'sos_confirmed_screen.dart';

class SosSendingScreen extends StatefulWidget {
  const SosSendingScreen({super.key});

  @override
  State<SosSendingScreen> createState() => _SosSendingScreenState();
}

class _SosSendingScreenState extends State<SosSendingScreen> {
  final controller = SosController.I;

  // A ValueNotifier means only the number inside the circle rebuilds each
  // second, not the whole screen.
  final ValueNotifier<int> _countdown = ValueNotifier<int>(3);
  Timer? _timer;
  bool _leaving = false; // blocks double-taps from pushing two routes

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_countdown.value > 1) {
        _countdown.value--;
        return;
      }

      t.cancel();
      continueSos();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _countdown.dispose();
    super.dispose();
  }

  bool _beginLeave() {
    if (_leaving) return false;
    _leaving = true;
    _timer?.cancel();
    return true;
  }

  void continueSos() {
    if (!_beginLeave()) return;
    controller.confirm();
    controller.submitReport();
    Nav.replace(context, const SosConfirmedScreen());
  }

  void cancelSos() {
    if (!_beginLeave()) return;
    controller.reset();
    Nav.resetTo(context, const Shell(initialTab: 0));
  }

  void openTab(int tab) {
    if (!_beginLeave()) return;
    Nav.resetTo(context, Shell(initialTab: tab));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      PulseRing(
                        color: kResidentBrand,
                        size: 190,
                        child: _CountdownCircle(countdown: _countdown),
                      ),
                      const SizedBox(height: 22),
                      const Text(
                        'Sending your SOS...',
                        style: TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 9),
                      const Text(
                        'Your location and household info are being\n'
                        'shared with the nearest responder team',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11,
                          height: 1.35,
                          color: Colors.black38,
                        ),
                      ),
                      const SizedBox(height: 80),
                      Row(
                        children: [
                          Expanded(
                            child: Pressable(
                              child: SizedBox(
                                height: 58,
                                child: OutlinedButton(
                                  onPressed: cancelSos,
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: Colors.black,
                                    side:
                                        const BorderSide(color: Colors.black12),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                  ),
                                  child: const Text(
                                    'CANCEL',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Pressable(
                              child: SizedBox(
                                height: 58,
                                child: FilledButton(
                                  onPressed: continueSos,
                                  style: FilledButton.styleFrom(
                                    backgroundColor: kResidentBrand,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                  ),
                                  child: const Text(
                                    'CONTINUE',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
            if (AuthController.I.role?.name == 'Resident')
              ResidentBottomBar(
                onHome: () => openTab(0),
                onStatus: () => openTab(1),
                onProfile: () => openTab(2),
              ),
          ],
        ),
      ),
    );
  }
}

class _CountdownCircle extends StatelessWidget {
  final ValueNotifier<int> countdown;
  const _CountdownCircle({required this.countdown});

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 190,
        height: 190,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // Static blurred shadow lives in its own layer: it is painted once
            // and never re-rasterised when the number changes.
            const RepaintBoundary(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: kResidentBrand,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x33000000),
                      blurRadius: 22,
                      offset: Offset(0, 8),
                    ),
                  ],
                ),
                child: SizedBox(width: 190, height: 190),
              ),
            ),
            RepaintBoundary(
              child: ValueListenableBuilder<int>(
                valueListenable: countdown,
                builder: (_, value, __) => AnimatedSwitcher(
                  duration: kMedium,
                  transitionBuilder: (child, anim) => FadeTransition(
                    opacity: anim,
                    child: ScaleTransition(scale: anim, child: child),
                  ),
                  child: Text(
                    '$value',
                    key: ValueKey<int>(value),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 52,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
}
