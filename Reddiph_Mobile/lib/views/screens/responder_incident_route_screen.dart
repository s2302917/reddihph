import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/driving_route.dart';
import '../../models/responder_dashboard.dart';
import '../../models/volunteer.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/open_street_map.dart';
import 'incident_arrival_screen.dart';

class ResponderIncidentRouteScreen extends StatefulWidget {
  const ResponderIncidentRouteScreen({
    super.key,
    required this.incidentId,
    required this.incidentType,
    required this.location,
    this.incident,
    this.apiClient,
  });

  final int incidentId;
  final String incidentType;
  final String location;
  final ResponderIncident? incident;
  final MobileApiClient? apiClient;

  @override
  State<ResponderIncidentRouteScreen> createState() =>
      _ResponderIncidentRouteScreenState();
}

class _ResponderIncidentRouteScreenState
    extends State<ResponderIncidentRouteScreen> {
  static const _red = Color(0xFF870000);
  final Distance _distance = const Distance();
  late final MobileApiClient _api = widget.apiClient ?? MobileApiClient.I;
  StreamSubscription<Position>? _positionSubscription;
  Position? _position;
  DrivingRoute? _route;
  double? _heading;
  bool _followHeading = true;
  int _mapRecenterSignal = 0;
  List<VolunteerProfile> _nearbyVolunteers = const [];
  VolunteerProfile? _selectedVolunteer;
  LatLng? _lastRoutedPosition;
  DateTime? _lastRouteRefreshAt;
  DateTime? _lastPresenceSentAt;
  String? _error;
  bool _loading = true;
  bool _refreshing = false;
  bool _arriving = false;
  bool _loadingVolunteers = false;
  String? _volunteerError;

  bool get _isHospitalRoute => widget.incident?.sentToHospital == true;
  double get _mapBearing => _followHeading ? (_heading ?? 0) : 0;

  @override
  void initState() {
    super.initState();
    unawaited(_startNavigation());
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    super.dispose();
  }

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

  Future<void> _loadNearbyVolunteers(Position position) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      if (mounted) {
        setState(() {
          _volunteerError = 'Sign in again to view available volunteers.';
          _loadingVolunteers = false;
        });
      }
      return;
    }
    setState(() {
      _loadingVolunteers = true;
      _volunteerError = null;
    });
    try {
      final roster = await _api.volunteersForIncident(
        token: token,
        latitude: position.latitude,
        longitude: position.longitude,
      );
      if (!mounted) return;
      final available = roster
          .where((volunteer) => volunteer.available)
          .toList(growable: false);
      setState(() {
        _nearbyVolunteers = available;
        if (_selectedVolunteer != null &&
            !available
                .any((volunteer) => volunteer.id == _selectedVolunteer!.id)) {
          _selectedVolunteer = null;
        }
        _loadingVolunteers = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _volunteerError = error.message;
        _loadingVolunteers = false;
      });
    }
  }

  Future<void> _startNavigation() async {
    await _positionSubscription?.cancel();
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final position = await LocationService.currentPosition();
      if (!mounted) return;
      setState(() {
        _position = position;
        _captureHeading(position);
      });
      await _refreshRoute(position);
      if (widget.incident?.sentToHospital != true) {
        await _loadNearbyVolunteers(position);
      }
      await _sendResponderPresence(position);
      _positionSubscription = LocationService.positionStream().listen(
        (position) => unawaited(_handlePosition(position)),
        onError: (Object error) {
          if (mounted) setState(() => _error = error.toString());
        },
      );
    } on LocationUnavailableException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on Exception {
      if (mounted) {
        setState(() => _error = 'Unable to start navigation. Please retry.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _handlePosition(Position position) async {
    if (!mounted) return;
    setState(() {
      _position = position;
      _captureHeading(position);
    });
    await _sendResponderPresence(position);
    final previous = _lastRoutedPosition;
    final refreshedAt = _lastRouteRefreshAt;
    final elapsed =
        refreshedAt == null ? null : DateTime.now().difference(refreshedAt);
    final movedEnough = previous != null &&
        _distance.as(
              LengthUnit.Meter,
              previous,
              LatLng(position.latitude, position.longitude),
            ) >=
            500;
    if (!_refreshing &&
        (refreshedAt == null ||
            (elapsed != null && elapsed >= const Duration(minutes: 5)) ||
            (movedEnough &&
                elapsed != null &&
                elapsed >= const Duration(minutes: 2)))) {
      try {
        await _refreshRoute(position);
      } on MobileApiException catch (error) {
        if (mounted) setState(() => _error = error.message);
      }
    }
  }

  Future<void> _refreshRoute(Position position) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      throw const MobileApiException('Please sign in again for directions.');
    }
    setState(() => _refreshing = true);
    _lastRouteRefreshAt = DateTime.now();
    try {
      final route = widget.incident?.sentToHospital == true
          ? await _api.incidentDirections(
              token: token,
              incidentId: widget.incidentId,
              latitude: position.latitude,
              longitude: position.longitude,
            )
          : await _api.responderIncidentDirections(
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
      });
    } on MobileApiException catch (error) {
      if (!mounted || _route == null) rethrow;
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _sendResponderPresence(Position position) async {
    final now = DateTime.now();
    final lastSent = _lastPresenceSentAt;
    if (lastSent != null &&
        now.difference(lastSent) < const Duration(seconds: 20)) {
      return;
    }
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) return;
    _lastPresenceSentAt = now;
    try {
      await _api.updateResponderPresence(
        token: token,
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    }
  }

  Future<void> _manualRefresh() async {
    try {
      final position = await LocationService.currentPosition();
      if (!mounted) return;
      setState(() {
        _position = position;
        _captureHeading(position);
        _mapRecenterSignal++;
      });
      await _sendResponderPresence(position);
      await _refreshRoute(position);
    } on LocationUnavailableException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
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

  Future<void> _markArrived() async {
    if (_arriving) return;
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      setState(
          () => _error = 'Please sign in again before confirming arrival.');
      return;
    }
    setState(() {
      _arriving = true;
      _error = null;
    });
    try {
      await _api.responderIncidentAction(
        token: token,
        incidentId: widget.incidentId,
        action: 'mark_scene_arrived',
      );
      if (!mounted) return;
      final navigator = Navigator.of(context);
      final destination = _route?.geometry.last;
      await _positionSubscription?.cancel();
      if (!mounted) return;
      await navigator.pushReplacement<void, void>(
        MaterialPageRoute<void>(
          builder: (_) => IncidentArrivalScreen(
            incidentId: widget.incidentId,
            incidentType: widget.incidentType,
            location: widget.location,
            incidentPoint: destination,
            incident: widget.incident,
            selectedVolunteer: _selectedVolunteer,
            apiClient: _api,
          ),
        ),
      );
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _arriving = false);
    }
  }

  Widget _turnBanner(DrivingRoute route, {required bool hospital}) {
    final instruction = route.instructions.first;
    final text = instruction.text;
    final icon = text.toLowerCase().contains('left')
        ? Icons.turn_left
        : text.toLowerCase().contains('right')
            ? Icons.turn_right
            : text.toLowerCase().contains('u-turn')
                ? Icons.u_turn_left
                : Icons.arrow_upward;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: kResponderBrand,
        borderRadius: BorderRadius.circular(14),
        boxShadow: const [
          BoxShadow(
              color: Color(0x26000000), blurRadius: 12, offset: Offset(0, 3)),
        ],
      ),
      child: Row(
        children: [
          Icon(
            icon,
            color: Colors.white,
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${instruction.text} · ${instruction.distanceLabel}',
                  maxLines: hospital ? 2 : 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  route.instructions.length > 1
                      ? 'Then ${route.instructions[1].text}'
                      : 'Continue to ${route.destinationName}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    color: Colors.white70,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _destinationSummary(DrivingRoute? route, {required bool hospital}) {
    final title =
        hospital ? route?.destinationName ?? 'Hospital' : widget.incidentType;
    final eta = route?.etaMinutes == null ? '--' : '${route!.etaMinutes} min';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7F7),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor:
                hospital ? const Color(0xFFFFE9E8) : const Color(0xFFFFF0E3),
            child: Icon(
              hospital ? Icons.local_hospital_outlined : Icons.emergency,
              color: _red,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  hospital ? 'DESTINATION' : 'INCIDENT LOCATION',
                  style: const TextStyle(
                    color: Colors.black54,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    letterSpacing: .6,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w800),
                ),
                Text(
                  widget.location,
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
                eta,
                style: const TextStyle(
                  color: _red,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                route?.distanceLabel ?? 'Distance unavailable',
                style: const TextStyle(fontSize: 10, color: Colors.black54),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _hospitalDestinationCard(DrivingRoute? route) =>
      _destinationSummary(route, hospital: true);

  Widget _incidentDestinationCard(DrivingRoute? route) =>
      _destinationSummary(route, hospital: false);

  Widget _nearbyVolunteerSection() {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E5E5)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.volunteer_activism_outlined,
                  color: _red, size: 19),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'AVAILABLE VOLUNTEERS NEARBY',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                tooltip: 'Refresh volunteers',
                visualDensity: VisualDensity.compact,
                onPressed: _position == null || _loadingVolunteers
                    ? null
                    : () => _loadNearbyVolunteers(_position!),
                icon: const Icon(Icons.refresh, size: 18),
              ),
            ],
          ),
          if (_loadingVolunteers)
            const LinearProgressIndicator(minHeight: 2)
          else if (_volunteerError != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                _volunteerError!,
                style: const TextStyle(color: _red, fontSize: 12),
              ),
            )
          else if (_nearbyVolunteers.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text(
                'No available volunteers nearby right now.',
                style: TextStyle(color: Colors.black54, fontSize: 12),
              ),
            )
          else ...[
            for (final volunteer in _nearbyVolunteers)
              Padding(
                padding: const EdgeInsets.only(top: 7),
                child: InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: () => setState(() {
                    _selectedVolunteer = _selectedVolunteer?.id == volunteer.id
                        ? null
                        : volunteer;
                  }),
                  child: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: _selectedVolunteer?.id == volunteer.id
                          ? const Color(0xFFFFF4F2)
                          : Colors.white,
                      border: Border.all(
                        color: _selectedVolunteer?.id == volunteer.id
                            ? _red
                            : const Color(0xFFE9E9E9),
                      ),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        const CircleAvatar(
                          radius: 17,
                          backgroundColor: Color(0xFFF4E6E3),
                          child: Icon(Icons.person, color: _red, size: 18),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                volunteer.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    fontSize: 12, fontWeight: FontWeight.w700),
                              ),
                              Text(
                                '${volunteer.vehicleType} · '
                                '${volunteer.distanceKm?.toStringAsFixed(1) ?? '--'} km',
                                style: const TextStyle(
                                    fontSize: 10, color: Colors.black54),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          _selectedVolunteer?.id == volunteer.id
                              ? Icons.check_circle
                              : Icons.radio_button_unchecked,
                          color: _selectedVolunteer?.id == volunteer.id
                              ? _red
                              : Colors.black38,
                          size: 20,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 8),
            Text(
              _selectedVolunteer == null
                  ? 'Choose a volunteer to include with the hospital transport request.'
                  : 'Selected ${_selectedVolunteer!.name}. The request is sent when you submit the hospital transport form.',
              style: const TextStyle(fontSize: 10, color: Colors.black54),
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final route = _route;
    final gps = _position;
    final current = gps == null ? null : LatLng(gps.latitude, gps.longitude);
    final destination =
        route?.geometry.isNotEmpty == true ? route!.geometry.last : null;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              flex: 6,
              child: Stack(
                children: [
                  OpenStreetMapView(
                    center:
                        current ?? destination ?? const LatLng(10.42, 122.92),
                    zoom: 14,
                    route: route?.geometry ?? const [],
                    routeColor: _isHospitalRoute
                        ? const Color(0xFF171717)
                        : kResidentNavigation,
                    bearing: _mapBearing,
                    followCenter: _isHospitalRoute,
                    rotationEnabled: _isHospitalRoute,
                    fitRouteOnUpdate: !_isHospitalRoute,
                    recenterSignal: _mapRecenterSignal,
                    markers: [
                      if (current != null)
                        OpenStreetMapMarker(
                          point: current,
                          label: 'You',
                          icon: Icons.navigation,
                          color: const Color(0xFF005B45),
                        ),
                      if (destination != null)
                        OpenStreetMapMarker(
                          point: destination,
                          label: widget.incident?.sentToHospital == true
                              ? 'Hospital'
                              : 'Incident',
                          icon: widget.incident?.sentToHospital == true
                              ? Icons.local_hospital
                              : Icons.emergency,
                          color: _red,
                        ),
                    ],
                  ),
                  if (_isHospitalRoute)
                    Positioned(
                      top: 58,
                      right: 12,
                      child: Column(
                        children: [
                          _RouteMapButton(
                            tooltip: 'Recenter on responder',
                            icon: Icons.my_location,
                            onPressed: _recenterMap,
                          ),
                          const SizedBox(height: 8),
                          _RouteMapButton(
                            tooltip: _followHeading
                                ? 'Switch to north-up'
                                : 'Follow travel direction',
                            icon: Icons.explore_outlined,
                            selected: _followHeading,
                            onPressed: _toggleHeadingFollow,
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
                        tooltip: 'Go back',
                        onPressed: () => Navigator.of(context).maybePop(),
                        icon: const Icon(Icons.arrow_back),
                      ),
                    ),
                  ),
                  if (_refreshing)
                    const Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: LinearProgressIndicator(minHeight: 2),
                    ),
                  if (route != null)
                    Positioned(
                      left: 8,
                      right: 8,
                      bottom: 8,
                      child: _turnBanner(route, hospital: _isHospitalRoute),
                    ),
                ],
              ),
            ),
            Expanded(
              flex: 6,
              child: Container(
                width: double.infinity,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x18000000),
                      blurRadius: 12,
                      offset: Offset(0, -3),
                    ),
                  ],
                ),
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
                  children: [
                    if (widget.incident?.sentToHospital == true)
                      _hospitalDestinationCard(route)
                    else
                      _incidentDestinationCard(route),
                    if (_error != null)
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF1F0),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          _error!,
                          style: const TextStyle(
                            color: _red,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    if (route == null && _loading) ...[
                      const LinearProgressIndicator(minHeight: 2),
                      const SizedBox(height: 8),
                      const Text('Finding the best route…',
                          style: TextStyle(color: Colors.black54)),
                    ],
                    if (widget.incident?.sentToHospital != true) ...[
                      const SizedBox(height: 12),
                      _nearbyVolunteerSection(),
                    ],
                    const SizedBox(height: 10),
                    if (widget.incident?.sentToHospital != true)
                      SizedBox(
                        height: 44,
                        child: FilledButton.icon(
                          onPressed:
                              route == null || _arriving ? null : _markArrived,
                          style: FilledButton.styleFrom(
                            backgroundColor: _red,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          icon: _arriving
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(Icons.location_on_outlined),
                          label:
                              Text(_arriving ? 'SENDING ARRIVAL…' : 'ARRIVED'),
                        ),
                      ),
                    Align(
                      alignment: Alignment.center,
                      child: TextButton.icon(
                        onPressed:
                            _loading || _refreshing ? null : _manualRefresh,
                        icon: const Icon(Icons.refresh, size: 16),
                        label: const Text('Refresh route'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RouteMapButton extends StatelessWidget {
  const _RouteMapButton({
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
