import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/responder_dashboard.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import 'responder_sos_incident_details_screen.dart';

class IncidentArrivalScreen extends StatefulWidget {
  const IncidentArrivalScreen({
    super.key,
    required this.incidentId,
    required this.incidentType,
    required this.location,
    this.incidentPoint,
    this.incident,
    this.selectedVolunteer,
    this.apiClient,
  });

  final int incidentId;
  final String incidentType;
  final String location;
  final LatLng? incidentPoint;
  final ResponderIncident? incident;
  final VolunteerProfile? selectedVolunteer;
  final MobileApiClient? apiClient;

  @override
  State<IncidentArrivalScreen> createState() => _IncidentArrivalScreenState();
}

class _IncidentArrivalScreenState extends State<IncidentArrivalScreen> {
  late final MobileApiClient _api = widget.apiClient ?? MobileApiClient.I;
  bool _falseReported = false;

  Future<void> _recordFalseReport() async {
    final submitted = await showGeneralDialog<bool>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'False report options',
      barrierColor: Colors.black.withValues(alpha: .38),
      transitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (context, _, __) => Material(
        type: MaterialType.transparency,
        child: Stack(
          children: [
            Positioned.fill(
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
                child: const ColoredBox(color: Color(0x22000000)),
              ),
            ),
            GestureDetector(
              onTap: () => Navigator.of(context).pop(false),
              child: const SizedBox.expand(),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: SafeArea(
                top: false,
                child: _FalseReportSheet(
                  incidentId: widget.incidentId,
                  location: widget.location,
                  apiClient: _api,
                ),
              ),
            ),
          ],
        ),
      ),
      transitionBuilder: (context, animation, _, child) => SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 1),
          end: Offset.zero,
        ).animate(
            CurvedAnimation(parent: animation, curve: Curves.easeOutCubic)),
        child: child,
      ),
    );
    if (mounted && submitted == true) {
      setState(() => _falseReported = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('False report submitted to your Barangay Admin.'),
        ),
      );
    }
  }

  Future<void> _recordRealEmergency() async {
    final point = widget.incidentPoint ?? widget.incident?.point;
    if (point == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('This incident has no valid location coordinates.'),
        ),
      );
      return;
    }
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => ResponderSosIncidentDetailsScreen(
          incidentId: widget.incidentId,
          incidentType: widget.incidentType,
          status: widget.incident?.status ?? 'stable',
          location: widget.location,
          point: point,
          incident: widget.incident,
          selectedVolunteer: widget.selectedVolunteer,
          apiClient: _api,
        ),
      ),
    );
    if (mounted && saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Patient details saved to this incident.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const Text('Arrival at incident'),
          leading: IconButton(
            tooltip: 'Go back',
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const Icon(Icons.arrow_back),
          ),
        ),
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEAF5EF),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: Color(0xFF147A4B),
                        child: Icon(Icons.check, color: Colors.white),
                      ),
                      SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'You have arrived',
                              style: TextStyle(
                                color: Color(0xFF145D3B),
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 3),
                            Text(
                              'Your Barangay Admin has been notified.',
                              style: TextStyle(
                                color: Color(0xFF426B57),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  'Incident #${widget.incidentId}',
                  style: const TextStyle(
                    color: Colors.black54,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  widget.incidentType,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  widget.location,
                  style: const TextStyle(color: Colors.black54, fontSize: 13),
                ),
                const Spacer(),
                const Text(
                  'What did you find at the scene?',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 54,
                  child: FilledButton.icon(
                    onPressed: _falseReported ? null : _recordRealEmergency,
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF005B45),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: const Icon(Icons.medical_services_outlined),
                    label: const Text('REAL EMERGENCY'),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 54,
                  child: OutlinedButton.icon(
                    onPressed: _falseReported ? null : _recordFalseReport,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF870000),
                      side: const BorderSide(color: Color(0xFF870000)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    icon: const Icon(Icons.report_gmailerrorred_outlined),
                    label: Text(
                      _falseReported
                          ? 'FALSE REPORT SUBMITTED'
                          : 'FALSE REPORT OR PRANK',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
}

class _FalseReportSheet extends StatefulWidget {
  const _FalseReportSheet({
    required this.incidentId,
    required this.location,
    required this.apiClient,
  });

  final int incidentId;
  final String location;
  final MobileApiClient apiClient;

  @override
  State<_FalseReportSheet> createState() => _FalseReportSheetState();
}

class _FalseReportSheetState extends State<_FalseReportSheet> {
  static const _reasons = <(String, String)>[
    ('prank_or_test', 'Prank or test'),
    ('wrong_location', 'Wrong location'),
    ('already_resolved', 'Already resolved'),
    ('cannot_find_anyone', 'Cannot find anyone'),
  ];

  final _notes = TextEditingController();
  String? _reason;
  String? _error;
  bool _submitting = false;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final reason = _reason;
    if (reason == null) {
      setState(() => _error = 'Choose a reason to continue.');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await widget.apiClient.submitFalseReport(
        token: AuthController.I.user?['token'] as String? ?? '',
        incidentId: widget.incidentId,
        reason: reason,
        notes: _notes.text,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(22, 14, 22, 22),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        ),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.black26,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'False report or prank?',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(
                'Case #RS-${widget.incidentId} · ${widget.location}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.black54, fontSize: 12),
              ),
              const SizedBox(height: 18),
              const Text('Select a reason',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final (value, label) in _reasons)
                    ChoiceChip(
                      label: Text(label),
                      selected: _reason == value,
                      onSelected: _submitting
                          ? null
                          : (_) => setState(() => _reason = value),
                      selectedColor: kResponderBrand,
                      labelStyle: TextStyle(
                        color: _reason == value ? Colors.white : Colors.black87,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _notes,
                enabled: !_submitting,
                minLines: 2,
                maxLines: 4,
                maxLength: 2000,
                decoration: const InputDecoration(
                  labelText: 'Notes (optional)',
                  hintText: 'Add a brief explanation.',
                  border: OutlineInputBorder(),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 4),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
              const SizedBox(height: 10),
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _submitting ? null : _submit,
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF870000),
                  ),
                  child: _submitting
                      ? const SizedBox(
                          width: 19,
                          height: 19,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('SUBMIT FALSE REPORT'),
                ),
              ),
            ],
          ),
        ),
      );
}
