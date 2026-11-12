import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../models/incident_patient.dart';
import '../../models/responder_dashboard.dart';
import '../../models/triage_category.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/incident_type_selector.dart';
import 'report_next_screen.dart';

class ResponderSosIncidentDetailsScreen extends StatefulWidget {
  const ResponderSosIncidentDetailsScreen({
    super.key,
    required this.incidentId,
    required this.incidentType,
    required this.triageCategory,
    required this.location,
    required this.point,
    required this.incident,
    required this.apiClient,
    this.alreadySentToHospital = false,
    this.selectedVolunteer,
  });

  final int incidentId;
  final String incidentType;
  final String? triageCategory;
  final String location;
  final LatLng point;
  final ResponderIncident? incident;
  final MobileApiClient apiClient;
  final bool alreadySentToHospital;
  final VolunteerProfile? selectedVolunteer;

  @override
  State<ResponderSosIncidentDetailsScreen> createState() =>
      _ResponderSosIncidentDetailsScreenState();
}

class _ResponderSosIncidentDetailsScreenState
    extends State<ResponderSosIncidentDetailsScreen> {
  static const _categories = EmergencyCategory.values;

  final _otherType = TextEditingController();
  String? _selectedType;
  String? _selectedCategory;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _selectedCategory = widget.triageCategory;
    final current = widget.incidentType.trim();
    for (final type in incidentTypeOptions.where((type) => type != 'Other')) {
      if (type.toLowerCase() == current.toLowerCase()) {
        _selectedType = type;
        return;
      }
    }
    if (current.isNotEmpty && current.toLowerCase() != 'sos emergency alert') {
      _selectedType = 'Other';
      _otherType.text = current;
    }
  }

  @override
  void dispose() {
    _otherType.dispose();
    super.dispose();
  }

  String? get _resolvedType {
    if (_selectedType == 'Other') {
      final value = _otherType.text.trim();
      return value.isEmpty ? null : value;
    }
    return _selectedType;
  }

  Future<void> _continueToPatientDetails() async {
    final incidentType = _resolvedType;
    if (incidentType == null) {
      setState(() => _error = 'Select or enter the real incident type.');
      return;
    }
    final triageCategory = _selectedCategory;
    if (triageCategory == null) {
      setState(() => _error = 'Select an emergency category for this patient.');
      return;
    }
    final token = AuthController.I.user?['token'] as String? ?? '';
    if (token.isEmpty) {
      setState(
          () => _error = 'Please sign in again before updating this case.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      if (!widget.alreadySentToHospital) {
        await widget.apiClient.responderIncidentAction(
          token: token,
          incidentId: widget.incidentId,
          action: 'update_incident_type',
          incidentType: incidentType,
        );
      }
      if (!mounted) return;
      final saved = await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => ReportNextScreen(
            existingIncidentId: widget.incidentId,
            draft: ResponderIncidentDraft(
              incidentType: incidentType,
              triageCategory: triageCategory,
              reportedBy: widget.incident?.reportedBy ?? 'resident_sos',
              location: widget.location,
              latitude: widget.point.latitude,
              longitude: widget.point.longitude,
              patientName: widget.incident?.patientName,
              patientAge: widget.incident?.patientAge,
              patientGender: widget.incident?.patientGender,
            ),
            apiClient: widget.apiClient,
            selectedVolunteer: widget.selectedVolunteer,
            alreadySentToHospital: widget.alreadySentToHospital,
          ),
        ),
      );
      if (mounted && saved == true) Navigator.of(context).pop(true);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: Text(
            widget.alreadySentToHospital
                ? 'Update incident details'
                : 'Incident details',
          ),
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          children: [
            Text(
              'Incident #${widget.incidentId}',
              style: const TextStyle(
                  fontSize: 12,
                  color: Colors.black54,
                  fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
            Text(
              widget.alreadySentToHospital
                  ? 'What has changed at the scene?'
                  : 'What is the real emergency?',
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 5),
            Text(
              widget.alreadySentToHospital
                  ? 'Review the incident type and emergency category, then add patient details for the hospital.'
                  : 'Replace the SOS label with the incident type observed at the scene.',
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
            const SizedBox(height: 20),
            IncidentTypeSelector(
              selectedType: _selectedType ?? '',
              onChanged: (type) => setState(() {
                _selectedType = type;
                _error = null;
              }),
              otherTypeController: _otherType,
              enabled: !_saving,
            ),
            const SizedBox(height: 14),
            const Text(
              'Emergency category',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                for (final category in _categories)
                  ChoiceChip(
                    label: Text(category.label),
                    selected: _selectedCategory == category.value,
                    onSelected: _saving
                        ? null
                        : (selected) {
                            if (selected) {
                              setState(
                                () => _selectedCategory = category.value,
                              );
                            }
                          },
                    selectedColor: category.color,
                    labelStyle: TextStyle(
                      color: _selectedCategory == category.value
                          ? category.textColor
                          : Colors.black87,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 18),
            _DetailRow(label: 'Location', value: widget.location),
            if (widget.incident?.patientName?.trim().isNotEmpty == true)
              _DetailRow(
                label: 'Reported patient',
                value: widget.incident!.patientName!,
              ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!,
                  style: const TextStyle(color: Colors.red, fontSize: 12)),
            ],
            const SizedBox(height: 20),
            SizedBox(
              height: 48,
              child: FilledButton(
                onPressed: _saving ? null : _continueToPatientDetails,
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFF005B45),
                ),
                child: _saving
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('CONTINUE TO PATIENT DETAILS'),
              ),
            ),
          ],
        ),
      );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(
            width: 110,
            child: Text(label,
                style: const TextStyle(fontSize: 11, color: Colors.black54)),
          ),
          Expanded(
            child: Text(value,
                style:
                    const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
          ),
        ]),
      );
}
