import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/role.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../dashboards/volunteer_community_tab.dart';
import '../dashboards/volunteer_history_tab.dart';
import '../dashboards/volunteer_home.dart';
import '../dashboards/volunteer_map_tab.dart';
import '../dashboards/volunteer_profile_tab.dart';
import '../widgets/common.dart';
import '../widgets/volunteer_widgets.dart';
import 'portal_screen.dart';
import 'sos_screen.dart';

class VolunteerShell extends StatefulWidget {
  final int initialTab;
  const VolunteerShell({super.key, this.initialTab = 0});

  @override
  State<VolunteerShell> createState() => _VolunteerShellState();
}

class _VolunteerShellState extends State<VolunteerShell>
    with WidgetsBindingObserver {
  late int tab = widget.initialTab;
  final Role _role = AuthController.I.role!;
  Timer? _locationTimer;
  bool _publishingLocation = false;

  static const _items = <(IconData, String)>[
    (Icons.home, 'Request'),
    (Icons.map_outlined, 'Map'),
    (Icons.groups_outlined, 'Community'),
    (Icons.history, 'History'),
    (Icons.account_circle_outlined, 'Profile'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _publishLocation());
    _locationTimer = Timer.periodic(
      const Duration(minutes: 2),
      (_) => _publishLocation(),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _publishLocation();
  }

  Future<void> _publishLocation() async {
    if (_publishingLocation ||
        WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || AuthController.I.user?['is_available'] == false) {
      return;
    }

    _publishingLocation = true;
    try {
      final position = await LocationService.currentPosition();
      await MobileApiClient.I.updateVolunteerLocation(
        token: token,
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } on LocationUnavailableException catch (error) {
      debugPrint('Volunteer location is unavailable: ${error.message}');
    } on MobileApiException catch (error) {
      debugPrint('Volunteer location update failed: ${error.message}');
    } on Exception catch (error) {
      debugPrint('Volunteer location update failed: $error');
    } finally {
      _publishingLocation = false;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _locationTimer?.cancel();
    super.dispose();
  }

  void _openSos() => Nav.push(context, SosScreen(role: _role));

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
        return VolunteerMapTab(onBack: () => _select(0));
      case 2:
        return const VolunteerCommunityTab();
      case 3:
        return VolunteerHistoryTab(onBack: () => _select(0));
      case 4:
        return VolunteerProfileTab(onLogout: _logout);
      default:
        return VolunteerHome(role: _role, onOpenMap: () => _select(1));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: SafeArea(
        top: tab != 1, // Map is full-bleed
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
                              color: i == tab ? kVol : Colors.black54),
                          Text(_items[i].$2,
                              style: TextStyle(
                                  fontSize: 8,
                                  fontWeight: i == tab
                                      ? FontWeight.w800
                                      : FontWeight.w500,
                                  color: i == tab ? kVol : Colors.black54)),
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
