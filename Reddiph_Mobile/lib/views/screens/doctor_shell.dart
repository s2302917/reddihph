import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../dashboards/doctor_calendar_tab.dart';
import '../dashboards/doctor_cases_tab.dart';
import '../dashboards/doctor_home.dart';
import '../dashboards/doctor_map_tab.dart';
import '../dashboards/doctor_profile_tab.dart';
import '../dashboards/doctor_reports_tab.dart';
import '../widgets/common.dart';
import 'portal_screen.dart';
import 'sos_screen.dart';

class DoctorShell extends StatefulWidget {
  final int initialTab;
  const DoctorShell({super.key, this.initialTab = 0});

  @override
  State<DoctorShell> createState() => _DoctorShellState();
}

class _DoctorShellState extends State<DoctorShell> {
  late int tab = widget.initialTab;

  static const _items = <(IconData, String)>[
    (Icons.home, 'Home'),
    (Icons.medical_services_outlined, 'Cases'),
    (Icons.map_outlined, 'Map'),
    (Icons.calendar_today, 'Calendar'),
    (Icons.bar_chart, 'History'),
    (Icons.account_circle_outlined, 'Profile'),
  ];

  void _openSos() => Nav.push(context, SosScreen(role: AuthController.I.role!));

  void _select(int i) {
    if (i == tab) return;
    HapticFeedback.selectionClick();
    setState(() => tab = i);
  }

  void _logout() {
    AuthController.I.logout();
    Nav.resetTo(context, const PortalScreen());
  }

  Widget _body() {
    switch (tab) {
      case 1:
        return const DoctorCasesTab();
      case 2:
        return const DoctorMapTab();
      case 3:
        return const DoctorCalendarTab();
      case 4:
        return const DoctorReportsTab();
      case 5:
        return DoctorProfileTab(
          onBack: () => _select(0),
          onLogout: _logout,
        );
      default:
        return const DoctorHome();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: SafeArea(
        top: tab != 1, // Cases tab has a full-bleed navy header
        bottom: false,
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          layoutBuilder: expandLayout,
          child: KeyedSubtree(key: ValueKey<int>(tab), child: _body()),
        ),
      ),
      floatingActionButton: Padding(
        padding: const EdgeInsets.only(bottom: 56),
        child: FloatingActionButton(
          heroTag: null,
          backgroundColor: kSos,
          shape: const CircleBorder(),
          onPressed: _openSos,
          child: const Text('SOS',
              style:
                  TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: SizedBox(
            height: 52,
            child: Material(
              color: Colors.white,
              elevation: 2,
              clipBehavior: Clip.antiAlias,
              shape: const RoundedRectangleBorder(
                borderRadius: BorderRadius.all(Radius.circular(26)),
                side: BorderSide(color: Colors.black12),
              ),
              child: Row(children: [
                for (int i = 0; i < _items.length; i++)
                  Expanded(
                    child: InkWell(
                      onTap: () => _select(i),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(_items[i].$1,
                              size: 17,
                              color: i == tab
                                  ? kDoctorBrand
                                  : Colors.black54),
                          Text(_items[i].$2,
                              style: TextStyle(
                                  fontSize: 8,
                                  fontWeight: i == tab
                                      ? FontWeight.w800
                                      : FontWeight.w500,
                                  color: i == tab
                                      ? kDoctorBrand
                                      : Colors.black54)),
                        ],
                      ),
                    ),
                  ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
