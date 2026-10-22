import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/nurse_portal.dart';
import '../dashboards/nurse_calendar_tab.dart';
import '../dashboards/nurse_cases_tab.dart';
import '../dashboards/nurse_home.dart';
import '../dashboards/nurse_profile_tab.dart';
import '../dashboards/nurse_reports_tab.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';
import 'portal_screen.dart';
import 'sos_screen.dart';

class NurseShell extends StatefulWidget {
  final int initialTab;
  const NurseShell({super.key, this.initialTab = 0});

  @override
  State<NurseShell> createState() => _NurseShellState();
}

class _NurseShellState extends State<NurseShell> {
  late int tab = widget.initialTab;

  static const _items = <(IconData, String)>[
    (Icons.checklist, 'Prep'),
    (Icons.hotel_outlined, 'Beds'),
    (Icons.swap_horiz, 'Handoff'),
    (Icons.calendar_today, 'Schedule'),
    (Icons.settings_outlined, 'Settings'),
  ];

  void _openSos() => Nav.push(context, SosScreen(role: AuthController.I.role!));

  void _select(int i) {
    if (i == tab) return;
    HapticFeedback.selectionClick();
    setState(() => tab = i);
  }

  void _logout() {
    final token = AuthController.I.user?['token'];
    if (token is String) NursePortalCache.clear(token);
    AuthController.I.logout();
    Nav.resetTo(context, const PortalScreen());
  }

  Widget _body() {
    switch (tab) {
      case 1:
        return const NurseCasesTab();
      case 2:
        return const NurseReportsTab();
      case 3:
        return const NurseCalendarTab();
      case 4:
        return NurseProfileTab(onBack: () => _select(0), onLogout: _logout);
      default:
        return const NurseHome();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: SafeArea(
        top: true,
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
                              size: 19,
                              color: i == tab ? kNurse : Colors.black54),
                          Text(_items[i].$2,
                              style: TextStyle(
                                  fontSize: 8.5,
                                  fontWeight: i == tab
                                      ? FontWeight.w800
                                      : FontWeight.w500,
                                  color: i == tab ? kNurse : Colors.black54)),
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
