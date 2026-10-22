import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:latlong2/latlong.dart';

import '../models/driving_route.dart';
import '../models/responder_dashboard.dart';
import '../services/location_service.dart';
import '../services/mobile_api_client.dart';
import 'auth_controller.dart';

/// Loads the responder home page from the database and keeps it fresh.
class ResponderController extends ChangeNotifier {
  ResponderController({
    MobileApiClient? client,
    Future<LatLng?> Function()? positionLoader,
  })  : _client = client ?? MobileApiClient.I,
        _positionLoader = positionLoader;

  static const _refreshEvery = Duration(seconds: 20);

  final MobileApiClient _client;
  final Future<LatLng?> Function()? _positionLoader;
  Timer? _timer;
  bool _disposed = false;
  bool _loading = false;
  bool _reloadRequested = false;

  ResponderDashboard? dashboard;
  DrivingRoute? activeRoute;
  LatLng? position;
  String? error;
  String? routeError;
  int page = 1;
  int? _routedIncidentId;
  bool? _routedToHospital;
  DateTime? _routeUpdatedAt;

  /// Loads the page and then refreshes it on a timer until [dispose].
  void start() {
    unawaited(load());
    unawaited(_loadPosition());
    _timer = Timer.periodic(_refreshEvery, (_) {
      unawaited(_loadPosition());
    });
  }

  Future<void> load() async {
    if (_loading) {
      _reloadRequested = true;
      return;
    }
    _loading = true;
    try {
      var result = await _fetchDashboard();
      // Cases can close while the list is open, so never stay on a page that is gone.
      if (page > result.queue.pages) {
        page = result.queue.pages;
        result = await _fetchDashboard();
      }
      dashboard = result;
      error = null;
      await _refreshActiveRoute(result.activeCase);
    } on MobileApiException catch (e) {
      error = e.message;
    } finally {
      _loading = false;
      _notify();
      if (_reloadRequested) {
        _reloadRequested = false;
        unawaited(load());
      }
    }
  }

  Future<ResponderDashboard> _fetchDashboard() => _client.responderDashboard(
        token: AuthController.I.user?['token'] as String? ?? '',
        page: page,
        latitude: position?.latitude,
        longitude: position?.longitude,
      );

  Future<void> _refreshActiveRoute(ResponderIncident? incident) async {
    if (incident == null || !incident.sentToHospital || position == null) {
      activeRoute = null;
      routeError = null;
      _routedIncidentId = null;
      _routedToHospital = null;
      _routeUpdatedAt = null;
      return;
    }
    if (_routedIncidentId != incident.id ||
        _routedToHospital != incident.sentToHospital) {
      activeRoute = null;
    }
    final updatedAt = _routeUpdatedAt;
    if (_routedIncidentId == incident.id &&
        _routedToHospital == incident.sentToHospital &&
        updatedAt != null &&
        DateTime.now().difference(updatedAt) < const Duration(minutes: 1)) {
      return;
    }

    try {
      activeRoute = await _client.incidentDirections(
        token: AuthController.I.user?['token'] as String? ?? '',
        incidentId: incident.id,
        latitude: position!.latitude,
        longitude: position!.longitude,
      );
      _routedIncidentId = incident.id;
      _routedToHospital = incident.sentToHospital;
      _routeUpdatedAt = DateTime.now();
      routeError = null;
    } on MobileApiException catch (e) {
      routeError = e.message;
    }
  }

  bool get showUpcomingCases => shouldShowUpcomingResponderCases(
        sentToHospital: dashboard?.activeCase?.sentToHospital ?? false,
        etaMinutes: activeRoute?.etaMinutes,
      );

  Future<void> _loadPosition() async {
    final currentPosition = await (_positionLoader ?? _currentPosition)();
    if (_disposed) return;
    if (currentPosition != null) position = currentPosition;
    await load();
  }

  Future<void> next() => _goTo(page + 1);
  Future<void> previous() => _goTo(page - 1);

  Future<void> _goTo(int target) {
    final pages = dashboard?.queue.pages ?? 1;
    if (target < 1 || target > pages) return Future.value();
    page = target;
    return load();
  }

  /// The responder's location is optional here: without it distances start at the hospital.
  Future<LatLng?> _currentPosition() async {
    try {
      final p = await LocationService.currentPosition();
      return LatLng(p.latitude, p.longitude);
    } on LocationUnavailableException {
      return null;
    }
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    super.dispose();
  }
}
