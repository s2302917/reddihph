import 'dart:async';

import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/driving_route.dart';
import '../../models/volunteer.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../../services/open_street_map_api.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/open_street_map.dart';

class VolunteerMapTab extends StatefulWidget {
  final VoidCallback onBack;

  const VolunteerMapTab({super.key, required this.onBack});

  @override
  State<VolunteerMapTab> createState() => _VolunteerMapTabState();
}

class _VolunteerMapTabState extends State<VolunteerMapTab>
    with WidgetsBindingObserver {
  VolunteerAssistanceRequest? _request;
  DrivingRoute? _route;
  Map<String, dynamic>? _incident;
  LatLng? _location;
  String? _error;
  bool _loading = true;
  bool _refreshingRoute = false;
  int _recenterSignal = 0;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadAcceptedRequest();
    _refreshTimer = Timer.periodic(const Duration(seconds: 90), (_) {
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        _refreshRoute();
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshRoute();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadAcceptedRequest() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      if (mounted) {
        setState(() {
          _error = 'Sign in again to load your accepted assistance.';
          _loading = false;
        });
      }
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final requests = await MobileApiClient.I.volunteerRequests(token: token);
      final accepted = requests.where((request) => request.status == 'accepted');
      final request = accepted.firstOrNull;
      if (!mounted) return;
      if (request == null) {
        setState(() {
          _request = null;
          _route = null;
          _incident = null;
          _loading = false;
        });
        return;
      }
      setState(() {
        _request = request;
        _loading = false;
      });
      await _refreshRoute();
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _refreshRoute() async {
    final request = _request;
    final token = AuthController.I.user?['token'] as String?;
    if (request == null || token == null || _refreshingRoute) return;

    setState(() {
      _refreshingRoute = true;
      _error = null;
    });
    try {
      final position = await LocationService.currentPosition();
      final location = LatLng(position.latitude, position.longitude);
      final data = await MobileApiClient.I.volunteerAssistanceRoute(
        token: token,
        assistanceId: request.assistanceId,
        latitude: location.latitude,
        longitude: location.longitude,
      );
      if (!mounted) return;
      setState(() {
        _location = location;
        _route = data['route'] as DrivingRoute;
        _incident = data['incident'] as Map<String, dynamic>;
      });
    } on LocationUnavailableException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _refreshingRoute = false);
    }
  }

  List<OpenStreetMapMarker> get _markers {
    final markers = <OpenStreetMapMarker>[];
    final location = _location;
    final request = _request;
    if (location != null) {
      markers.add(OpenStreetMapMarker(
        point: location,
        label: 'You',
        color: kVolunteerBrand,
        icon: Icons.person,
      ));
    }
    if (request?.latitude != null && request?.longitude != null) {
      markers.add(OpenStreetMapMarker(
        point: LatLng(request!.latitude!, request.longitude!),
        label: 'Incident',
        color: kSos,
        icon: Icons.emergency,
      ));
    }
    final responderLatitude = _incident?['responder_latitude'];
    final responderLongitude = _incident?['responder_longitude'];
    if (responderLatitude is num && responderLongitude is num) {
      markers.add(OpenStreetMapMarker(
        point: LatLng(
          responderLatitude.toDouble(),
          responderLongitude.toDouble(),
        ),
        label: _incident?['responder_name'] as String? ?? 'Responder',
        color: kResponderBrand,
        icon: Icons.support_agent,
      ));
    }
    return markers;
  }

  @override
  Widget build(BuildContext context) {
    final request = _request;
    final route = _route;
    final location = _location;
    final center = location ??
        (request?.latitude != null && request?.longitude != null
            ? LatLng(request!.latitude!, request.longitude!)
            : OpenStreetMapApi.townCenter);

    return Stack(
      fit: StackFit.expand,
      children: [
        OpenStreetMapView(
          radius: 0,
          center: center,
          zoom: 15,
          markers: _markers,
          route: route?.geometry ?? const [],
          routeColor: kVolunteerBrand,
          recenterSignal: _recenterSignal,
          followCenter: location != null,
          fitRouteOnUpdate: route != null,
        ),
        Positioned(
          left: 16,
          top: 16,
          child: _MapButton(
            icon: Icons.chevron_left,
            label: 'Back',
            onTap: widget.onBack,
          ),
        ),
        Positioned(
          right: 16,
          top: 16,
          child: _MapButton(
            icon: _refreshingRoute ? Icons.hourglass_top : Icons.my_location,
            label: 'Recenter',
            onTap: () {
              setState(() => _recenterSignal++);
              _refreshRoute();
            },
          ),
        ),
        DraggableScrollableSheet(
          initialChildSize: .38,
          minChildSize: .25,
          maxChildSize: .78,
          snap: true,
          snapSizes: const [.38, .78],
          builder: (context, scrollController) => Material(
            color: Colors.white,
            elevation: 12,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
            clipBehavior: Clip.antiAlias,
            child: ListView(
              controller: scrollController,
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 100),
              children: [
                Center(
                  child: Container(
                    width: 52,
                    height: 5,
                    decoration: BoxDecoration(
                      color: Colors.black26,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                if (_loading)
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.all(18),
                      child: CircularProgressIndicator(),
                    ),
                  )
                else if (request == null)
                  _NoActiveAssistance(onRefresh: _loadAcceptedRequest)
                else
                  _AssistanceDetails(
                    request: request,
                    incident: _incident,
                    route: route,
                    refreshing: _refreshingRoute,
                    error: _error,
                    onRefresh: _refreshRoute,
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _NoActiveAssistance extends StatelessWidget {
  const _NoActiveAssistance({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'No active assistance',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 5),
          const Text(
            'Accept an incident request to see your live route and incident details here.',
            style: TextStyle(fontSize: 9, color: Colors.black54),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: onRefresh,
            icon: const Icon(Icons.refresh, size: 16),
            label: const Text('Refresh requests'),
          ),
        ],
      );
}

class _AssistanceDetails extends StatelessWidget {
  const _AssistanceDetails({
    required this.request,
    required this.incident,
    required this.route,
    required this.refreshing,
    required this.error,
    required this.onRefresh,
  });

  final VolunteerAssistanceRequest request;
  final Map<String, dynamic>? incident;
  final DrivingRoute? route;
  final bool refreshing;
  final String? error;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  request.needsVehicle ? 'Vehicle needed' : request.incidentType,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (route != null)
                SmallPill(
                  route!.distanceLabel,
                  (bg: const Color(0xFFE6E7EB), fg: Colors.black87),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${request.incidentType} · ${request.location}',
            style: const TextStyle(fontSize: 9, color: Colors.black54),
          ),
          const SizedBox(height: 12),
          DCard(
            Row(
              children: [
                const CircleAvatar(
                  radius: 17,
                  backgroundColor: kVolunteerBrand,
                  child: Icon(Icons.person, color: Colors.white, size: 18),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        request.requesterName,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        [
                          request.patientName,
                          if (request.patientAge != null)
                            '${request.patientAge} years',
                        ].join(' · '),
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
            margin: EdgeInsets.zero,
          ),
          const SizedBox(height: 8),
          DCard(
            Column(
              children: [
                _DetailRow('Pickup', request.location),
                const Divider(height: 14),
                _DetailRow(
                  'Hospital',
                  request.hospitalName ?? 'Not assigned',
                ),
                if (route != null) ...[
                  const Divider(height: 14),
                  _DetailRow('Estimated time', '${route!.etaMinutes} min'),
                ],
                if (incident?['responder_name'] is String) ...[
                  const Divider(height: 14),
                  _DetailRow('Responder', incident!['responder_name'] as String),
                ],
                if (request.notes.isNotEmpty) ...[
                  const Divider(height: 14),
                  _DetailRow('Incident notes', request.notes),
                ],
              ],
            ),
            margin: EdgeInsets.zero,
          ),
          if (error != null) ...[
            const SizedBox(height: 8),
            Text(
              error!,
              style: const TextStyle(fontSize: 9, color: kSos),
            ),
          ],
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: refreshing ? null : onRefresh,
              icon: refreshing
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.refresh, size: 16),
              label: Text(refreshing ? 'UPDATING ROUTE' : 'UPDATE ROUTE'),
              style: OutlinedButton.styleFrom(
                foregroundColor: kVolunteerBrand,
                side: const BorderSide(color: kVolunteerBrand),
                textStyle: const TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(fontSize: 8, color: Colors.black54),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      );
}

class _MapButton extends StatelessWidget {
  const _MapButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        elevation: 3,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(22),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 16, color: kVolunteerBrand),
                const SizedBox(width: 5),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
}
