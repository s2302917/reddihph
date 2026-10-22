import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/incident_patient.dart';
import '../../models/role.dart';
import '../../models/volunteer.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/incident_type_selector.dart';
import 'sos_screen.dart';
import 'sos_confirmed_screen.dart';

class ResidentIncidentReportScreen extends StatefulWidget {
  const ResidentIncidentReportScreen({super.key});

  @override
  State<ResidentIncidentReportScreen> createState() =>
      _ResidentIncidentReportScreenState();
}

class _ResidentIncidentReportScreenState
    extends State<ResidentIncidentReportScreen> {
  static const _conditions = ['critical', 'urgent', 'stable'];
  static const _genders = ['male', 'female', 'other', 'unknown'];

  final _incidentFormKey = GlobalKey<FormState>();
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _age = TextEditingController();
  final _heartRate = TextEditingController();
  final _spo2 = TextEditingController();
  final _bloodPressure = TextEditingController();
  final _notes = TextEditingController();
  final _otherIncidentType = TextEditingController();
  final _photoPicker = ImagePicker();
  final _patients = <DraftIncidentPatient>[];
  final _sceneConditions = <String>{};
  XFile? _patientPhoto;
  List<VolunteerProfile> _volunteers = [];
  VolunteerProfile? _selectedVolunteer;
  double? _latitude;
  double? _longitude;
  String _selectedIncidentType = '';
  String _condition = _conditions.first;
  String _gender = _genders.last;
  String _reportedBy = 'resident_sos';
  String? _locationError;
  String? _rosterError;
  int _step = 0;
  bool _loadingLocation = true;
  bool _loadingRoster = false;
  bool _requestAnotherVehicle = false;
  bool _submitting = false;

  String? get _token => AuthController.I.user?['token'] as String?;
  String get _incidentType => _selectedIncidentType == 'Other'
      ? _otherIncidentType.text.trim()
      : _selectedIncidentType;

  @override
  void initState() {
    super.initState();
    _loadLocation();
  }

  @override
  void dispose() {
    _name.dispose();
    _age.dispose();
    _heartRate.dispose();
    _spo2.dispose();
    _bloodPressure.dispose();
    _notes.dispose();
    _otherIncidentType.dispose();
    super.dispose();
  }

  Future<void> _loadLocation() async {
    setState(() {
      _loadingLocation = true;
      _locationError = null;
      _latitude = null;
      _longitude = null;
    });
    try {
      final position = await LocationService.currentPosition();
      if (!mounted) return;
      setState(() {
        _latitude = position.latitude;
        _longitude = position.longitude;
        _loadingLocation = false;
      });
    } on LocationUnavailableException catch (error) {
      if (!mounted) return;
      setState(() {
        _locationError = error.message;
        _loadingLocation = false;
      });
    }
  }

  Future<void> _openVolunteerRoster() async {
    if (_step == 1 && _hasCurrentPatientInput) {
      if (!_stageCurrentPatient()) return;
    }
    if (_patients.isEmpty) {
      _message('Add at least one patient before continuing.');
      return;
    }
    final token = _token;
    final latitude = _latitude;
    final longitude = _longitude;
    if (token == null) {
      _message('Sign in again before requesting volunteer help.');
      return;
    }
    if (latitude == null || longitude == null) {
      _message('Pin the incident location before continuing.');
      return;
    }
    setState(() {
      _loadingRoster = true;
      _rosterError = null;
      _volunteers = [];
      _step = 2;
    });
    try {
      final volunteers = await MobileApiClient.I.volunteersForIncident(
        token: token,
        latitude: latitude,
        longitude: longitude,
      );
      if (!mounted) return;
      setState(() {
        _volunteers = volunteers;
        _selectedVolunteer = null;
        _loadingRoster = false;
        _step = 2;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _rosterError = error.message;
        _loadingRoster = false;
      });
    }
  }

  void _review({bool withoutVolunteer = false}) {
    if (withoutVolunteer) _selectedVolunteer = null;
    setState(() => _step = 3);
  }

  bool get _hasCurrentPatientInput =>
      _name.text.trim().isNotEmpty ||
      _age.text.trim().isNotEmpty ||
      _heartRate.text.trim().isNotEmpty ||
      _spo2.text.trim().isNotEmpty ||
      _bloodPressure.text.trim().isNotEmpty ||
      _notes.text.trim().isNotEmpty ||
      _sceneConditions.isNotEmpty ||
      _patientPhoto != null;

  bool _stageCurrentPatient() {
    if (!(_formKey.currentState?.validate() ?? false)) return false;
    final age = int.tryParse(_age.text.trim());
    if (age == null) {
      _message('Enter a valid patient age.');
      return false;
    }
    final heartRate = _optionalVital(_heartRate, 'Pulse', 65535);
    final spo2 = _optionalVital(_spo2, 'SpO2', 100);
    if (heartRate == -1 || spo2 == -1) return false;
    final bloodPressure = _bloodPressure.text.trim();
    if (bloodPressure.length > 15) {
      _message('Blood pressure must be 15 characters or fewer.');
      return false;
    }
    _patients.add(
      DraftIncidentPatient(
        clientId: 'resident-${_patients.length}',
        name: _name.text.trim(),
        age: age,
        gender: _gender,
        heartRate: heartRate,
        spo2: spo2,
        bloodPressure: bloodPressure.isEmpty ? null : bloodPressure,
        notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        sceneConditions: List.unmodifiable(_sceneConditions),
        photo: _patientPhoto,
      ),
    );
    _clearPatientFields();
    return true;
  }

  void _addPatient() {
    if (_patients.length >= 20) {
      _message('A report can include up to 20 patients.');
      return;
    }
    if (!_stageCurrentPatient()) return;
    setState(() {});
  }

  void _clearPatientFields() {
    _name.clear();
    _age.clear();
    _heartRate.clear();
    _spo2.clear();
    _bloodPressure.clear();
    _notes.clear();
    _sceneConditions.clear();
    _gender = _genders.last;
    _patientPhoto = null;
  }

  void _nextToPatientDetails() {
    if (!(_incidentFormKey.currentState?.validate() ?? false)) return;
    if (_selectedIncidentType.isEmpty) {
      _message('Select an incident type first.');
      return;
    }
    if (_latitude == null || _longitude == null) {
      _message('Pin the incident location before continuing.');
      return;
    }
    setState(() => _step = 1);
  }

  void _back() {
    if (_submitting) return;
    if (_step == 0) {
      Navigator.maybePop(context);
    } else {
      setState(() => _step--);
    }
  }

  Future<void> _sendReport() async {
    final latitude = _latitude;
    final longitude = _longitude;
    if (latitude == null || longitude == null || _patients.isEmpty) {
      _message('Check the patient details and incident location.');
      return;
    }

    setState(() => _submitting = true);
    try {
      await SosController.I.submitResidentReport(
        latitude: latitude,
        longitude: longitude,
        incidentType: _incidentType,
        status: _condition,
        patientName: _patients.first.name,
        patientAge: _patients.first.age,
        patientGender: _patients.first.gender,
        notes: _patients.first.notes ?? '',
        volunteerUserId: _selectedVolunteer?.id,
        patients: List.unmodifiable(_patients),
        requestAnotherVehicle: _requestAnotherVehicle,
        reportedBy: _reportedBy,
      );
      if (!mounted) return;
      Nav.replace(context, const SosConfirmedScreen());
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  int? _optionalVital(
    TextEditingController controller,
    String label,
    int maximum,
  ) {
    final value = controller.text.trim();
    if (value.isEmpty) return null;
    final number = int.tryParse(value);
    if (number == null || number < 0 || number > maximum) {
      _message('$label is invalid.');
      return -1;
    }
    return number;
  }

  Future<void> _choosePatientPhoto() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
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
          ],
        ),
      ),
    );
    if (source == null) return;
    try {
      final photo = await _photoPicker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 82,
      );
      if (photo != null && mounted) setState(() => _patientPhoto = photo);
    } on PlatformException catch (error) {
      if (mounted) _message(error.message ?? 'Photo access is unavailable.');
    }
  }

  void _message(String text) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const SizedBox.shrink(),
          leading: IconButton(
            tooltip: 'Back',
            onPressed: _back,
            icon: const Icon(Icons.arrow_back),
          ),
        ),
        body: Column(
          children: [
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                child: switch (_step) {
                  1 => _patientStep(),
                  2 => _volunteerStep(),
                  3 => _reviewStep(),
                  _ => _incidentStep(),
                },
              ),
            ),
            _footer(),
          ],
        ),
        floatingActionButton: FloatingActionButton(
          backgroundColor: kSos,
          shape: const CircleBorder(),
          onPressed: () => Nav.push(
            context,
            SosScreen(role: AuthController.I.role ?? roles.first),
          ),
          child: const Text(
            'SOS',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
          ),
        ),
      );

  Widget _progress() {
    final labels = _step < 2
        ? const ['Incident', 'Patient', 'Send']
        : const ['Incident', 'Patient', 'Help', 'Send'];
    final activeStep = _step;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
      child: Row(
        children: [
          for (var index = 0; index < labels.length; index++) ...[
            Expanded(
              child: Column(
                children: [
                  Container(
                    height: 6,
                    decoration: BoxDecoration(
                      color:
                          index <= activeStep ? kResidentBrand : Colors.black12,
                      borderRadius: BorderRadius.circular(6),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    labels[index],
                    style: TextStyle(
                      color:
                          index <= activeStep ? kResidentBrand : Colors.black54,
                      fontSize: 10,
                      fontWeight: index == activeStep
                          ? FontWeight.w700
                          : FontWeight.normal,
                    ),
                  ),
                ],
              ),
            ),
            if (index < labels.length - 1) const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }

  Widget _incidentStep() => Form(
        key: _incidentFormKey,
        child: ListView(
          key: const ValueKey('resident-incident-form'),
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
          children: [
            const Text(
              'Incident details',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            const Text(
              'Tell us what happened.',
              style: TextStyle(color: Colors.black54, fontSize: 12),
            ),
            _progress(),
            const SizedBox(height: 18),
            IncidentTypeSelector(
              selectedType: _selectedIncidentType,
              onChanged: (type) => setState(() => _selectedIncidentType = type),
              otherTypeController: _otherIncidentType,
              color: kResidentBrand,
            ),
            const SizedBox(height: 16),
            _label('Condition'),
            Wrap(
              spacing: 7,
              runSpacing: 6,
              children: [
                for (final condition in _conditions)
                  _choicePill(
                    _title(condition),
                    selected: _condition == condition,
                    onTap: () => setState(() => _condition = condition),
                    color: kResidentBrand,
                  ),
              ],
            ),
            const SizedBox(height: 16),
            _label('Location'),
            _locationCard(),
            const SizedBox(height: 16),
            _label('Reported by'),
            Wrap(
              spacing: 8,
              children: [
                _choicePill(
                  'On scene',
                  selected: _reportedBy == 'on_scene',
                  onTap: () => setState(() => _reportedBy = 'on_scene'),
                  color: kResidentBrand,
                ),
                _choicePill(
                  'Resident SOS',
                  selected: _reportedBy == 'resident_sos',
                  onTap: () => setState(() => _reportedBy = 'resident_sos'),
                  color: kResidentBrand,
                ),
              ],
            ),
          ],
        ),
      );

  Widget _patientStep() => Form(
        key: _formKey,
        child: ListView(
          key: const ValueKey('resident-patient-form'),
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
          children: [
            const Text(
              'Patient details',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            const Text(
              'Write what you can see.',
              style: TextStyle(color: Colors.black54, fontSize: 12),
            ),
            _progress(),
            const SizedBox(height: 18),
            _label('Patient name'),
            TextFormField(
              key: const ValueKey('resident-patient-name-field'),
              controller: _name,
              textCapitalization: TextCapitalization.words,
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Enter the patient name.'
                  : null,
              style: const TextStyle(fontSize: 14),
              decoration: _decoration('Patient name'),
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 4,
                  child: Column(
                    key: const ValueKey('resident-patient-age-column'),
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _label('Age'),
                      TextFormField(
                        key: const ValueKey('resident-patient-age-field'),
                        controller: _age,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                          LengthLimitingTextInputFormatter(3),
                        ],
                        validator: (value) {
                          final age = int.tryParse((value ?? '').trim());
                          return age == null || age > 130
                              ? 'Enter a valid age.'
                              : null;
                        },
                        style: const TextStyle(fontSize: 14),
                        decoration: _decoration('Age'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 6,
                  child: Column(
                    key: const ValueKey('resident-patient-sex-column'),
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _label('Sex'),
                      Wrap(
                        spacing: 6,
                        runSpacing: 2,
                        children: [
                          for (final gender in _genders)
                            _choicePill(
                              _title(gender),
                              selected: _gender == gender,
                              onTap: () => setState(() => _gender = gender),
                              color: kResidentBrand,
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _label('Condition at scene'),
            Wrap(
              spacing: 7,
              runSpacing: 6,
              children: [
                for (final condition in const [
                  ('conscious', 'Conscious'),
                  ('unconscious', 'Unconscious'),
                  ('bleeding', 'Bleeding'),
                  ('breathing_difficulty', 'Breathing difficulty'),
                ])
                  _choicePill(
                    condition.$2,
                    selected: _sceneConditions.contains(condition.$1),
                    onTap: () => setState(() {
                      if (!_sceneConditions.add(condition.$1)) {
                        _sceneConditions.remove(condition.$1);
                      }
                    }),
                    color: kResidentBrand,
                  ),
              ],
            ),
            const SizedBox(height: 12),
            _label('Vitals (optional)'),
            Row(
              children: [
                _vitalField('Pulse', _heartRate, 'bpm'),
                const SizedBox(width: 8),
                _vitalField('SpO2', _spo2, '%'),
                const SizedBox(width: 8),
                _vitalField('BP', _bloodPressure, 'mmHg'),
              ],
            ),
            const SizedBox(height: 12),
            _label('Notes'),
            TextField(
              controller: _notes,
              minLines: 3,
              maxLines: 4,
              maxLength: 10000,
              decoration: _decoration('Add relevant observations'),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _submitting ? null : _choosePatientPhoto,
              icon: const Icon(Icons.add_a_photo_outlined),
              label: Text(
                _patientPhoto == null
                    ? 'Add patient photo'
                    : 'Photo added · tap to replace',
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: kResidentBrand,
                side: const BorderSide(color: Colors.black12),
                minimumSize: const Size.fromHeight(44),
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: _submitting ? null : _addPatient,
              style: OutlinedButton.styleFrom(
                foregroundColor: kResidentBrand,
                side: const BorderSide(color: Colors.black26),
                minimumSize: const Size.fromHeight(42),
              ),
              child: const Text('+ ANOTHER PATIENT'),
            ),
            if (_patients.isNotEmpty) ...[
              const SizedBox(height: 12),
              _label('Patients in this report (${_patients.length})'),
              for (final patient in _patients)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  leading: Icon(
                    patient.photo == null
                        ? Icons.person_outline
                        : Icons.image_outlined,
                    color: kResidentBrand,
                  ),
                  title: Text(patient.name),
                  subtitle: Text('${patient.age} · ${_title(patient.gender)}'),
                  trailing: IconButton(
                    tooltip: 'Remove ${patient.name}',
                    onPressed: _submitting
                        ? null
                        : () => setState(() => _patients.remove(patient)),
                    icon: const Icon(Icons.delete_outline),
                  ),
                ),
            ],
          ],
        ),
      );

  Widget _locationCard() {
    final coordinates = _latitude == null || _longitude == null
        ? null
        : '${_latitude!.toStringAsFixed(6)}, ${_longitude!.toStringAsFixed(6)}';
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.black12),
      ),
      child: Row(
        children: [
          const Icon(Icons.location_on_outlined, color: kResidentBrand),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _loadingLocation
                      ? 'Getting your current location…'
                      : _locationError ??
                          'GPS ${coordinates ?? 'Location unavailable'}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                const Text(
                  'Using current device location',
                  style: TextStyle(fontSize: 11, color: Colors.black54),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: _loadingLocation ? null : _loadLocation,
            child: Text(coordinates == null ? 'Retry' : 'Change'),
          ),
        ],
      ),
    );
  }

  Widget _volunteerStep() => ListView(
        key: const ValueKey('resident-volunteer-list'),
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
        children: [
          const Text(
            'Ask a volunteer for help',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'Optional. Choose a registered volunteer to assist at the scene while responders are dispatched.',
            style: TextStyle(color: Colors.black54, fontSize: 12),
          ),
          _progress(),
          const SizedBox(height: 14),
          Chip(
            label: Text(
              'Volunteers ${_loadingRoster ? '…' : _volunteers.length}',
            ),
            backgroundColor: kResidentBrand,
            labelStyle: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          if (_rosterError != null) ...[
            _errorCard(_rosterError!, onRetry: _openVolunteerRoster),
            const SizedBox(height: 10),
          ],
          if (_loadingRoster)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 28),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_volunteers.isEmpty && _rosterError == null)
            const _EmptyRoster()
          else
            for (final volunteer in _volunteers) ...[
              _VolunteerCard(
                volunteer: volunteer,
                selected: _selectedVolunteer?.id == volunteer.id,
                onTap: volunteer.available
                    ? () => setState(() => _selectedVolunteer = volunteer)
                    : null,
              ),
              const SizedBox(height: 9),
            ],
          if (_rosterError == null && !_loadingRoster)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: kResidentBrand.withAlpha(35),
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Text(
                'Responder dispatch continues even when no volunteer is selected.',
                style: TextStyle(fontSize: 11, color: Colors.black54),
              ),
            ),
        ],
      );

  Widget _reviewStep() => ListView(
        key: const ValueKey('resident-report-review'),
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
        children: [
          const Text(
            'Send to responders',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'This is the incident report responder dispatch will receive.',
            style: TextStyle(color: Colors.black54, fontSize: 12),
          ),
          _progress(),
          const SizedBox(height: 16),
          _dispatchCard(),
          const SizedBox(height: 12),
          for (final patient in _patients) ...[
            _summaryCard('Patient · ${patient.name}, ${patient.age}', [
              ('Sex', _title(patient.gender)),
              if (patient.sceneConditions.isNotEmpty)
                ('Condition', patient.sceneConditions.map(_title).join(', ')),
              if (patient.heartRate != null)
                ('Pulse', '${patient.heartRate} bpm'),
              if (patient.spo2 != null) ('SpO2', '${patient.spo2}%'),
              if (patient.bloodPressure != null) ('BP', patient.bloodPressure!),
              if (patient.photo != null) ('Photo', 'Attached'),
              if (patient.notes != null) ('Notes', patient.notes!),
            ]),
            const SizedBox(height: 12),
          ],
          const SizedBox(height: 12),
          _summaryCard('Incident', [
            ('Type', _incidentType),
            ('Condition', _title(_condition)),
            (
              'Reported by',
              _reportedBy == 'on_scene' ? 'On scene' : 'Resident SOS'
            ),
          ]),
          const SizedBox(height: 12),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: const Text('Request another vehicle'),
            value: _requestAnotherVehicle,
            controlAffinity: ListTileControlAffinity.leading,
            onChanged: _submitting
                ? null
                : (value) => setState(
                      () => _requestAnotherVehicle = value ?? false,
                    ),
          ),
          const SizedBox(height: 4),
          _summaryCard('Volunteer assistance', [
            (
              'Selected',
              _selectedVolunteer?.name ?? 'No volunteer selected',
            ),
            if (_selectedVolunteer != null)
              ('Vehicle', _selectedVolunteer!.vehicleType),
          ]),
          const SizedBox(height: 12),
          _summaryCard('Location', [
            (
              'Coordinates',
              '${_latitude!.toStringAsFixed(6)}, '
                  '${_longitude!.toStringAsFixed(6)}',
            ),
          ]),
        ],
      );

  Widget _dispatchCard() {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: kResidentBrand.withAlpha(36),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        children: [
          Icon(Icons.support_agent, color: kResidentBrand),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Responder dispatch',
                    style: TextStyle(fontSize: 11, color: Colors.black54)),
                SizedBox(height: 3),
                Text(
                  'Send this incident to responders',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryCard(String title, List<(String, String)> rows) => Container(
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

  Widget _footer() {
    if (_step == 0) {
      return _footerButton(
        label: 'NEXT',
        onPressed: _loadingLocation ? null : _nextToPatientDetails,
      );
    }
    if (_step == 1) {
      return _footerButton(
        label: 'NEXT: ASK A VOLUNTEER',
        onPressed: _loadingLocation ? null : _openVolunteerRoster,
      );
    }
    if (_step == 2) {
      return _footerButton(
        label: 'NEXT: REVIEW AND SEND',
        onPressed: _loadingRoster || _patients.isEmpty
            ? null
            : () => _review(withoutVolunteer: _selectedVolunteer == null),
      );
    }
    return _footerButton(
      label: _submitting ? 'SENDING…' : 'SEND TO RESPONDER',
      onPressed: _submitting ? null : _sendReport,
      child: _submitting
          ? const SizedBox(
              height: 18,
              width: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: Colors.white,
              ),
            )
          : null,
    );
  }

  Widget _footerButton({
    required String label,
    required VoidCallback? onPressed,
    Widget? child,
  }) =>
      SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: SizedBox(
            height: 48,
            width: double.infinity,
            child: FilledButton(
              onPressed: onPressed,
              style: FilledButton.styleFrom(backgroundColor: kResidentBrand),
              child: child ?? Text(label),
            ),
          ),
        ),
      );

  Widget _errorCard(String message, {required VoidCallback onRetry}) =>
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF0EF),
          borderRadius: BorderRadius.circular(9),
        ),
        child: Row(
          children: [
            Expanded(
                child: Text(message, style: const TextStyle(fontSize: 12))),
            TextButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      );

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 5),
        child: Text(text,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
      );

  Widget _choicePill(
    String label, {
    required bool selected,
    required VoidCallback onTap,
    required Color color,
  }) =>
      OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 38),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          foregroundColor: selected ? Colors.white : Colors.black87,
          backgroundColor: selected ? color : Colors.white,
          side: BorderSide(color: selected ? color : Colors.black26),
          shape: const StadiumBorder(),
          visualDensity: VisualDensity.compact,
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      );

  Widget _vitalField(
    String label,
    TextEditingController controller,
    String unit,
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
              Text(
                label,
                style: const TextStyle(fontSize: 10, color: Colors.black54),
              ),
              const Spacer(),
              TextField(
                controller: controller,
                keyboardType:
                    label == 'BP' ? TextInputType.text : TextInputType.number,
                inputFormatters: [
                  if (label == 'BP')
                    LengthLimitingTextInputFormatter(15)
                  else
                    FilteringTextInputFormatter.digitsOnly,
                ],
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
          borderSide: const BorderSide(color: kResidentBrand, width: 1.4),
        ),
      );

  String _title(String value) =>
      value.isEmpty ? value : '${value[0].toUpperCase()}${value.substring(1)}';
}

class _VolunteerCard extends StatelessWidget {
  const _VolunteerCard({
    required this.volunteer,
    required this.selected,
    required this.onTap,
  });

  final VolunteerProfile volunteer;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Material(
        color: selected ? const Color(0xFFEAF3F0) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              border: Border.all(
                color: selected ? kResidentBrand : Colors.black12,
                width: selected ? 1.5 : 1,
              ),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  backgroundColor: const Color(0xFFE6EEEB),
                  child: Icon(
                    _vehicleIcon(volunteer.vehicleType),
                    color: kResidentBrand,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(volunteer.name,
                          style: const TextStyle(
                              fontSize: 14, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 3),
                      Text(
                        volunteer.vehicleType,
                        style: const TextStyle(
                            fontSize: 12, color: Colors.black54),
                      ),
                      const SizedBox(height: 7),
                      Wrap(
                        spacing: 10,
                        runSpacing: 5,
                        children: [
                          _detail(
                            Icons.star,
                            volunteer.rating == null
                                ? 'No ratings'
                                : volunteer.rating!.toStringAsFixed(1),
                          ),
                          _detail(Icons.volunteer_activism_outlined,
                              '${volunteer.assistedIncidents} assisted'),
                          _detail(
                            Icons.near_me_outlined,
                            volunteer.distanceKm == null
                                ? 'Distance unavailable'
                                : '${volunteer.distanceKm!.toStringAsFixed(1)} km away',
                          ),
                        ],
                      ),
                      if (!volunteer.available)
                        const Padding(
                          padding: EdgeInsets.only(top: 7),
                          child: Text(
                            'Currently unavailable',
                            style: TextStyle(
                              color: Colors.black54,
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                Icon(
                  selected
                      ? Icons.radio_button_checked
                      : Icons.radio_button_unchecked,
                  color: onTap == null
                      ? Colors.black26
                      : selected
                          ? kResidentBrand
                          : Colors.black45,
                ),
              ],
            ),
          ),
        ),
      );

  Widget _detail(IconData icon, String label) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon,
              size: 14,
              color: icon == Icons.star ? Colors.amber[800] : kResidentBrand),
          const SizedBox(width: 3),
          Text(label, style: const TextStyle(fontSize: 10)),
        ],
      );

  IconData _vehicleIcon(String value) {
    final vehicle = value.toLowerCase();
    if (vehicle.contains('ambulance')) return Icons.emergency;
    if (vehicle.contains('motorcycle') || vehicle.contains('motor')) {
      return Icons.two_wheeler;
    }
    if (vehicle.contains('car') ||
        vehicle.contains('van') ||
        vehicle.contains('ptv')) {
      return Icons.directions_car;
    }
    return Icons.volunteer_activism_outlined;
  }
}

class _EmptyRoster extends StatelessWidget {
  const _EmptyRoster();

  @override
  Widget build(BuildContext context) => const Padding(
        padding: EdgeInsets.symmetric(vertical: 32),
        child: Column(
          children: [
            Icon(Icons.people_outline, size: 38, color: Colors.black38),
            SizedBox(height: 8),
            Text('No registered volunteers yet',
                style: TextStyle(fontWeight: FontWeight.w700)),
            SizedBox(height: 4),
            Text(
              'You can continue and send the report to responder dispatch without a volunteer.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, color: Colors.black54),
            ),
          ],
        ),
      );
}
