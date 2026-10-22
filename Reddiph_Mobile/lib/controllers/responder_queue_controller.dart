import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:latlong2/latlong.dart';

import '../models/responder_dashboard.dart';
import '../services/location_service.dart';
import '../services/mobile_api_client.dart';
import 'auth_controller.dart';

/// Loads the SOS queue tab (filter + page) and re-reads it every second so new
/// incidents appear in real time.
class ResponderQueueController extends ChangeNotifier {
  ResponderQueueController({MobileApiClient? client})
      : _client = client ?? MobileApiClient.I;

  static const refreshEvery = Duration(seconds: 1);
  static const filters = ['all', 'critical', 'urgent', 'stable'];

  final MobileApiClient _client;
  Timer? _timer;
  bool _loading = false;
  bool _disposed = false;
  LatLng? _position;

  ResponderQueue? queue;
  String? error;
  String filter = 'all';
  int page = 1;

  void start() {
    unawaited(_locate());
    unawaited(load());
    _timer = Timer.periodic(refreshEvery, (_) => load());
  }

  /// A ticking refresh is skipped while the previous request is still running.
  Future<void> load() async {
    if (_loading) return;
    _loading = true;
    try {
      final result = await _client.responderQueue(
        token: AuthController.I.user?['token'] as String? ?? '',
        status: filter,
        page: page,
        latitude: _position?.latitude,
        longitude: _position?.longitude,
      );
      // Cases can disappear from a filter, so never stay on a page that is gone.
      if (page > result.page.pages) {
        page = result.page.pages;
        _loading = false;
        return load();
      }
      queue = result;
      error = null;
    } on MobileApiException catch (e) {
      error = e.message;
    }
    _loading = false;
    if (!_disposed) notifyListeners();
  }

  Future<ResponderIncident?> refreshActiveCase() async {
    final dashboard = await _client.responderDashboard(
      token: AuthController.I.user?['token'] as String? ?? '',
      latitude: _position?.latitude,
      longitude: _position?.longitude,
    );
    return dashboard.activeCase;
  }

  void select(String value) {
    if (value == filter) return;
    filter = value;
    page = 1;
    unawaited(load());
  }

  void next() => _goTo(page + 1);
  void previous() => _goTo(page - 1);

  void _goTo(int target) {
    if (target < 1 || target > (queue?.page.pages ?? 1)) return;
    page = target;
    unawaited(load());
  }

  /// The location only sharpens the distances; without it they start at the hospital.
  Future<void> _locate() async {
    try {
      final p = await LocationService.currentPosition();
      _position = LatLng(p.latitude, p.longitude);
    } on LocationUnavailableException {
      _position = null;
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    super.dispose();
  }
}
