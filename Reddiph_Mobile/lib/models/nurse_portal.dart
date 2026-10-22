class NurseProfile {
  const NurseProfile({
    required this.id,
    required this.fullName,
    required this.hospitalName,
    required this.department,
    required this.isAvailable,
    required this.notificationsEnabled,
    required this.language,
    this.licenseNumber,
    this.nextShift,
  });

  final int id;
  final String fullName;
  final String hospitalName;
  final String department;
  final bool isAvailable;
  final bool notificationsEnabled;
  final String language;
  final String? licenseNumber;
  final String? nextShift;

  String get firstName {
    final parts = fullName.trim().split(RegExp(r'\s+'));
    return parts.isEmpty ? '' : parts.first;
  }

  factory NurseProfile.fromJson(Map<String, dynamic> json) {
    return NurseProfile(
      id: _intValue(json['id'], 'nurse id'),
      fullName: _stringValue(json['full_name'], 'nurse name'),
      hospitalName: _stringValue(json['hospital_name'], 'hospital name'),
      department: _stringValue(json['department'], 'department'),
      isAvailable: _boolValue(json['is_available'], 'duty status'),
      notificationsEnabled:
          _boolValue(json['notifications_enabled'], 'notification setting'),
      language: _stringValue(json['language'], 'language'),
      licenseNumber: _optionalString(json['license_number']),
      nextShift: _optionalString(json['next_shift']),
    );
  }
}

class NursePrepTask {
  const NursePrepTask({
    required this.id,
    required this.name,
    required this.done,
    this.doneAt,
  });

  final int id;
  final String name;
  final bool done;
  final String? doneAt;

  factory NursePrepTask.fromJson(Map<String, dynamic> json) => NursePrepTask(
        id: _intValue(json['id'], 'task id'),
        name: _stringValue(json['name'], 'task name'),
        done: _boolValue(json['done'], 'task status'),
        doneAt: _optionalString(json['done_at']),
      );
}

class NurseCaseRecord {
  const NurseCaseRecord({
    required this.id,
    required this.title,
    required this.priority,
    required this.status,
    required this.patientName,
    required this.patientGender,
    required this.location,
    required this.responder,
    required this.unit,
    required this.tasks,
    required this.prepDone,
    required this.prepTotal,
    required this.prepComplete,
    required this.arrivalConfirmed,
    this.patientAge,
    this.etaMinutes,
    this.bedId,
    this.bedLabel,
    this.note = '',
    this.responderNotes = '',
    this.heartRate,
    this.spo2,
    this.bloodPressure,
    this.reportedAt,
    this.arrivedAt,
    this.transportType = '',
  });

  final int id;
  final String title;
  final String priority;
  final String status;
  final String patientName;
  final String patientGender;
  final String location;
  final String responder;
  final String unit;
  final List<NursePrepTask> tasks;
  final int prepDone;
  final int prepTotal;
  final bool prepComplete;
  final bool arrivalConfirmed;
  final int? patientAge;
  final int? etaMinutes;
  final int? bedId;
  final String? bedLabel;
  final String note;
  final String responderNotes;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final String? reportedAt;
  final String? arrivedAt;
  final String transportType;

  factory NurseCaseRecord.fromJson(Map<String, dynamic> json) {
    final tasksJson = json['tasks'];
    if (tasksJson is! List) {
      throw const FormatException('Invalid nurse preparation tasks.');
    }
    return NurseCaseRecord(
      id: _intValue(json['id'], 'incident id'),
      title: _stringValue(json['title'], 'case title'),
      priority: _stringValue(json['priority'], 'case priority'),
      status: _stringValue(json['status'], 'case status'),
      patientName: _stringValue(json['patient_name'], 'patient name'),
      patientGender: _stringValue(json['patient_gender'], 'patient gender'),
      location: _stringValue(json['location'], 'incident location'),
      responder: _stringValue(json['responder'], 'responder name'),
      unit: _stringValue(json['unit'], 'transport unit'),
      tasks: tasksJson.map((item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid nurse preparation task.');
        }
        return NursePrepTask.fromJson(item);
      }).toList(growable: false),
      prepDone: _intValue(json['prep_done'], 'completed task count'),
      prepTotal: _intValue(json['prep_total'], 'total task count'),
      prepComplete: _boolValue(json['prep_complete'], 'preparation status'),
      arrivalConfirmed:
          _boolValue(json['arrival_confirmed'], 'arrival confirmation'),
      patientAge: _optionalInt(json['patient_age']),
      etaMinutes: _optionalInt(json['eta_minutes']),
      bedId: _optionalInt(json['bed_id']),
      bedLabel: _optionalString(json['bed_label']),
      note: _stringValue(json['note'], 'clinical note'),
      responderNotes: _stringValue(json['responder_notes'], 'responder notes'),
      heartRate: _optionalInt(json['heart_rate']),
      spo2: _optionalInt(json['spo2']),
      bloodPressure: _optionalString(json['blood_pressure']),
      reportedAt: _optionalString(json['reported_at']),
      arrivedAt: _optionalString(json['arrived_at']),
      transportType: _stringValue(json['transport_type'], 'transport type'),
    );
  }
}

class NurseBed {
  const NurseBed({
    required this.id,
    required this.type,
    required this.number,
    required this.label,
    required this.status,
    this.incidentId,
    this.patientName,
  });

  final int id;
  final String type;
  final int number;
  final String label;
  final String status;
  final int? incidentId;
  final String? patientName;

  factory NurseBed.fromJson(Map<String, dynamic> json) => NurseBed(
        id: _intValue(json['id'], 'bed id'),
        type: _stringValue(json['type'], 'bed type'),
        number: _intValue(json['number'], 'bed number'),
        label: _stringValue(json['label'], 'bed label'),
        status: _stringValue(json['status'], 'bed status'),
        incidentId: _optionalInt(json['incident_id']),
        patientName: _optionalString(json['patient_name']),
      );
}

class NurseHandoff {
  const NurseHandoff({
    required this.id,
    required this.incidentId,
    required this.patientName,
    required this.caseTitle,
    required this.bedLabel,
    required this.fromResponder,
    required this.pulse,
    required this.spo2,
    required this.bloodPressure,
    required this.notes,
    required this.createdAt,
  });

  final int id;
  final int incidentId;
  final String patientName;
  final String caseTitle;
  final String bedLabel;
  final String fromResponder;
  final String pulse;
  final String spo2;
  final String bloodPressure;
  final String notes;
  final String createdAt;

  factory NurseHandoff.fromJson(Map<String, dynamic> json) => NurseHandoff(
        id: _intValue(json['id'], 'handoff id'),
        incidentId: _intValue(json['incident_id'], 'incident id'),
        patientName: _stringValue(json['patient_name'], 'patient name'),
        caseTitle: _stringValue(json['case'], 'case title'),
        bedLabel: _stringValue(json['bed_label'], 'bed label'),
        fromResponder: _stringValue(json['from_responder'], 'responder name'),
        pulse: _stringValue(json['pulse'], 'pulse'),
        spo2: _stringValue(json['spo2'], 'SpO2'),
        bloodPressure: _stringValue(json['blood_pressure'], 'blood pressure'),
        notes: _stringValue(json['notes'], 'handoff notes'),
        createdAt: _stringValue(json['created_at'], 'handoff date'),
      );
}

class NurseShift {
  const NurseShift({
    required this.id,
    required this.date,
    required this.type,
    required this.start,
    required this.end,
    required this.status,
    required this.unit,
  });

  final int id;
  final String date;
  final String type;
  final String start;
  final String end;
  final String status;
  final String unit;

  factory NurseShift.fromJson(Map<String, dynamic> json) => NurseShift(
        id: _intValue(json['id'], 'shift id'),
        date: _stringValue(json['date'], 'shift date'),
        type: _stringValue(json['type'], 'shift type'),
        start: _stringValue(json['start'], 'shift start'),
        end: _stringValue(json['end'], 'shift end'),
        status: _stringValue(json['status'], 'shift status'),
        unit: _stringValue(json['unit'], 'shift unit'),
      );
}

class NurseDashboardData {
  const NurseDashboardData({
    required this.profile,
    required this.cases,
    required this.summary,
  });

  final NurseProfile profile;
  final List<NurseCaseRecord> cases;
  final Map<String, dynamic> summary;

  factory NurseDashboardData.fromJson(Map<String, dynamic> json) {
    final profile = json['profile'];
    final cases = json['cases'];
    final summary = json['summary'];
    if (profile is! Map<String, dynamic> ||
        cases is! List ||
        summary is! Map<String, dynamic>) {
      throw const FormatException('Invalid nurse dashboard data.');
    }
    return NurseDashboardData(
      profile: NurseProfile.fromJson(profile),
      cases: cases.map((item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid nurse case record.');
        }
        return NurseCaseRecord.fromJson(item);
      }).toList(growable: false),
      summary: summary,
    );
  }
}

class NursePortalCache {
  NursePortalCache._();

  static final Map<String, NurseDashboardData> _dashboards = {};
  static final Map<String, NurseProfile> _profiles = {};
  static final Map<String, List<NurseCaseRecord>> _cases = {};
  static final Map<String, List<NurseBed>> _beds = {};
  static final Map<String, List<NurseHandoff>> _handoffs = {};
  static final Map<String, List<NurseShift>> _schedules = {};

  static NurseDashboardData? dashboard(String token) => _dashboards[token];
  static NurseProfile? profile(String token) => _profiles[token];
  static List<NurseCaseRecord>? cases(String token) => _cases[token];
  static List<NurseBed>? beds(String token) => _beds[token];
  static List<NurseHandoff>? handoffs(String token) => _handoffs[token];
  static List<NurseShift>? schedule(String token, String month) =>
      _schedules['$token:$month'];

  static void saveDashboard(String token, NurseDashboardData data) {
    _dashboards[token] = data;
    _profiles[token] = data.profile;
    _cases[token] = data.cases;
  }

  static void saveProfile(String token, NurseProfile profile) {
    _profiles[token] = profile;
  }

  static void saveCases(String token, List<NurseCaseRecord> cases) {
    _cases[token] = cases;
  }

  static void saveCase(String token, NurseCaseRecord caseRecord) {
    final cases = [...?_cases[token]];
    final index = cases.indexWhere((item) => item.id == caseRecord.id);
    if (index < 0) {
      cases.add(caseRecord);
    } else {
      cases[index] = caseRecord;
    }
    _cases[token] = List.unmodifiable(cases);
  }

  static void saveBeds(String token, List<NurseBed> beds) {
    _beds[token] = beds;
  }

  static void saveHandoffs(String token, List<NurseHandoff> handoffs) {
    _handoffs[token] = handoffs;
  }

  static void saveSchedule(
      String token, String month, List<NurseShift> shifts) {
    _schedules['$token:$month'] = shifts;
  }

  static void clear(String token) {
    _dashboards.remove(token);
    _profiles.remove(token);
    _cases.remove(token);
    _beds.remove(token);
    _handoffs.remove(token);
    _schedules.removeWhere((key, _) => key.startsWith('$token:'));
  }
}

int _intValue(Object? value, String label) {
  if (value is int) return value;
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null) return parsed;
  }
  throw FormatException('Invalid $label.');
}

int? _optionalInt(Object? value) =>
    value == null ? null : _intValue(value, 'number');

bool _boolValue(Object? value, String label) {
  if (value is bool) return value;
  if (value == 0 || value == '0') return false;
  if (value == 1 || value == '1') return true;
  throw FormatException('Invalid $label.');
}

String _stringValue(Object? value, String label) {
  if (value is String) return value;
  if (value == null) return '';
  if (value is num) return value.toString();
  throw FormatException('Invalid $label.');
}

String? _optionalString(Object? value) =>
    value == null ? null : _stringValue(value, 'text value');
