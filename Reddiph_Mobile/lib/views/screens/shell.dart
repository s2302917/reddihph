import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/emergency.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../dashboards/doctor_home.dart';
import '../dashboards/nurse_home.dart';
import '../dashboards/resident_home.dart';
import '../dashboards/responder_home.dart';
import '../dashboards/volunteer_home.dart';
import '../dashboards/barangay_admin_home.dart';
import '../widgets/common.dart';
import '../widgets/open_street_map.dart';
import '../../services/open_street_map_api.dart';
import 'portal_screen.dart';
import 'sos_screen.dart';
import 'sos_sending_screen.dart';
import 'tracking_screen.dart';
import 'resident_incident_report_screen.dart';
import 'resident_report_history_screen.dart';
import '../dashboards/responder_map.dart';
import '../dashboards/responder_queue.dart';

import '../screens/doctor_shell.dart';
import 'nurse_shell.dart';
import 'volunteer_shell.dart';

const _residentColor = kResidentBrand;

class Shell extends StatefulWidget {
  final int initialTab;

  const Shell({super.key, this.initialTab = 0});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  final role = AuthController.I.role!;
  late int tab = widget.initialTab;
  late final bool _isResident = role.name == 'Resident';
  Timer? _responderAlertTimer;
  bool _checkingResponderAlerts = false;
  bool _responderAlertDialogOpen = false;

  @override
  void initState() {
    super.initState();
    unawaited(SosController.I.loadActiveIncident());
    if (role.name == 'Responder') {
      unawaited(_pollResponderAlerts());
      _responderAlertTimer = Timer.periodic(
        const Duration(seconds: 20),
        (_) => _pollResponderAlerts(),
      );
    }
  }

  @override
  void dispose() {
    _responderAlertTimer?.cancel();
    super.dispose();
  }

  Future<void> _pollResponderAlerts() async {
    final token = AuthController.I.user?['token'] as String?;
    if (!mounted ||
        token == null ||
        _checkingResponderAlerts ||
        _responderAlertDialogOpen) {
      return;
    }
    _checkingResponderAlerts = true;
    try {
      double? latitude;
      double? longitude;
      try {
        final position = await LocationService.currentPosition();
        latitude = position.latitude;
        longitude = position.longitude;
      } on LocationUnavailableException {
        // Continue checking queued alerts even when GPS is temporarily unavailable.
      }
      final alerts = await MobileApiClient.I.responderAlerts(
        token: token,
        latitude: latitude,
        longitude: longitude,
      );
      if (mounted && alerts.isNotEmpty && !_responderAlertDialogOpen) {
        await _showResponderAlert(token, alerts.first);
      }
    } on MobileApiException catch (error) {
      debugPrint('Responder alert polling failed: ${error.message}');
    } finally {
      _checkingResponderAlerts = false;
    }
  }

  Future<void> _showResponderAlert(String token, ResponderAlert alert) async {
    _responderAlertDialogOpen = true;
    unawaited(SystemSound.play(SystemSoundType.alert));
    HapticFeedback.vibrate();
    final soundTimer = Timer.periodic(const Duration(seconds: 2), (_) {
      unawaited(SystemSound.play(SystemSoundType.alert));
      HapticFeedback.vibrate();
    });
    try {
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => PopScope(
          canPop: false,
          child: AlertDialog(
            icon: const Icon(Icons.emergency, color: kSos, size: 36),
            title: const Text('New emergency dispatch'),
            content: Text(
              '${alert.incidentType}\n'
              '${alert.barangay ?? alert.location}\n'
              'Case #${alert.incidentId}'
              '${alert.etaMinutes == null ? '' : '\nETA ${alert.etaMinutes} min'}',
            ),
            actions: [
              FilledButton(
                onPressed: () async {
                  try {
                    await MobileApiClient.I.acknowledgeResponderAlert(
                      token: token,
                      alertId: alert.alertId,
                    );
                    if (dialogContext.mounted) {
                      Navigator.of(dialogContext).pop();
                    }
                  } on MobileApiException catch (error) {
                    if (dialogContext.mounted) {
                      ScaffoldMessenger.of(dialogContext).showSnackBar(
                        SnackBar(content: Text(error.message)),
                      );
                    }
                  }
                },
                child: const Text('STOP ALERT AND ACCEPT'),
              ),
            ],
          ),
        ),
      );
    } finally {
      soundTimer.cancel();
      _responderAlertDialogOpen = false;
    }
  }

  // Role never changes while the shell is alive, so build these once.
  static const _responderDestinations = <Widget>[
    NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
    NavigationDestination(icon: Icon(Icons.update), label: 'Incoming'),
    NavigationDestination(icon: Icon(Icons.map_outlined), label: 'Map'),
    NavigationDestination(icon: Icon(Icons.account_circle), label: 'Profile'),
  ];

  late final List<Widget> _destinations = role.name == 'Responder'
      ? _responderDestinations
      : [
          NavigationDestination(
            icon: const Icon(Icons.home),
            label: role.name == 'Volunteer' ? 'Request' : 'Home',
          ),
          NavigationDestination(
            icon: const Icon(Icons.history),
            label: _isResident ? 'Status' : 'History',
          ),
          const NavigationDestination(
            icon: Icon(Icons.account_circle),
            label: 'Profile',
          ),
        ];
  void _sendSos() {
    final sos = SosController.I;
    if (sos.incidentId != null &&
        sos.stage != EmergencyStage.idle &&
        sos.stage != EmergencyStage.completed) {
      if (_isResident) {
        _openLiveStatus();
      } else {
        Nav.push(context, const TrackingScreen());
      }
      return;
    }
    if (_isResident) {
      sos.send();
      Nav.push(context, const SosSendingScreen());
      return;
    }

    SosController.I.send();
    Nav.push(context, const SosSendingScreen());
  }

  void _openSos() => Nav.push(context, SosScreen(role: role));

  void _openResidentIncidentReport() {
    final sos = SosController.I;
    if (sos.stage != EmergencyStage.idle &&
        sos.stage != EmergencyStage.completed) {
      Nav.push(context, const TrackingScreen());
      return;
    }
    Nav.push(context, const ResidentIncidentReportScreen());
  }

  void _logout() {
    SosController.I.reset();
    AuthController.I.logout();
    Nav.resetTo(context, const PortalScreen());
  }

  void _selectTab(int index) {
    if (index == tab) return;
    HapticFeedback.selectionClick();
    setState(() => tab = index);
  }

  void _openLiveStatus() => Nav.push(context, const ResidentLiveStatusScreen());

  Widget _residentBody() {
    switch (tab) {
      case 1:
        return ResidentReportHistoryScreen(
          token: AuthController.I.user?['token'] as String?,
        );
      case 2:
        return const ResidentProfileHome(roleColor: _residentColor);
      default:
        return ResidentHome(
          role: role,
          onSos: _sendSos,
          onReportIncident: _openResidentIncidentReport,
          onViewLiveStatus: _openLiveStatus,
        );
    }
  }

  Widget _responderBody() {
    switch (tab) {
      case 1:
        return const ResponderQueueTab();
      case 2:
        return const ResponderMapTab();
      case 3:
        return Center(
          child: Pressable(
            child: RoleColorButton(
              color: role.color,
              onPressed: _logout,
              child: const Text('Log out'),
            ),
          ),
        );
      default:
        return const ResponderHome();
    }
  }

  Widget _body() {
    if (_isResident) return _residentBody();
    if (role.name == 'Responder') return _responderBody();

    if (tab == 1) {
      return const Center(child: Text('No history yet'));
    }

    if (tab == 2) {
      return Center(
        child: Pressable(
          child: RoleColorButton(
            color: role.color,
            onPressed: _logout,
            child: const Text('Log out'),
          ),
        ),
      );
    }

    switch (role.name) {
      case 'Responder':
        return const ResponderHome();
      case 'Doctor':
        return const DoctorHome();
      case 'Nurse':
        return const NurseHome();
      default:
        return VolunteerHome(role: role);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (role.name == 'Barangay Admin') {
      return BarangayAdminHome(onLogout: _logout);
    }

    if (role.name == 'Doctor') return const DoctorShell();
    if (role.name == 'Nurse') return const NurseShell();
    if (role.name == 'Volunteer') return const VolunteerShell();

    // Responders keep SOS on Home and Incoming only; the Map and Profile tabs hide it.
    final showFab = _isResident ? tab == 2 : tab < 2;

    return Scaffold(
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          switchInCurve: Curves.easeOut,
          switchOutCurve: Curves.easeIn,
          layoutBuilder: expandLayout,
          child: KeyedSubtree(key: ValueKey<int>(tab), child: _body()),
        ),
      ),
      // One FAB that scales in/out instead of two copy-pasted ones popping in.
      floatingActionButton: ExcludeSemantics(
        excluding: !showFab,
        child: IgnorePointer(
          ignoring: !showFab,
          child: AnimatedScale(
            scale: showFab ? 1 : 0,
            duration: kMedium,
            curve: showFab ? Curves.easeOutBack : Curves.easeIn,
            child: FloatingActionButton(
              heroTag: null, // stops a Hero flight between old/new Shell
              backgroundColor: kSos,
              shape: const CircleBorder(),
              onPressed: _isResident ? _sendSos : _openSos,
              child: const Text(
                'SOS',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        animationDuration: const Duration(milliseconds: 300),
        onDestinationSelected: _selectTab,
        destinations: _destinations,
      ),
    );
  }
}

class ResidentStatusHome extends StatelessWidget {
  const ResidentStatusHome({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = SosController.I;

    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        return AnimatedSwitcher(
          duration: kMedium,
          layoutBuilder: expandLayout,
          child: controller.details != null &&
                  controller.stage != EmergencyStage.idle
              ? _ActiveStatus(
                  key: const ValueKey('active'),
                  incident: controller.details!,
                  live: controller.reportError == null,
                )
              : const _EmptyStatus(key: ValueKey('empty')),
        );
      },
    );
  }
}

class ResidentLiveStatusScreen extends StatelessWidget {
  const ResidentLiveStatusScreen({super.key});

  @override
  Widget build(BuildContext context) => const Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(child: ResidentStatusHome()),
      );
}

class _EmptyStatus extends StatelessWidget {
  const _EmptyStatus({super.key});

  @override
  Widget build(BuildContext context) => const Center(
        child: Padding(
          padding: EdgeInsets.all(28),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.history, size: 54, color: Colors.black26),
              SizedBox(height: 14),
              Text(
                'No active emergency',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              SizedBox(height: 6),
              Text(
                'Your active emergency status will appear here.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 11, color: Colors.black54),
              ),
            ],
          ),
        ),
      );
}

class _ActiveStatus extends StatelessWidget {
  final IncidentDetails incident;
  final bool live;
  const _ActiveStatus({
    super.key,
    required this.incident,
    required this.live,
  });

  static const _card = BoxDecoration(
    border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
    borderRadius: BorderRadius.all(Radius.circular(8)),
  );

  int get _currentStep {
    if (incident.hospitalArrivedAt != null) return 6;
    if (incident.hospitalSentAt != null || incident.sentToHospital) return 4;
    if (incident.patientPickedUpAt != null) return 3;
    if (incident.responderArrivedAt != null) return 2;
    if (incident.responderAssignedAt != null ||
        incident.responderName != null) {
      return 1;
    }
    return 0;
  }

  String get _headline {
    if (incident.sentToHospital || incident.patientPickedUpAt != null) {
      return 'Help is confirmed';
    }
    if (incident.responderArrivedAt != null) {
      return 'A responder is checking on you';
    }
    if (incident.responderName != null) {
      return 'A responder is coming to check on you';
    }
    return 'Alert sent · waiting for responder';
  }

  String _time(DateTime? time) {
    if (time == null) return '';
    final localTime = time.toLocal();
    return '${localTime.hour.toString().padLeft(2, '0')}:'
        '${localTime.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final incidentPoint =
        incident.latitude != null && incident.longitude != null
            ? LatLng(incident.latitude!, incident.longitude!)
            : null;
    final responderPoint = incident.responderLatitude != null &&
            incident.responderLongitude != null
        ? LatLng(incident.responderLatitude!, incident.responderLongitude!)
        : null;
    final hospitalPoint =
        incident.hospitalLatitude != null && incident.hospitalLongitude != null
            ? LatLng(incident.hospitalLatitude!, incident.hospitalLongitude!)
            : null;
    final eventTimes = [
      incident.reportedAt ?? SosController.I.sentAt,
      incident.responderAcknowledgedAt ?? incident.responderAssignedAt,
      incident.responderArrivedAt,
      incident.patientPickedUpAt,
      incident.hospitalSentAt,
      incident.hospitalArrivedAt,
    ];
    const eventLabels = [
      'Report sent',
      'Responder on the way',
      'Responder checking on you',
      'Help confirmed',
      'On the way to the hospital',
      'Arrived',
    ];
    final currentStep = _currentStep;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Status',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: live ? const Color(0xFFE3F2E1) : const Color(0xFFFFF3E0),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.circle,
                    color: live
                        ? const Color(0xFF55A65B)
                        : const Color(0xFFE58A24),
                    size: 8,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    live ? 'LIVE' : 'UPDATE DELAYED',
                    style: TextStyle(
                      color: live
                          ? const Color(0xFF245B2C)
                          : const Color(0xFF8A4B00),
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          'Report #${incident.incidentId} · '
          '${incident.barangay ?? incident.location}',
          style: const TextStyle(fontSize: 10, color: Colors.black54),
        ),
        const SizedBox(height: 14),
        DecoratedBox(
          decoration: BoxDecoration(
            color: const Color(0xFFADB5BA),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _headline,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        incident.sentToHospital
                            ? 'estimated arrival at the hospital'
                            : 'estimated arrival at the emergency location',
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 9,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  incident.etaMinutes == null
                      ? '—'
                      : '${incident.etaMinutes} min',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        SizedBox(
          height: 150,
          child: OpenStreetMapView(
            key: ValueKey('resident-status-map-${incident.incidentId}'),
            radius: 10,
            center:
                responderPoint ?? incidentPoint ?? OpenStreetMapApi.townCenter,
            onTap: () => Nav.push(context, const TrackingScreen()),
            markers: [
              if (responderPoint != null)
                OpenStreetMapMarker(
                  point: responderPoint,
                  label: incident.responderName ?? 'Responder',
                  color: kResponderBrand,
                  icon: Icons.support_agent,
                ),
              if (incidentPoint != null)
                OpenStreetMapMarker(
                  point: incidentPoint,
                  label: 'Emergency location',
                  color: kSos,
                  icon: Icons.emergency,
                ),
              if (hospitalPoint != null)
                OpenStreetMapMarker(
                  point: hospitalPoint,
                  label: incident.hospital ?? 'Hospital',
                  color: Colors.blueGrey,
                  icon: Icons.local_hospital,
                ),
            ],
          ),
        ),
        DecoratedBox(
          key: const ValueKey('resident-live-status-timeline'),
          decoration: _card,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 12, 10),
            child: Column(
              children: [
                for (var index = 0; index < eventLabels.length; index++)
                  SizedBox(
                    height: 28,
                    child: Row(
                      children: [
                        SizedBox(
                          width: 24,
                          child: Column(
                            children: [
                              Expanded(
                                child: Container(
                                  width: 2,
                                  color: index > 0 && index <= currentStep
                                      ? const Color(0xFF7DBB75)
                                      : index == 0
                                          ? Colors.transparent
                                          : Colors.black12,
                                ),
                              ),
                              Container(
                                width: 16,
                                height: 16,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: index < currentStep
                                      ? const Color(0xFF79B96F)
                                      : index == currentStep
                                          ? kSos
                                          : const Color(0xFFD9D9D9),
                                ),
                                child: index < currentStep
                                    ? const Icon(
                                        Icons.check,
                                        size: 11,
                                        color: Colors.white,
                                      )
                                    : null,
                              ),
                              Expanded(
                                child: Container(
                                  width: 2,
                                  color: index < currentStep
                                      ? const Color(0xFF7DBB75)
                                      : index == eventLabels.length - 1
                                          ? Colors.transparent
                                          : Colors.black12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            eventLabels[index],
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: index == currentStep
                                  ? FontWeight.w700
                                  : FontWeight.normal,
                              color: index <= currentStep
                                  ? Colors.black
                                  : Colors.black54,
                            ),
                          ),
                        ),
                        if (_time(eventTimes[index]).isNotEmpty)
                          Text(
                            _time(eventTimes[index]),
                            style: const TextStyle(
                              fontSize: 8,
                              color: Colors.black54,
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        Btn(
          'VIEW LIVE STATUS',
          kResidentBrand,
          fontSize: 14,
          onTap: () => Nav.push(context, const TrackingScreen()),
        ),
      ],
    );
  }
}

class ResidentProfileHome extends StatelessWidget {
  final Color roleColor;

  const ResidentProfileHome({
    super.key,
    required this.roleColor,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        const Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  SizedBox(height: 3),
                  Text(
                    'Sean Occena · Brgy. San Isidro',
                    style: TextStyle(fontSize: 10),
                  ),
                ],
              ),
            ),
            CircleAvatar(
              radius: 14,
              backgroundColor: _residentColor,
              child: Text(
                'S',
                style: TextStyle(color: Colors.white, fontSize: 11),
              ),
            ),
          ],
        ),
        const SizedBox(height: 22),
        const Text(
          'Household members',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _profileCard('Sean Occena', '24 · Main household member', roleColor),
        _profileCard('Aries Occena', '43 · Household member', roleColor),
        const SizedBox(height: 6),
        Pressable(
          child: OutlinedButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.add_circle_outline, size: 17),
            label: const Text('Add household member'),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Emergency contacts',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _profileCard(
          'Brgy. San Isidro Health Center',
          'Barangay hotline',
          roleColor,
          icon: Icons.local_hospital,
        ),
        _profileCard(
          'Juan Ramos',
          'Next of kin',
          roleColor,
          icon: Icons.phone,
        ),
        const SizedBox(height: 18),
        const Text(
          'Settings',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _setting('Notifications', 'On'),
        _setting('Language', 'Filipino'),
        _setting('Log out', '', danger: true, onTap: () {
          AuthController.I.logout();
          Nav.resetTo(context, const PortalScreen());
        }),
      ],
    );
  }

  Widget _profileCard(
    String name,
    String detail,
    Color color, {
    IconData icon = Icons.person,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.all(10),
      decoration: const BoxDecoration(
        border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
        borderRadius: BorderRadius.all(Radius.circular(8)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 14,
            backgroundColor: color,
            child: Icon(icon, size: 15, color: Colors.white),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  detail,
                  style: const TextStyle(fontSize: 8, color: Colors.black54),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _setting(
    String title,
    String value, {
    bool danger = false,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: Colors.black12)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 10,
                  color: danger ? kSos : Colors.black,
                  fontWeight: danger ? FontWeight.bold : FontWeight.normal,
                ),
              ),
            ),
            if (value.isNotEmpty)
              Text(
                value,
                style: const TextStyle(fontSize: 10, color: Colors.black54),
              ),
          ],
        ),
      ),
    );
  }
}
