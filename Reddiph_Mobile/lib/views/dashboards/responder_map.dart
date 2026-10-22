import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/driving_route.dart';
import '../../models/responder_dashboard.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../screens/case_detail_screen.dart';
import '../widgets/common.dart';
import '../widgets/open_street_map.dart';
import '../widgets/responder_widgets.dart';

/// Map tab: the active route and, near hospital arrival, upcoming open cases.
class ResponderMapTab extends StatefulWidget {
  const ResponderMapTab({super.key});

  @override
  State<ResponderMapTab> createState() => _ResponderMapTabState();
}

class _ResponderMapTabState extends State<ResponderMapTab> {
  static const _refreshEvery = Duration(seconds: 10);

  Timer? _timer;
  StreamSubscription<Position>? _positionSubscription;
  bool _loading = false;
  LatLng? _me;
  List<ResponderIncident>? _incidents;
  ResponderIncident? _activeCase;
  DrivingRoute? _activeRoute;
  String? _error;
  String? _routeError;
  int? _selectedId;
  int? _routedIncidentId;
  bool? _routedToHospital;
  DateTime? _routeUpdatedAt;

  @override
  void initState() {
    super.initState();
    unawaited(_start());
    _timer = Timer.periodic(_refreshEvery, (_) => _load());
  }

  Future<void> _start() async {
    try {
      final p = await LocationService.currentPosition();
      _me = LatLng(p.latitude, p.longitude);
    } on LocationUnavailableException {
      _me = null;
    }
    _positionSubscription = LocationService.positionStream().listen(
      (position) {
        _me = LatLng(position.latitude, position.longitude);
      },
      onError: (Object error) {
        if (mounted) setState(() => _routeError = error.toString());
      },
    );
    await _load();
  }

  Future<void> _load() async {
    if (_loading) return;
    _loading = true;
    try {
      final token = AuthController.I.user?['token'] as String? ?? '';
      final dashboard = await MobileApiClient.I.responderDashboard(
        token: token,
        latitude: _me?.latitude,
        longitude: _me?.longitude,
      );
      final incidents = await MobileApiClient.I.responderMap(
        token: token,
        latitude: _me?.latitude,
        longitude: _me?.longitude,
      );
      _activeCase = dashboard.activeCase;
      _incidents = incidents;
      _error = null;
      await _refreshActiveRoute(token, dashboard.activeCase);
    } on MobileApiException catch (e) {
      _error = e.message;
    }
    _loading = false;
    if (mounted) setState(() {});
  }

  Future<void> _refreshActiveRoute(
    String token,
    ResponderIncident? incident,
  ) async {
    if (incident == null || _me == null) {
      _activeRoute = null;
      _routeError = null;
      _routedIncidentId = null;
      _routedToHospital = null;
      _routeUpdatedAt = null;
      return;
    }
    if (_routedIncidentId != incident.id ||
        _routedToHospital != incident.sentToHospital) {
      _activeRoute = null;
    }
    final lastUpdated = _routeUpdatedAt;
    if (_routedIncidentId == incident.id &&
        _routedToHospital == incident.sentToHospital &&
        lastUpdated != null &&
        DateTime.now().difference(lastUpdated) < const Duration(minutes: 1)) {
      return;
    }

    try {
      _activeRoute = incident.sentToHospital
          ? await MobileApiClient.I.incidentDirections(
              token: token,
              incidentId: incident.id,
              latitude: _me!.latitude,
              longitude: _me!.longitude,
            )
          : await MobileApiClient.I.responderIncidentDirections(
              token: token,
              incidentId: incident.id,
              latitude: _me!.latitude,
              longitude: _me!.longitude,
            );
      _routedIncidentId = incident.id;
      _routedToHospital = incident.sentToHospital;
      _routeUpdatedAt = DateTime.now();
      _routeError = null;
    } on MobileApiException catch (error) {
      _routeError = error.message;
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _positionSubscription?.cancel();
    super.dispose();
  }

  ResponderIncident? get _selected {
    final activeCase = _activeCase;
    if (activeCase?.id == _selectedId) return activeCase;
    for (final incident in _incidents ?? const <ResponderIncident>[]) {
      if (incident.id == _selectedId) return incident;
    }
    return null;
  }

  Color _pinColor(ResponderIncident incident) => switch (incident.status) {
        'critical' => const Color(0xFFD9382B),
        'urgent' => const Color(0xFFE0A100),
        _ => const Color(0xFF2F6FB5),
      };

  @override
  Widget build(BuildContext context) {
    final incidents = _incidents;
    if (incidents == null) {
      return Center(
        child: _error == null
            ? const CircularProgressIndicator()
            : Text(_error!,
                style: const TextStyle(fontSize: 11, color: Colors.black54)),
      );
    }

    final selected = _selected;
    final activeRoute = _activeRoute;
    final upcomingIsVisible = shouldShowUpcomingResponderCases(
      sentToHospital: _activeCase?.sentToHospital ?? false,
      etaMinutes: activeRoute?.etaMinutes,
    );
    final center = _me ??
        (incidents.isEmpty ? null : incidents.first.point) ??
        const LatLng(14.5995, 120.9842);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Map',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Expanded(
          child: OpenStreetMapView(
            // The map only redraws when the set of incidents changes.
            key: ValueKey(incidents.map((i) => '${i.id}${i.status}').join(',')),
            radius: 10,
            center: center,
            zoom: 14,
            route: activeRoute?.geometry ?? const [],
            routeColor: _activeCase?.sentToHospital == true
                ? kResponderBrown
                : kResidentNavigation,
            fitRouteOnUpdate: true,
            markers: [
              if (_me != null)
                OpenStreetMapMarker(
                  point: _me!,
                  label: 'You',
                  icon: Icons.support_agent,
                  color: kResponderBrown,
                ),
              if (_activeCase?.point case final activePoint?)
                OpenStreetMapMarker(
                  point: activePoint,
                  label: 'Active incident',
                  icon: Icons.emergency,
                  color: _pinColor(_activeCase!),
                  onTap: () => setState(() => _selectedId = _activeCase!.id),
                ),
              if (_activeCase?.sentToHospital == true &&
                  activeRoute?.geometry.isNotEmpty == true)
                OpenStreetMapMarker(
                  point: activeRoute!.geometry.last,
                  label: 'Hospital',
                  icon: Icons.local_hospital,
                  color: kResponderBrown,
                ),
              for (final incident in incidents)
                if (incident.id != _activeCase?.id && upcomingIsVisible)
                  OpenStreetMapMarker(
                    point: incident.point!,
                    label: 'Upcoming: ${incident.type}',
                    icon: Icons.emergency,
                    color: _pinColor(incident),
                    onTap: () => setState(() => _selectedId = incident.id),
                  ),
            ],
          ),
        ),
        if (_routeError != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              _routeError!,
              style: const TextStyle(fontSize: 10, color: Colors.red),
            ),
          ),
        if (upcomingIsVisible)
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'Hospital ETA is 5 minutes or less · upcoming cases are shown',
              style: TextStyle(fontSize: 10, color: Colors.black54),
            ),
          ),
        if (selected != null) ...[
          const SizedBox(height: 10),
          _SelectedCard(
            incident: selected,
            isActiveCase: selected.id == _activeCase?.id,
            onFalseReportClosed: _load,
          ),
        ],
      ]),
    );
  }
}

class _SelectedCard extends StatelessWidget {
  final ResponderIncident incident;
  final bool isActiveCase;
  final Future<void> Function() onFalseReportClosed;
  const _SelectedCard({
    required this.incident,
    required this.isActiveCase,
    required this.onFalseReportClosed,
  });

  Future<void> _openCase(BuildContext context) async {
    final closed = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => CaseDetailScreen(
          item: incident.toQueueItem(),
          activeIncident: isActiveCase ? incident : null,
        ),
      ),
    );
    if (closed == true) await onFalseReportClosed();
  }

  @override
  Widget build(BuildContext context) {
    final sev = severityColors(incident.severity);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFD9382B), width: 1.2),
      ),
      child: Row(children: [
        Expanded(
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(incident.type,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style:
                    const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            const SizedBox(height: 2),
            Text(
                '${incident.location} · ${incident.distanceLabel} · ${incident.agoLabel}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 10, color: Colors.black54)),
            const SizedBox(height: 4),
            Text(incident.severity,
                style: TextStyle(
                    fontSize: 10, fontWeight: FontWeight.w700, color: sev.fg)),
          ]),
        ),
        const SizedBox(width: 10),
        SizedBox(
          height: 30,
          width: 104,
          child: Btn('VIEW CASE', kResponderBrown,
              fontSize: 9, onTap: () => _openCase(context)),
        ),
      ]),
    );
  }
}
