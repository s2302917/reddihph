import 'dart:async';

import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/theme.dart';
import '../../models/driving_route.dart';
import '../../models/emergency.dart';
import '../../services/mobile_api_client.dart';
import '../../services/open_street_map_api.dart';
import '../widgets/open_street_map.dart';

class TrackingScreen extends StatefulWidget {
  const TrackingScreen({super.key});

  @override
  State<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends State<TrackingScreen> {
  final controller = SosController.I;
  final Distance _distance = const Distance();
  bool _alerting = false;
  DrivingRoute? _route;
  String? _routeError;
  int? _routeIncidentId;
  double? _routeResponderLatitude;
  double? _routeResponderLongitude;
  double? _routeHospitalLatitude;
  double? _routeHospitalLongitude;
  bool _routeSentToHospital = false;
  bool _loadingRoute = false;
  int _mapRecenterSignal = 0;

  @override
  void initState() {
    super.initState();
    controller.addListener(_incidentChanged);
    unawaited(controller.refreshDetails());
    unawaited(_loadRouteIfChanged());
  }

  @override
  void dispose() {
    controller.removeListener(_incidentChanged);
    super.dispose();
  }

  void _incidentChanged() {
    unawaited(_loadRouteIfChanged());
  }

  Future<void> _loadRouteIfChanged() async {
    if (!mounted) return;
    final incident = controller.details;
    final id = controller.incidentId;
    final sentToHospital = incident?.sentToHospital ?? false;
    final latitude = incident?.responderLatitude;
    final longitude = incident?.responderLongitude;
    final hospitalLatitude = sentToHospital ? incident?.hospitalLatitude : null;
    final hospitalLongitude =
        sentToHospital ? incident?.hospitalLongitude : null;
    final currentOrigin = latitude != null && longitude != null
        ? LatLng(latitude, longitude)
        : null;
    final previousOrigin =
        _routeResponderLatitude != null && _routeResponderLongitude != null
            ? LatLng(_routeResponderLatitude!, _routeResponderLongitude!)
            : null;
    final originChanged = id != _routeIncidentId ||
        (currentOrigin == null) != (previousOrigin == null) ||
        (currentOrigin != null &&
            previousOrigin != null &&
            _distance.as(LengthUnit.Meter, currentOrigin, previousOrigin) >=
                100);
    if (id == _routeIncidentId &&
        !originChanged &&
        sentToHospital == _routeSentToHospital &&
        hospitalLatitude == _routeHospitalLatitude &&
        hospitalLongitude == _routeHospitalLongitude) {
      return;
    }
    if (_loadingRoute) return;
    _routeIncidentId = id;
    _routeResponderLatitude = latitude;
    _routeResponderLongitude = longitude;
    _routeSentToHospital = sentToHospital;
    _routeHospitalLatitude = hospitalLatitude;
    _routeHospitalLongitude = hospitalLongitude;
    if (id == null || (incident?.responderUserId == null && !sentToHospital)) {
      if (mounted) {
        setState(() {
          _route = null;
          _routeError = null;
        });
      }
      return;
    }
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || _loadingRoute) return;
    _loadingRoute = true;
    try {
      final route = await MobileApiClient.I.responderStatusDirections(
        token: token,
        incidentId: id,
      );
      if (mounted &&
          controller.incidentId == id &&
          controller.details?.sentToHospital == sentToHospital) {
        setState(() {
          _route = route;
          _routeError = null;
        });
      }
    } on MobileApiException catch (error) {
      if (mounted &&
          controller.incidentId == id &&
          controller.details?.sentToHospital == sentToHospital) {
        setState(() {
          _route = null;
          _routeError = error.message;
        });
      }
    } finally {
      _loadingRoute = false;
      unawaited(_loadRouteIfChanged());
    }
  }

  Future<void> _alertResponder() async {
    final id = controller.incidentId;
    final token = AuthController.I.user?['token'] as String?;
    if (id == null || token == null || _alerting) return;
    setState(() => _alerting = true);
    try {
      await MobileApiClient.I.alertAssignedResponder(
        token: token,
        incidentId: id,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('The assigned responder has been alerted.')),
        );
      }
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _alerting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: ListenableBuilder(
        listenable: controller,
        builder: (context, _) {
          final incident = controller.details;
          final incidentPoint =
              incident?.latitude != null && incident?.longitude != null
                  ? LatLng(incident!.latitude!, incident.longitude!)
                  : null;
          final responderPoint = incident?.responderLatitude != null &&
                  incident?.responderLongitude != null
              ? LatLng(
                  incident!.responderLatitude!,
                  incident.responderLongitude!,
                )
              : null;
          final hospitalPoint = incident?.hospitalLatitude != null &&
                  incident?.hospitalLongitude != null
              ? LatLng(
                  incident!.hospitalLatitude!,
                  incident.hospitalLongitude!,
                )
              : null;
          final sentToHospital = incident?.sentToHospital == true;
          return Stack(
            fit: StackFit.expand,
            children: [
              Positioned.fill(
                child: OpenStreetMapView(
                  center: responderPoint ??
                      incidentPoint ??
                      hospitalPoint ??
                      OpenStreetMapApi.townCenter,
                  zoom: 15,
                  route: _route?.geometry ?? const [],
                  recenterSignal: _mapRecenterSignal,
                  markers: [
                    if (responderPoint != null)
                      OpenStreetMapMarker(
                        point: responderPoint,
                        label: incident?.responderName ?? 'Assigned responder',
                        color: kResidentBrand,
                        icon: Icons.support_agent,
                        showLabel: true,
                      ),
                    if (incidentPoint != null)
                      OpenStreetMapMarker(
                        point: incidentPoint,
                        label: 'Emergency location',
                        color: kSos,
                        icon: Icons.emergency,
                      ),
                    if (sentToHospital && hospitalPoint != null)
                      OpenStreetMapMarker(
                        point: hospitalPoint,
                        label: incident?.hospital ?? 'Assigned hospital',
                        color: kResidentBrand,
                        icon: Icons.local_hospital,
                      ),
                  ],
                ),
              ),
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                    child: _MapHeader(
                      onBack: () => Navigator.maybePop(context),
                      onRecenter: () =>
                          setState(() => _mapRecenterSignal++),
                      subtitle: incident == null
                          ? 'Loading emergency details…'
                          : '${incident.barangay ?? incident.location}'
                              ' · Case #${incident.incidentId}',
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: _ResponderSheet(
                  incident: incident,
                  onAlertResponder: _alertResponder,
                  alerting: _alerting,
                  routeError: _routeError,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _MapHeader extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onRecenter;
  final String subtitle;
  const _MapHeader({
    required this.onBack,
    required this.onRecenter,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        elevation: 4,
        shadowColor: Colors.black26,
        borderRadius: BorderRadius.circular(18),
        child: SizedBox(
          height: 58,
          child: Row(
            children: [
              IconButton(
                tooltip: 'Back',
                onPressed: onBack,
                icon: const Icon(Icons.arrow_back_ios_new, size: 18),
              ),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Emergency response',
                      style:
                          TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style:
                          const TextStyle(fontSize: 11, color: Colors.black54),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Center on responder',
                onPressed: onRecenter,
                icon: const Icon(
                  Icons.my_location,
                  color: kResidentBrand,
                  size: 21,
                ),
              ),
            ],
          ),
        ),
      );
}

class _ResponderSheet extends StatelessWidget {
  final IncidentDetails? incident;
  final VoidCallback onAlertResponder;
  final bool alerting;
  final String? routeError;

  const _ResponderSheet({
    required this.incident,
    required this.onAlertResponder,
    required this.alerting,
    required this.routeError,
  });

  String _dispatchStatus(IncidentDetails? incident) {
    if (incident?.sentToHospital == true) {
      return 'PATIENT EN ROUTE TO HOSPITAL';
    }
    if (incident?.responderName == null) {
      return 'WAITING FOR RESPONDER ASSIGNMENT';
    }
    if (incident?.responderAlertStatus == 'acknowledged') {
      return 'RESPONDER ACKNOWLEDGED THE DISPATCH';
    }
    return 'ALERT SENT · WAITING FOR RESPONDER';
  }

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        elevation: 12,
        shadowColor: Colors.black38,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 38,
                    height: 4,
                    decoration: BoxDecoration(
                      color: kGrey,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _dispatchStatus(incident),
                            style: const TextStyle(
                              color: kResidentBrand,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              letterSpacing: .5,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            incident?.etaMinutes == null
                                ? '—'
                                : '${incident!.etaMinutes} min',
                            style: const TextStyle(
                              fontSize: 30,
                              height: 1,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            incident?.sentToHospital == true
                                ? 'estimated arrival at the hospital'
                                : 'estimated arrival at the emergency location',
                            style: const TextStyle(
                              fontSize: 11,
                              color: Colors.black54,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const _ResponderAvatar(),
                  ],
                ),
                const SizedBox(height: 14),
                const Divider(height: 1),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        incident?.responderName ?? 'Responder not assigned',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Text(
                      incident?.vehicleLabel ?? 'No vehicle assigned',
                      style:
                          const TextStyle(fontSize: 11, color: Colors.black54),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  'Barangay: ${incident?.responderBarangay ?? incident?.barangay ?? 'Not available'}',
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
                const SizedBox(height: 4),
                Text(
                  'Hospital: ${incident?.hospital ?? 'Not assigned'}',
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
                if (routeError != null) ...[
                  const SizedBox(height: 4),
                  const Text(
                    'Live road route is temporarily unavailable.',
                    style: TextStyle(fontSize: 10, color: Colors.deepOrange),
                  ),
                ],
                const SizedBox(height: 14),
                SizedBox(
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: incident?.responderUserId == null || alerting
                        ? null
                        : onAlertResponder,
                    style: FilledButton.styleFrom(
                      backgroundColor: kResidentBrand,
                      disabledBackgroundColor: kGrey,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    icon: Icon(
                        alerting
                            ? Icons.hourglass_top
                            : Icons.notifications_active,
                        size: 18),
                    label: Text(
                      alerting ? 'ALERTING RESPONDER…' : 'ALERT RESPONDER',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
}

class _ResponderAvatar extends StatelessWidget {
  const _ResponderAvatar();

  @override
  Widget build(BuildContext context) => Container(
        width: 54,
        height: 54,
        decoration: BoxDecoration(
          color: kResidentBrand.withAlpha(24),
          shape: BoxShape.circle,
        ),
        child: const Icon(Icons.support_agent, size: 30, color: kResidentBrand),
      );
}
