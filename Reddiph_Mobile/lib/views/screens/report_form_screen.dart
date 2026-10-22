import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/incident_patient.dart';
import '../../models/role.dart';
import '../../services/location_service.dart';
import '../widgets/common.dart';
import '../widgets/responder_widgets.dart';
import 'report_next_screen.dart';
import 'sos_screen.dart';

/// First step of the responder's incident-report flow.
class ReportFormScreen extends StatefulWidget {
  final Color color;
  const ReportFormScreen({super.key, this.color = kResponderBrown});

  @override
  State<ReportFormScreen> createState() => _ReportFormScreenState();
}

class _ReportFormScreenState extends State<ReportFormScreen> {
  static const _types = ['Vehicular', 'Fall', 'Medical', 'Fire', 'Other'];
  static const _conditions = ['Critical', 'Urgent', 'Stable'];

  String? _type;
  String _condition = 'Critical';
  String _reportedBy = 'on_scene';
  String? _otherType;
  double? _latitude;
  double? _longitude;

  @override
  void initState() {
    super.initState();
    _pinLocation();
  }

  Future<void> _pinLocation() async {
    try {
      final position = await LocationService.currentPosition();
      if (!mounted) return;
      setState(() {
        _latitude = position.latitude;
        _longitude = position.longitude;
      });
    } on LocationUnavailableException catch (error) {
      if (mounted) _message(error.message);
    }
  }

  void _next() {
    final type = _type == 'Other' ? _otherType?.trim() : _type;
    if (type == null || type.isEmpty) {
      _message('Select an incident type first.');
      return;
    }
    if (_latitude == null || _longitude == null) {
      _message('Pin the current incident location first.');
      return;
    }
    final draft = ResponderIncidentDraft(
      incidentType: type,
      status: _condition.toLowerCase(),
      reportedBy: _reportedBy,
      location:
          'GPS ${_latitude!.toStringAsFixed(6)}, ${_longitude!.toStringAsFixed(6)}',
      latitude: _latitude!,
      longitude: _longitude!,
    );
    if (mounted) {
      Nav.push(context, ReportNextScreen(draft: draft));
    }
  }

  void _message(String value) => ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(value)));

  Widget _sectionLabel(String text) => Padding(
        padding: const EdgeInsets.only(top: 16, bottom: 8),
        child: Text(text,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700)),
      );

  Widget _choice(String label, bool selected, VoidCallback onTap,
      {bool emphasized = false}) {
    final color = widget.color;
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: selected
              ? (emphasized ? const Color(0xFFBA696B) : color)
              : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: selected ? color : Colors.black26),
        ),
        child: Text(label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : Colors.black87,
            )),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final locationText = _latitude == null
        ? 'Location not pinned'
        : 'GPS ${_latitude!.toStringAsFixed(6)}, ${_longitude!.toStringAsFixed(6)}';
    return Scaffold(
      appBar: AppBar(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(24, 4, 24, 100),
        children: [
          const Text('Incident details',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 2),
          const Text('Tell us what happened.',
              style: TextStyle(fontSize: 12, color: Colors.black54)),
          const SizedBox(height: 14),
          const _Progress(currentStep: 0),
          const SizedBox(height: 14),
          _sectionLabel('Incident type'),
          Wrap(
            spacing: 5,
            runSpacing: 6,
            children: [
              for (final value in _types)
                _choice(value, _type == value, () {
                  setState(() {
                    _type = value;
                    if (value != 'Other') _otherType = null;
                  });
                }),
            ],
          ),
          if (_type == 'Other') ...[
            const SizedBox(height: 8),
            TextField(
              onChanged: (value) => _otherType = value,
              maxLength: 120,
              decoration: _input('Type the specific incident'),
              style: const TextStyle(fontSize: 11),
            ),
          ],
          _sectionLabel('Condition'),
          Wrap(
            spacing: 5,
            children: [
              for (final value in _conditions)
                _choice(value, _condition == value,
                    () => setState(() => _condition = value),
                    emphasized: value == 'Critical'),
            ],
          ),
          _sectionLabel('Location'),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.black12),
            ),
            child: Row(children: [
              Icon(Icons.location_pin, size: 20, color: widget.color),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(locationText,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              fontSize: 9, fontWeight: FontWeight.bold)),
                      const Text('Using current device location',
                          style: TextStyle(fontSize: 8, color: Colors.black54)),
                    ]),
              ),
              TextButton(
                onPressed: _pinLocation,
                child: Text(_latitude == null ? 'Pin' : 'Change',
                    style: const TextStyle(fontSize: 9)),
              ),
            ]),
          ),
          _sectionLabel('Reported by'),
          Wrap(spacing: 5, children: [
            _choice('On scene', _reportedBy == 'on_scene',
                () => setState(() => _reportedBy = 'on_scene')),
            _choice('Resident SOS', _reportedBy == 'resident_sos',
                () => setState(() => _reportedBy = 'resident_sos')),
          ]),
          const SizedBox(height: 10),
          SizedBox(
            height: 48,
            child: Btn('NEXT', widget.color, fontSize: 13, onTap: _next),
          ),
          const SizedBox(height: 28),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: kSos,
        shape: const CircleBorder(),
        onPressed: () => Nav.push(
            context, SosScreen(role: AuthController.I.role ?? roles[1])),
        child: const Text('SOS',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
    );
  }
}

InputDecoration _input(String hint) => InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 10, color: Colors.black38),
      isDense: true,
      counterText: '',
      contentPadding: const EdgeInsets.all(10),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(6)),
    );

class _Progress extends StatelessWidget {
  final int currentStep;
  const _Progress({required this.currentStep});

  @override
  Widget build(BuildContext context) => Column(children: [
        Row(children: [
          for (var i = 0; i < 3; i++)
            Expanded(
              child: Container(
                height: 7,
                margin: EdgeInsets.only(right: i == 2 ? 0 : 6),
                decoration: BoxDecoration(
                  color: i <= currentStep
                      ? kResponderBrown
                      : const Color(0xFFE2E0DD),
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
        ]),
        const SizedBox(height: 5),
        Row(children: [
          for (var i = 0; i < 3; i++)
            Expanded(
              child: Text(
                const ['Incident', 'Patient', 'Send'][i],
                textAlign: i == 0
                    ? TextAlign.left
                    : i == 1
                        ? TextAlign.center
                        : TextAlign.right,
                style: TextStyle(
                  fontSize: 10,
                  color: i <= currentStep ? kResponderBrown : Colors.black54,
                  fontWeight:
                      i == currentStep ? FontWeight.w600 : FontWeight.normal,
                ),
              ),
            ),
        ]),
      ]);
}
