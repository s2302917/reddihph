import 'dart:async';

import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/responder_widgets.dart';

/// Step-by-step transport timeline for an accepted case. The responder taps
/// "Update status" to move to the next step; the time is stamped as it happens.
class TransportStatusScreen extends StatefulWidget {
  final String? title, location;
  final String caseId;
  final int? incidentId;
  const TransportStatusScreen(
      {super.key,
      this.title,
      this.location,
      this.incidentId,
      this.caseId = 'A-0142'});

  @override
  State<TransportStatusScreen> createState() => _TransportStatusScreenState();
}

class _TransportStatusScreenState extends State<TransportStatusScreen> {
  static const _steps = [
    'Dispatched',
    'Responding',
    'Patient picked up',
    'En route to hospital',
    'Arrived at hospital',
  ];
  static const _subs = ['Unit RB-04 assigned', 'On the way to the incident'];

  final _times = <String?>['11:16', '11:17', '11:24', null, null];
  int _stage = 2;
  bool _updatingLocation = false;
  bool _arrivalUpdatePending = false;
  bool _locationErrorShown = false;
  Timer? _locationUpdates;

  @override
  void dispose() {
    _locationUpdates?.cancel();
    super.dispose();
  }

  static String _now() {
    final t = TimeOfDay.now();
    return '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
  }

  Future<void> _advance() async {
    if (_stage >= _steps.length - 1) return;
    if (_stage == 3) {
      await _markArrived();
      return;
    }
    setState(() {
      _stage++;
      _times[_stage] = _now();
    });
    if (_stage == 3 && widget.incidentId != null) {
      _syncTransportLocation();
      _locationUpdates = Timer.periodic(
        const Duration(seconds: 15),
        (_) => _syncTransportLocation(),
      );
    }
  }

  Future<void> _syncTransportLocation() async {
    final incidentId = widget.incidentId;
    final token = AuthController.I.user?['token'] as String?;
    if (!mounted ||
        _stage != 3 ||
        incidentId == null ||
        token == null ||
        _updatingLocation) {
      return;
    }

    _updatingLocation = true;
    try {
      final position = await LocationService.currentPosition();
      final result = await MobileApiClient.I.responderIncidentAction(
        token: token,
        incidentId: incidentId,
        action: 'update_transport_location',
        latitude: position.latitude,
        longitude: position.longitude,
      );
      if (result['arrived'] == true) {
        _setArrived();
      }
    } on LocationUnavailableException catch (error) {
      _stopLocationUpdates();
      _showArrivalError(error.message);
    } on MobileApiException catch (error) {
      _stopLocationUpdates();
      _showArrivalError(error.message);
    } finally {
      _updatingLocation = false;
    }
  }

  Future<void> _markArrived() async {
    final incidentId = widget.incidentId;
    final token = AuthController.I.user?['token'] as String?;
    if (incidentId == null || token == null || _arrivalUpdatePending) {
      _showArrivalError(
        'This case is not connected to an active responder session.',
        force: true,
      );
      return;
    }

    _stopLocationUpdates();
    setState(() => _arrivalUpdatePending = true);
    try {
      await MobileApiClient.I.responderIncidentAction(
        token: token,
        incidentId: incidentId,
        action: 'transport_arrived',
      );
      _setArrived();
    } on MobileApiException catch (error) {
      _showArrivalError(error.message, force: true);
    } finally {
      if (mounted) {
        setState(() => _arrivalUpdatePending = false);
      }
    }
  }

  void _setArrived() {
    if (!mounted || _stage >= _steps.length - 1) return;
    _locationUpdates?.cancel();
    _locationUpdates = null;
    setState(() {
      _stage = _steps.length - 1;
      _times[_stage] = _now();
    });
  }

  void _stopLocationUpdates() {
    _locationUpdates?.cancel();
    _locationUpdates = null;
  }

  void _showArrivalError(String message, {bool force = false}) {
    if (!mounted || (_locationErrorShown && !force)) return;
    _locationErrorShown = true;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Widget _dot(int i) {
    const size = 20.0;
    if (i < _stage) {
      return const CircleAvatar(
          radius: size / 2,
          backgroundColor: kResponderBrown,
          child: Icon(Icons.check, size: 12, color: Colors.white));
    }
    if (i == _stage) {
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: kResponderBrown,
          border: Border.all(color: const Color(0xFFE3C3B8), width: 3),
        ),
        child: const Center(
            child: Icon(Icons.circle, size: 6, color: Colors.white)),
      );
    }
    return const CircleAvatar(
        radius: size / 2, backgroundColor: Color(0xFFB9B9B9));
  }

  @override
  Widget build(BuildContext context) {
    final d = DashboardController.I.dispatch;
    final title = widget.title ?? d.title;
    final location = widget.location ?? d.location;
    final last = _stage >= _steps.length - 1;

    return Scaffold(
      appBar: AppBar(),
      body: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          children: [
            Text(title,
                style:
                    const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            Text('Case ${widget.caseId} · $location',
                style: const TextStyle(fontSize: 9, color: Colors.black54)),
            const SizedBox(height: 28),
            const Text('Transport status',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 14),
            for (int i = 0; i < _steps.length; i++)
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Column(children: [
                  _dot(i),
                  if (i < _steps.length - 1)
                    Container(
                        width: 2,
                        height: 30,
                        color: i < _stage
                            ? kResponderBrown
                            : const Color(0xFFD0D0D0)),
                ]),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 1),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(_steps[i],
                              style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: i <= _stage
                                      ? FontWeight.w700
                                      : FontWeight.normal,
                                  color: i <= _stage
                                      ? Colors.black
                                      : Colors.black54)),
                          if (i == _stage)
                            const Text('Current status',
                                style: TextStyle(
                                    fontSize: 8, color: Colors.black54))
                          else if (i < _subs.length && i < _stage)
                            Text(_subs[i],
                                style: const TextStyle(
                                    fontSize: 8, color: Colors.black54)),
                        ]),
                  ),
                ),
                if (_times[i] != null)
                  Text(_times[i]!,
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54)),
              ]),
            const SizedBox(height: 20),
            Btn(
              last
                  ? 'CASE COMPLETED'
                  : 'MARK: ${_steps[_stage + 1].toUpperCase()}',
              kResponderBrown,
              disabled: last || _arrivalUpdatePending,
              onTap: _advance,
            ),
          ]),
    );
  }
}
