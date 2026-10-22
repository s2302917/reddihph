import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/incident_patient.dart';
import '../../models/responder_report_preview.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/responder_widgets.dart';
import 'hospital_route_screen.dart';
import 'sos_screen.dart';
import 'volunteer_picker_screen.dart';

/// Patient and on-scene vital records for a responder-filed incident.
class ReportNextScreen extends StatefulWidget {
  final ResponderIncidentDraft draft;
  final int? existingIncidentId;
  final MobileApiClient? apiClient;
  final VolunteerProfile? selectedVolunteer;

  const ReportNextScreen({
    super.key,
    required this.draft,
    this.existingIncidentId,
    this.apiClient,
    this.selectedVolunteer,
  });

  @override
  State<ReportNextScreen> createState() => _ReportNextScreenState();
}

class _ReportNextScreenState extends State<ReportNextScreen> {
  final _name = TextEditingController();
  final _age = TextEditingController();
  final _heartRate = TextEditingController();
  final _spo2 = TextEditingController();
  final _bloodPressure = TextEditingController();
  final _notes = TextEditingController();
  final _picker = ImagePicker();
  final List<DraftIncidentPatient> _patients = [];
  final Set<String> _savedPatientClientIds = {};
  final Set<String> _sceneConditions = {};

  String? _gender;
  XFile? _photo;
  ResponderReportPreview? _preview;
  VolunteerProfile? _selectedVolunteer;
  int _nextPatientId = 0;
  bool _working = false;
  bool _reviewing = false;
  bool _vehicleRequested = false;
  int? _incidentId;

  String get _token => AuthController.I.user?['token'] as String? ?? '';
  MobileApiClient get _api => widget.apiClient ?? MobileApiClient.I;
  bool get _existingIncident => widget.existingIncidentId != null;

  static const _conditionOptions = [
    ('conscious', 'Conscious'),
    ('unconscious', 'Unconscious'),
    ('bleeding', 'Bleeding'),
    ('breathing_difficulty', 'Breathing difficulty'),
  ];

  @override
  void dispose() {
    _name.dispose();
    _age.dispose();
    _heartRate.dispose();
    _spo2.dispose();
    _bloodPressure.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _selectedVolunteer = widget.selectedVolunteer;
    _name.text = widget.draft.patientName ?? '';
    _age.text = widget.draft.patientAge?.toString() ?? '';
    final gender = widget.draft.patientGender;
    if (gender != null &&
        const {'male', 'female', 'other', 'unknown'}.contains(gender)) {
      _gender = gender;
    }
  }

  Future<void> _choosePhoto(ImageSource source) async {
    try {
      final image = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 82,
      );
      if (image != null && mounted) setState(() => _photo = image);
    } on PlatformException catch (error) {
      if (mounted) {
        _message(error.message ?? 'Photo access is unavailable.');
      }
    }
  }

  Future<void> _selectPhotoSource() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(children: [
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Choose from gallery'),
            onTap: () => Navigator.pop(context, ImageSource.gallery),
          ),
          ListTile(
            leading: const Icon(Icons.camera_alt_outlined),
            title: const Text('Take a photo'),
            onTap: () => Navigator.pop(context, ImageSource.camera),
          ),
        ]),
      ),
    );
    if (source != null) await _choosePhoto(source);
  }

  int? _optionalNumber(TextEditingController controller, String label,
      {int? maximum}) {
    final value = controller.text.trim();
    if (value.isEmpty) return null;
    final number = int.tryParse(value);
    if (number == null || number < 0 || (maximum != null && number > maximum)) {
      _message('$label is invalid.');
      return -1;
    }
    return number;
  }

  DraftIncidentPatient? _readCurrentPatient() {
    final name = _name.text.trim();
    final age = int.tryParse(_age.text.trim());
    if (name.isEmpty ||
        name.length > 160 ||
        age == null ||
        age < 0 ||
        age > 130 ||
        _gender == null) {
      _message('Enter the patient name, age and sex.');
      return null;
    }
    final heartRate = _optionalNumber(_heartRate, 'Heart rate', maximum: 65535);
    if (heartRate == -1) return null;
    final spo2 = _optionalNumber(_spo2, 'SpO2', maximum: 100);
    if (spo2 == -1) return null;
    if (_bloodPressure.text.trim().length > 15) {
      _message('Blood pressure is too long.');
      return null;
    }

    return DraftIncidentPatient(
      clientId: 'draft-${_nextPatientId++}',
      name: name,
      age: age,
      gender: _gender!,
      heartRate: heartRate,
      spo2: spo2,
      bloodPressure: _bloodPressure.text.trim().isEmpty
          ? null
          : _bloodPressure.text.trim(),
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      sceneConditions: List.unmodifiable(_sceneConditions),
      photo: _photo,
    );
  }

  void _addPatient() {
    final patient = _readCurrentPatient();
    if (patient == null) return;
    setState(() => _patients.add(patient));
    _clearPatientFields();
    _message('Patient added to this draft. Nothing has been reported yet.');
  }

  void _clearPatientFields() {
    _name.clear();
    _age.clear();
    _heartRate.clear();
    _spo2.clear();
    _bloodPressure.clear();
    _notes.clear();
    _sceneConditions.clear();
    _gender = null;
    _photo = null;
  }

  void _deletePatient(DraftIncidentPatient patient) {
    setState(
        () => _patients.removeWhere((p) => p.clientId == patient.clientId));
  }

  Future<void> _askVolunteerForHelp() async {
    if (_working || _incidentId != null) return;
    final token = _token;
    if (token.isEmpty) {
      _message('Sign in again before requesting volunteer help.');
      return;
    }

    final volunteer = await Navigator.of(context).push<VolunteerProfile>(
      MaterialPageRoute<VolunteerProfile>(
        builder: (_) => VolunteerPickerScreen(
          token: token,
          latitude: widget.draft.latitude,
          longitude: widget.draft.longitude,
          apiClient: _api,
        ),
      ),
    );
    if (mounted && volunteer != null) {
      setState(() => _selectedVolunteer = volunteer);
      if (!_reviewing) await _advanceToReview();
    }
  }

  Future<void> _advanceToReview() async {
    if (_working || _incidentId != null) return;
    final patients = List<DraftIncidentPatient>.of(_patients);
    final hasCurrentEntry = _name.text.trim().isNotEmpty ||
        _age.text.trim().isNotEmpty ||
        _gender != null ||
        _heartRate.text.trim().isNotEmpty ||
        _spo2.text.trim().isNotEmpty ||
        _bloodPressure.text.trim().isNotEmpty ||
        _notes.text.trim().isNotEmpty ||
        _sceneConditions.isNotEmpty ||
        _photo != null;
    if (hasCurrentEntry || patients.isEmpty) {
      final current = _readCurrentPatient();
      if (current == null) return;
      patients.add(current);
    }

    setState(() => _working = true);
    try {
      if (_existingIncident) {
        if (!mounted) return;
        setState(() {
          _patients
            ..clear()
            ..addAll(patients);
          _clearPatientFields();
          _reviewing = true;
        });
        return;
      }
      final preview = await _api.previewResponderReport(
        token: _token,
        latitude: widget.draft.latitude,
        longitude: widget.draft.longitude,
      );
      if (!mounted) return;
      if (preview.hospitalId == null || preview.hospitalName.trim().isEmpty) {
        _message('No hospital is assigned to this incident location.');
        return;
      }
      setState(() {
        _patients
          ..clear()
          ..addAll(patients);
        _clearPatientFields();
        _preview = preview;
        _reviewing = true;
      });
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }

  Future<void> _sendReport() async {
    if (_incidentId != null || _working) return;
    if (_patients.isEmpty) {
      _message('Add at least one patient before sending the report.');
      return;
    }
    if (_existingIncident) {
      setState(() => _working = true);
      try {
        for (final patient in _patients) {
          if (_savedPatientClientIds.contains(patient.clientId)) continue;
          await _api.saveResponderPatient(
            token: _token,
            incidentId: widget.existingIncidentId!,
            name: patient.name,
            age: patient.age,
            gender: patient.gender,
            heartRate: patient.heartRate,
            spo2: patient.spo2,
            bloodPressure: patient.bloodPressure,
            notes: patient.notes,
            sceneConditions: patient.sceneConditions,
            photo: patient.photo,
          );
          _savedPatientClientIds.add(patient.clientId);
        }
        final sent = await _api.responderIncidentAction(
          token: _token,
          incidentId: widget.existingIncidentId!,
          action: 'send_to_hospital',
          status: widget.draft.status,
          volunteerUserId: _selectedVolunteer?.id,
        );
        if (!mounted) return;
        final ResponderReportPreview preview;
        try {
          preview = ResponderReportPreview.fromJson({
            'success': true,
            'hospital': sent['hospital'],
            'estimated_road_distance_km': sent['estimated_road_distance_km'],
            'eta_minutes': sent['eta_minutes'],
          });
        } on FormatException {
          _message('The hospital assignment returned invalid routing details.');
          return;
        }
        await Navigator.of(context).pushReplacement<void, void>(
          MaterialPageRoute<void>(
            builder: (_) => HospitalRouteScreen(
              incidentId: widget.existingIncidentId!,
              incidentType: widget.draft.incidentType,
              location: widget.draft.location,
              volunteerName: _selectedVolunteer?.name,
              preview: preview,
              apiClient: _api,
            ),
          ),
        );
      } on MobileApiException catch (error) {
        if (mounted) _message(error.message);
      } finally {
        if (mounted) setState(() => _working = false);
      }
      return;
    }
    final preview = _preview;
    if (preview == null) {
      _message('Load the hospital assignment before sending this report.');
      return;
    }

    setState(() => _working = true);
    try {
      final id = await _api.createResponderReport(
        token: _token,
        incidentType: widget.draft.incidentType,
        status: widget.draft.status,
        reportedBy: widget.draft.reportedBy,
        location: widget.draft.location,
        latitude: widget.draft.latitude,
        longitude: widget.draft.longitude,
        patients: List<DraftIncidentPatient>.of(_patients),
        requestAnotherVehicle: _vehicleRequested,
        volunteerUserId: _selectedVolunteer?.id,
      );
      if (!mounted) return;
      setState(() => _incidentId = id);
      await Navigator.of(context).pushReplacement<void, void>(
        MaterialPageRoute<void>(
          builder: (context) => HospitalRouteScreen(
            incidentId: id,
            incidentType: widget.draft.incidentType,
            location: widget.draft.location,
            preview: preview,
          ),
        ),
      );
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }

  void _message(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Widget _label(String label, {String? supporting}) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            Text(label,
                style:
                    const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            if (supporting != null) ...[
              const SizedBox(width: 4),
              Text(supporting,
                  style: const TextStyle(fontSize: 10, color: Colors.black54)),
            ],
          ],
        ),
      );

  InputDecoration _decoration(String hint) => InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(fontSize: 12, color: Colors.black38),
        isDense: true,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(9),
          borderSide: const BorderSide(color: Colors.black12),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(9),
          borderSide: const BorderSide(color: Colors.black12),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(9),
          borderSide: const BorderSide(color: kResponderBrown, width: 1.4),
        ),
      );

  Widget _textField({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    int maxLines = 1,
  }) =>
      TextField(
        controller: controller,
        keyboardType: keyboardType,
        minLines: maxLines == 1 ? 1 : maxLines,
        maxLines: maxLines,
        style: const TextStyle(fontSize: 14),
        decoration: _decoration(hint),
      );

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          toolbarHeight: 44,
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.white,
          elevation: 0,
        ),
        body: _reviewing
            ? _buildReview()
            : ListView(
                key: const ValueKey('patient-entry-list'),
                padding: const EdgeInsets.fromLTRB(24, 4, 24, 100),
                children: [
                  const Text('Patient details',
                      style:
                          TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  const Text('Write what you can see.',
                      style: TextStyle(fontSize: 12, color: Colors.black54)),
                  const SizedBox(height: 14),
                  _Progress(currentStep: _incidentId == null ? 1 : 2),
                  const SizedBox(height: 18),
                  _label('Patient name'),
                  _textField(controller: _name, hint: 'Patient name'),
                  const SizedBox(height: 12),
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(
                      flex: 4,
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _label('Age'),
                            _textField(
                              controller: _age,
                              hint: 'Age',
                              keyboardType: TextInputType.number,
                            ),
                          ]),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 6,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _label('Sex'),
                          Wrap(
                            spacing: 6,
                            runSpacing: 2,
                            children: [
                              for (final option in const [
                                ('male', 'Male'),
                                ('female', 'Female'),
                                ('other', 'Other'),
                                ('unknown', 'Unknown'),
                              ])
                                _GenderChoice(
                                  label: option.$2,
                                  selected: _gender == option.$1,
                                  onTap: () =>
                                      setState(() => _gender = option.$1),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ]),
                  const SizedBox(height: 14),
                  _label('Condition at scene'),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final (code, label) in _conditionOptions)
                        _ConditionChoice(
                          label: label,
                          selected: _sceneConditions.contains(code),
                          onTap: () => setState(() {
                            if (!_sceneConditions.add(code)) {
                              _sceneConditions.remove(code);
                            }
                          }),
                        ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  _label('Vitals', supporting: '(optional)'),
                  Row(children: [
                    _vitalField(
                        'Pulse', _heartRate, 'bpm', TextInputType.number),
                    const SizedBox(width: 8),
                    _vitalField('SpO2', _spo2, '%', TextInputType.number),
                    const SizedBox(width: 8),
                    _vitalField(
                        'BP', _bloodPressure, 'mmHg', TextInputType.phone),
                  ]),
                  const SizedBox(height: 14),
                  _label('Notes'),
                  _textField(
                    controller: _notes,
                    hint: 'Add relevant observations',
                    maxLines: 2,
                  ),
                  const SizedBox(height: 10),
                  InkWell(
                    onTap: _working || _incidentId != null
                        ? null
                        : _selectPhotoSource,
                    borderRadius: BorderRadius.circular(9),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 10),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: Colors.black12),
                      ),
                      child: Row(children: [
                        const Icon(Icons.add_a_photo_outlined,
                            size: 19, color: kResponderBrown),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            _photo == null ? 'Add patient photo' : _photo!.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 12),
                          ),
                        ),
                        if (_photo != null)
                          IconButton(
                            tooltip: 'Remove photo',
                            visualDensity: VisualDensity.compact,
                            onPressed: _working || _incidentId != null
                                ? null
                                : () => setState(() => _photo = null),
                            icon: const Icon(Icons.close, size: 18),
                          )
                        else
                          const Text('ADD',
                              style: TextStyle(
                                  color: kResponderBrown,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700)),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(
                      child: _SecondaryAction(
                        label: '+ ANOTHER PATIENT',
                        onPressed: _working || _incidentId != null
                            ? null
                            : _addPatient,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _SecondaryAction(
                        label: _selectedVolunteer == null
                            ? 'ASK FOR HELP'
                            : 'VOLUNTEER SELECTED',
                        selected: _selectedVolunteer != null,
                        onPressed: _working || _incidentId != null
                            ? null
                            : _askVolunteerForHelp,
                      ),
                    ),
                  ]),
                  if (_patients.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text('Patients in this draft (${_patients.length})',
                        style: const TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    for (final patient in _patients)
                      _PatientRow(
                          name: patient.name,
                          details: '${patient.age} yrs · ${patient.gender}',
                          hasPhoto: patient.photo != null,
                          onDelete: () => _deletePatient(patient)),
                  ],
                  const SizedBox(height: 14),
                  SizedBox(
                    height: 48,
                    child: FilledButton(
                      onPressed: _working || _incidentId != null
                          ? null
                          : _advanceToReview,
                      style: FilledButton.styleFrom(
                        backgroundColor: kResponderBrown,
                        disabledBackgroundColor:
                            kResponderBrown.withValues(alpha: .6),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10)),
                      ),
                      child: _working
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : const Text(
                              'NEXT',
                              style: TextStyle(
                                  fontSize: 14, fontWeight: FontWeight.w700),
                            ),
                    ),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
        floatingActionButton: FloatingActionButton(
          backgroundColor: kSos,
          shape: const CircleBorder(),
          onPressed: () {
            final role = AuthController.I.role;
            if (role != null) Nav.push(context, SosScreen(role: role));
          },
          child: const Text('SOS',
              style:
                  TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      );

  Widget _buildReview() => ListView(
        key: const ValueKey('report-review-list'),
        padding: const EdgeInsets.fromLTRB(24, 4, 24, 100),
        children: [
          Text(
              _existingIncident
                  ? 'Confirm patient details'
                  : 'Send to hospital',
              style:
                  const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 2),
          Text(
            _existingIncident
                ? 'This sends the patient details to the assigned hospital and opens the route there.'
                : 'This is exactly what the hospital will get.',
            style: const TextStyle(fontSize: 12, color: Colors.black54),
          ),
          const SizedBox(height: 14),
          const _Progress(currentStep: 2),
          const SizedBox(height: 18),
          if (!_existingIncident) ...[
            _HospitalEtaCard(preview: _preview!),
            const SizedBox(height: 12),
          ],
          _ReviewCard(
            title: 'Incident',
            rows: [
              ('Type', widget.draft.incidentType),
              ('Condition', widget.draft.status.toUpperCase()),
              ('Location', widget.draft.location),
              (
                'Coordinates',
                '${widget.draft.latitude.toStringAsFixed(6)}, '
                    '${widget.draft.longitude.toStringAsFixed(6)}',
              ),
            ],
          ),
          for (final patient in _patients) ...[
            const SizedBox(height: 12),
            _ReviewCard(
              title: '${patient.name}, ${patient.age}',
              rows: [
                (
                  'Sex',
                  patient.gender[0].toUpperCase() + patient.gender.substring(1),
                ),
                (
                  'Condition',
                  patient.sceneConditions.isEmpty
                      ? 'Not specified'
                      : patient.sceneConditions.map(_conditionLabel).join(', '),
                ),
                if (patient.heartRate != null)
                  ('Pulse', '${patient.heartRate} bpm'),
                if (patient.spo2 != null) ('SpO2', '${patient.spo2}%'),
                if (patient.bloodPressure != null)
                  ('Blood pressure', patient.bloodPressure!),
                if (patient.notes != null) ('Notes', patient.notes!),
                if (patient.photo != null) ('Photo', 'Attached'),
              ],
            ),
          ],
          if (!_existingIncident)
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              dense: true,
              title: const Text('Request another vehicle'),
              value: _vehicleRequested,
              controlAffinity: ListTileControlAffinity.leading,
              onChanged: _working
                  ? null
                  : (value) =>
                      setState(() => _vehicleRequested = value ?? false),
            ),
          if (_selectedVolunteer != null)
            _ReviewCard(
              title: 'Volunteer assistance',
              rows: [
                ('Volunteer', _selectedVolunteer!.name),
                ('Vehicle', _selectedVolunteer!.vehicleType),
              ],
            ),
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed:
                _working ? null : () => setState(() => _reviewing = false),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
              foregroundColor: kResponderBrown,
              side: const BorderSide(color: Colors.black12),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(9)),
            ),
            child: const Text('BACK TO PATIENT DETAILS'),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 48,
            child: FilledButton(
              onPressed: _working ? null : _sendReport,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFF005B45),
                disabledBackgroundColor:
                    const Color(0xFF005B45).withValues(alpha: .6),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10)),
              ),
              child: _working
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Text(
                      'SEND TO HOSPITAL',
                      style:
                          TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                    ),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'The hospital and barangay admin will receive this report.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Colors.black54),
          ),
        ],
      );

  String _conditionLabel(String code) => switch (code) {
        'conscious' => 'Conscious',
        'unconscious' => 'Unconscious',
        'bleeding' => 'Bleeding',
        'breathing_difficulty' => 'Breathing difficulty',
        _ => code,
      };

  Widget _vitalField(
    String label,
    TextEditingController controller,
    String unit,
    TextInputType type,
  ) =>
      Expanded(
        child: Container(
          height: 64,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.black12),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label,
                  style: const TextStyle(fontSize: 10, color: Colors.black54)),
              const Spacer(),
              TextField(
                controller: controller,
                keyboardType: type,
                style: const TextStyle(fontSize: 13),
                decoration: InputDecoration(
                  hintText: unit,
                  hintStyle:
                      const TextStyle(color: Colors.black38, fontSize: 10),
                  isDense: true,
                  contentPadding: EdgeInsets.zero,
                  border: InputBorder.none,
                ),
              ),
            ],
          ),
        ),
      );
}

class _HospitalEtaCard extends StatelessWidget {
  final ResponderReportPreview preview;

  const _HospitalEtaCard({required this.preview});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFFFE1D8),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    preview.hospitalName,
                    style: const TextStyle(
                        fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 3),
                  const Text('Estimated arrival',
                      style: TextStyle(fontSize: 10, color: Colors.black54)),
                  if (preview.estimatedRoadDistanceKm != null)
                    Text(
                      'Approx. ${preview.estimatedRoadDistanceKm!.toStringAsFixed(1)} km by road',
                      style:
                          const TextStyle(fontSize: 10, color: Colors.black54),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Text(
              preview.etaMinutes == null ? '—' : '${preview.etaMinutes} min',
              style: const TextStyle(
                color: Color(0xFF941A1B),
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      );
}

class _ConditionChoice extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _ConditionChoice({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 34),
          padding: const EdgeInsets.symmetric(horizontal: 10),
          foregroundColor: selected ? Colors.white : Colors.black87,
          backgroundColor: selected ? kResponderBrown : Colors.white,
          side: BorderSide(
            color: selected ? kResponderBrown : const Color(0xFFD5D2CF),
          ),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
        ),
      );
}

class _GenderChoice extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _GenderChoice({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 38),
          padding: const EdgeInsets.symmetric(horizontal: 10),
          foregroundColor: selected ? Colors.white : Colors.black87,
          backgroundColor: selected ? kResponderBrown : Colors.white,
          side: BorderSide(
            color: selected ? kResponderBrown : const Color(0xFFD5D2CF),
          ),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Text(label, style: const TextStyle(fontSize: 11)),
      );
}

class _ReviewCard extends StatelessWidget {
  final String title;
  final List<(String, String)> rows;

  const _ReviewCard({required this.title, required this.rows});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: Colors.black12),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title,
                style:
                    const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            for (final (label, value) in rows)
              Padding(
                padding: const EdgeInsets.only(top: 5),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 90,
                      child: Text(label,
                          style: const TextStyle(
                              fontSize: 12, color: Colors.black54)),
                    ),
                    Expanded(
                      child: Text(value, style: const TextStyle(fontSize: 12)),
                    ),
                  ],
                ),
              ),
          ],
        ),
      );
}

class _SecondaryAction extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback? onPressed;

  const _SecondaryAction({
    required this.label,
    required this.onPressed,
    this.selected = false,
  });

  @override
  Widget build(BuildContext context) => SizedBox(
        height: 42,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            foregroundColor: selected ? kResponderBrown : Colors.black87,
            side:
                BorderSide(color: selected ? kResponderBrown : Colors.black12),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
            padding: const EdgeInsets.symmetric(horizontal: 6),
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
          ),
        ),
      );
}

class _PatientRow extends StatelessWidget {
  final String name;
  final String details;
  final bool hasPhoto;
  final VoidCallback onDelete;

  const _PatientRow({
    required this.name,
    required this.details,
    required this.hasPhoto,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.symmetric(vertical: 3),
        child: ListTile(
          dense: true,
          contentPadding: const EdgeInsets.symmetric(horizontal: 10),
          leading: Icon(
            hasPhoto ? Icons.image : Icons.person_outline,
            color: kResponderBrown,
            size: 18,
          ),
          title: Text(name,
              style:
                  const TextStyle(fontSize: 10, fontWeight: FontWeight.w600)),
          subtitle: Text(
            details,
            maxLines: 2,
            style: const TextStyle(fontSize: 8),
          ),
          trailing: IconButton(
            tooltip: 'Delete patient',
            visualDensity: VisualDensity.compact,
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline, size: 18),
          ),
        ),
      );
}

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
