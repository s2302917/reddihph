import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/driving_route.dart';
import '../../models/hospital_arrival_summary.dart';
import '../../models/responder_report_preview.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/open_street_map.dart';
import 'responder_hospital_arrival_screen.dart';

class HospitalRouteScreen extends StatefulWidget {
  const HospitalRouteScreen({
    super.key,
    required this.incidentId,
    required this.incidentType,
    required this.location,
    required this.preview,
    this.volunteerName,
    this.apiClient,
  });

  final int incidentId;
  final String incidentType;
  final String location;
  final ResponderReportPreview preview;
  final String? volunteerName;
  final MobileApiClient? apiClient;

  @override
  State<HospitalRouteScreen> createState() => _HospitalRouteScreenState();
}

class _HospitalRouteScreenState extends State<HospitalRouteScreen> {
  final TextEditingController _notesController = TextEditingController();
  final Distance _distance = const Distance();
  late final MobileApiClient _apiClient = widget.apiClient ?? MobileApiClient.I;
  StreamSubscription<Position>? _positionSubscription;
  Position? _position;
  DrivingRoute? _route;
  double? _heading;
  bool _followHeading = true;
  int _mapRecenterSignal = 0;
  LatLng? _lastRoutedPosition;
  DateTime? _lastRouteRefreshAt;
  DateTime? _lastLocationSentAt;
  DateTime? _lastPresenceSentAt;
  String? _error;
  String? _backgroundError;
  bool _loading = true;
  bool _refreshing = false;
  bool _sendingNotes = false;
  final bool _arrived = false;
  bool _arrivalSubmitting = false;

  bool get _isResponder => AuthController.I.user?['role'] == 'responder';
  double get _mapBearing => _followHeading ? (_heading ?? 0) : 0;

  void _captureHeading(Position position) {
    if (position.speed >= 1 &&
        position.heading.isFinite &&
        position.heading >= 0 &&
        position.headingAccuracy.isFinite &&
        position.headingAccuracy >= 0 &&
        position.headingAccuracy <= 45) {
      _heading = position.heading;
    }
  }

  @override
  void initState() {
    super.initState();
    _beginNavigation();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _beginNavigation() async {
    await _positionSubscription?.cancel();
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
      _backgroundError = null;
    });

    try {
      final position = await LocationService.currentPosition();
      if (!mounted) return;
      setState(() {
        _position = position;
        _captureHeading(position);
      });
      await _refreshRoute(position);
      await _sendResponderPresence(position);
      await _sendTransportLocation(position);
      await _checkArrival(position);
      if (!mounted) return;
      _positionSubscription = LocationService.positionStream().listen(
        (position) => unawaited(_handlePosition(position)),
        onError: (Object error) {
          if (!mounted) return;
          setState(() => _backgroundError = error.toString());
        },
      );
    } on LocationUnavailableException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on Exception {
      if (mounted) {
        setState(
            () => _error = 'Unable to start navigation. Please try again.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _handlePosition(Position position) async {
    if (!mounted || _arrived) return;
    setState(() {
      _position = position;
      _captureHeading(position);
      _backgroundError = null;
    });
    await _sendResponderPresence(position);
    await _sendTransportLocation(position);

    final previous = _lastRoutedPosition;
    final lastRefreshAt = _lastRouteRefreshAt;
    final elapsed =
        lastRefreshAt == null ? null : DateTime.now().difference(lastRefreshAt);
    final movedEnough = previous != null &&
        _distance.as(
              LengthUnit.Meter,
              previous,
              LatLng(position.latitude, position.longitude),
            ) >=
            500;
    if (!_refreshing &&
        (lastRefreshAt == null ||
            (elapsed != null && elapsed >= const Duration(minutes: 5)) ||
            (movedEnough &&
                elapsed != null &&
                elapsed >= const Duration(minutes: 2)))) {
      try {
        await _refreshRoute(position);
      } on MobileApiException catch (error) {
        if (mounted) setState(() => _backgroundError = error.message);
      }
    }
    await _checkArrival(position);
  }

  Future<void> _refreshRoute(Position position) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      throw const MobileApiException('Please sign in again to get directions.');
    }
    if (mounted) setState(() => _refreshing = true);
    _lastRouteRefreshAt = DateTime.now();
    try {
      final route = await _apiClient.incidentDirections(
        token: token,
        incidentId: widget.incidentId,
        latitude: position.latitude,
        longitude: position.longitude,
      );
      if (!mounted) return;
      setState(() {
        _route = route;
        _lastRoutedPosition = LatLng(position.latitude, position.longitude);
        _error = null;
        _backgroundError = null;
      });
    } on MobileApiException catch (error) {
      if (!mounted) rethrow;
      setState(() => _backgroundError = error.message);
      if (_route == null) rethrow;
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _retryRoute() async {
    final position = _position;
    if (position == null) {
      await _beginNavigation();
      return;
    }
    try {
      await _refreshRoute(position);
    } on MobileApiException {
      // The route error is already shown in the route status panel.
    }
  }

  void _recenterMap() {
    setState(() => _mapRecenterSignal++);
  }

  void _toggleHeadingFollow() {
    setState(() {
      _followHeading = !_followHeading;
      _mapRecenterSignal++;
    });
  }

  Future<void> _sendTransportLocation(Position position) async {
    if (!_isResponder || _route?.transportAssigned != true) return;
    final token = AuthController.I.user?['token'] as String?;
    final lastSent = _lastLocationSentAt;
    if (token == null ||
        (lastSent != null &&
            DateTime.now().difference(lastSent) <
                const Duration(seconds: 30))) {
      return;
    }
    _lastLocationSentAt = DateTime.now();
    try {
      await _apiClient.responderIncidentAction(
        token: token,
        incidentId: widget.incidentId,
        action: 'update_transport_location',
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _backgroundError = error.message);
    }
  }

  Future<void> _sendResponderPresence(Position position) async {
    if (!_isResponder) return;
    final token = AuthController.I.user?['token'] as String?;
    final lastSent = _lastPresenceSentAt;
    if (token == null ||
        (lastSent != null &&
            DateTime.now().difference(lastSent) <
                const Duration(seconds: 20))) {
      return;
    }
    _lastPresenceSentAt = DateTime.now();
    try {
      await _apiClient.updateResponderPresence(
        token: token,
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _backgroundError = error.message);
    }
  }

  Future<void> _checkArrival(Position position) async {
    final route = _route;
    final token = AuthController.I.user?['token'] as String?;
    if (!_isResponder ||
        _arrivalSubmitting ||
        route == null ||
        token == null ||
        _distance.as(
              LengthUnit.Meter,
              LatLng(position.latitude, position.longitude),
              route.geometry.last,
            ) >
            60) {
      return;
    }

    _arrivalSubmitting = true;
    try {
      final response = await _apiClient.responderIncidentAction(
        token: token,
        incidentId: widget.incidentId,
        action: 'transport_arrived',
      );
      if (!mounted) return;
      await _positionSubscription?.cancel();
      final arrival = response['arrival'];
      if (arrival is! Map<String, dynamic>) {
        throw const MobileApiException(
          'The server did not return the hospital arrival timeline.',
        );
      }
      final summary = HospitalArrivalSummary.fromJson(arrival);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement<void, void>(
        MaterialPageRoute<void>(
          builder: (_) => ResponderHospitalArrivalScreen(
            incidentId: widget.incidentId,
            summary: summary,
          ),
        ),
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _backgroundError = error.message);
    } on FormatException {
      if (mounted) {
        setState(() => _backgroundError =
            'The hospital arrival timeline returned invalid data.');
      }
    } finally {
      _arrivalSubmitting = false;
    }
  }

  Future<void> _sendNotes() async {
    final notes = _notesController.text.trim();
    if (notes.isEmpty || _sendingNotes) return;
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      setState(() =>
          _backgroundError = 'Please sign in again before sending an update.');
      return;
    }

    setState(() {
      _sendingNotes = true;
      _backgroundError = null;
    });
    try {
      await _apiClient.updateIncidentNotes(
        token: token,
        incidentId: widget.incidentId,
        notes: notes,
      );
      if (!mounted) return;
      _notesController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Update sent to hospital staff on duty.')),
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _backgroundError = error.message);
    } finally {
      if (mounted) setState(() => _sendingNotes = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final route = _route;
    final currentPosition = _position;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              flex: 7,
              child: _RouteMap(
                geometry: route?.geometry ?? const [],
                currentPosition: currentPosition == null
                    ? null
                    : LatLng(
                        currentPosition.latitude,
                        currentPosition.longitude,
                      ),
                refreshing: _refreshing,
                arrived: _arrived,
                onBack: () => Navigator.of(context).maybePop(),
                turnGuidance: route == null ? null : _directionsCard(route),
                bearing: _mapBearing,
                followHeading: _followHeading,
                recenterSignal: _mapRecenterSignal,
                onRecenter: _recenterMap,
                onToggleHeading: _toggleHeadingFollow,
              ),
            ),
            Expanded(
              flex: 5,
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(
                    top: Radius.circular(18),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x15000000),
                      blurRadius: 10,
                      offset: Offset(0, -3),
                    ),
                  ],
                ),
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                  children: [
                    if (_loading) const LinearProgressIndicator(minHeight: 2),
                    if (_error != null) _errorCard(_error!),
                    if (_backgroundError != null) _errorCard(_backgroundError!),
                    _destinationCard(route),
                    if (widget.volunteerName != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Volunteer assistance requested from ${widget.volunteerName}.',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                    const SizedBox(height: 12),
                    _notesForm(),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _destinationCard(DrivingRoute? route) {
    final hospitalName = route?.hospitalName ?? widget.preview.hospitalName;
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _arrived ? 'ARRIVED AT' : 'EN ROUTE TO',
                style: const TextStyle(
                  color: Color(0xFF6C7075),
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: .5,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                hospitalName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style:
                    const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
              ),
              Text(
                '${widget.incidentType} · ${widget.location}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, color: Colors.black54),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              route?.etaMinutes == null ? '--' : '${route!.etaMinutes} mins',
              style: const TextStyle(
                color: Color(0xFF9B1719),
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              route?.distanceLabel ?? 'Distance unavailable',
              style: const TextStyle(fontSize: 11, color: Colors.black54),
            ),
          ],
        ),
        IconButton(
          tooltip: 'Refresh route',
          onPressed: _position == null ? _beginNavigation : _retryRoute,
          icon: _loading
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.refresh, size: 20),
        ),
      ],
    );
  }

  Widget _directionsCard(DrivingRoute route) {
    final current = route.instructions.first;
    final following =
        route.instructions.length > 1 ? route.instructions[1] : null;
    final nextAction = following?.text;
    final subtitle = nextAction != null
        ? 'Then $nextAction'
        : 'Continue to ${route.destinationName}';
    final instructionIcon = current.text.toLowerCase().contains('left')
        ? Icons.turn_left
        : current.text.toLowerCase().contains('right')
            ? Icons.turn_right
            : current.text.toLowerCase().contains('u-turn')
                ? Icons.u_turn_left
                : Icons.arrow_upward;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: const Color(0xFF91442F),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Icon(instructionIcon, color: Colors.white, size: 27),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${current.text} · ${current.distanceLabel}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFFFFE7DB),
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Text(
          route.trafficAware
              ? 'TomTom traffic data is used where available.'
              : 'ETA uses estimated road speeds; live traffic is unavailable.',
          style: const TextStyle(fontSize: 10, color: Colors.black54),
        ),
      ],
    );
  }

  Widget _notesForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'SEND AN UPDATE TO THE HOSPITAL',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 7),
        TextField(
          controller: _notesController,
          minLines: 2,
          maxLines: 3,
          maxLength: 2000,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            hintText: 'Add a patient or transport update',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            counterText: '',
          ),
        ),
        const SizedBox(height: 8),
        FilledButton.icon(
          onPressed: _sendingNotes ? null : _sendNotes,
          icon: _sendingNotes
              ? const SizedBox(
                  width: 17,
                  height: 17,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.send, size: 17),
          label: Text(
              _sendingNotes ? 'SENDING UPDATE...' : 'SEND UPDATE TO HOSPITAL'),
          style: FilledButton.styleFrom(
            backgroundColor: const Color(0xFF9B1719),
            minimumSize: const Size.fromHeight(46),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
      ],
    );
  }

  Widget _errorCard(String message) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: const Color(0xFFFFF2F0),
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.all(11),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.info_outline,
                  color: Color(0xFF9B1719), size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(message, style: const TextStyle(fontSize: 12)),
              ),
              if (_error != null)
                TextButton(
                  onPressed: _beginNavigation,
                  child: const Text('RETRY'),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RouteMap extends StatelessWidget {
  const _RouteMap({
    required this.geometry,
    required this.currentPosition,
    required this.refreshing,
    required this.arrived,
    required this.onBack,
    required this.turnGuidance,
    required this.bearing,
    required this.followHeading,
    required this.recenterSignal,
    required this.onRecenter,
    required this.onToggleHeading,
  });
  final List<LatLng> geometry;
  final LatLng? currentPosition;
  final bool refreshing;
  final bool arrived;
  final VoidCallback onBack;
  final Widget? turnGuidance;
  final double bearing;
  final bool followHeading;
  final int recenterSignal;
  final VoidCallback onRecenter;
  final VoidCallback onToggleHeading;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        Positioned.fill(
          child: OpenStreetMapView(
            center: currentPosition ??
                (geometry.isNotEmpty
                    ? geometry.first
                    : const LatLng(10.55, 122.95)),
            zoom: 14,
            route: geometry,
            routeColor: const Color(0xFF171717),
            bearing: bearing,
            followCenter: true,
            rotationEnabled: true,
            recenterSignal: recenterSignal,
            markers: [
              if (currentPosition != null)
                OpenStreetMapMarker(
                  point: currentPosition!,
                  label: 'Your location',
                  color: const Color(0xFF171D45),
                  icon: Icons.my_location,
                ),
              if (geometry.length > 1)
                OpenStreetMapMarker(
                  point: geometry.last,
                  label: 'Hospital',
                  color: const Color(0xFF91442F),
                  icon: Icons.local_hospital,
                ),
            ],
          ),
        ),
        Positioned(
          top: 8,
          left: 8,
          child: Material(
            color: Colors.white,
            shape: const CircleBorder(),
            elevation: 2,
            child: IconButton(
              tooltip: 'Back',
              onPressed: onBack,
              icon: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
        ),
        Positioned(
          top: 14,
          right: 14,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              boxShadow: const [
                BoxShadow(color: Color(0x22000000), blurRadius: 8),
              ],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  arrived ? Icons.check_circle : Icons.circle,
                  color: arrived ? kResponderBrand : const Color(0xFF2E8B57),
                  size: 10,
                ),
                const SizedBox(width: 6),
                Text(
                  arrived
                      ? 'ARRIVED'
                      : refreshing
                          ? 'UPDATING ROUTE'
                          : 'LIVE ROUTE',
                  style: const TextStyle(
                      fontSize: 10, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ),
        Positioned(
          top: 58,
          right: 12,
          child: Column(
            children: [
              _MapActionButton(
                tooltip: 'Recenter on responder',
                icon: Icons.my_location,
                onPressed: onRecenter,
              ),
              const SizedBox(height: 8),
              _MapActionButton(
                tooltip: followHeading
                    ? 'Switch to north-up'
                    : 'Follow travel direction',
                icon: Icons.explore_outlined,
                selected: followHeading,
                onPressed: onToggleHeading,
              ),
            ],
          ),
        ),
        if (turnGuidance != null)
          Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: SafeArea(
              top: false,
              child: turnGuidance!,
            ),
          ),
      ],
    );
  }
}

class _MapActionButton extends StatelessWidget {
  const _MapActionButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
    this.selected = false,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;
  final bool selected;

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.white,
        shape: const CircleBorder(),
        elevation: 3,
        child: IconButton(
          tooltip: tooltip,
          onPressed: onPressed,
          icon: Icon(
            icon,
            color: selected ? const Color(0xFF91442F) : Colors.black87,
            size: 20,
          ),
        ),
      );
}
