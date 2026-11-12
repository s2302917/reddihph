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

class NurseCaseRecord {
  const NurseCaseRecord({
    required this.id,
    required this.title,
    required this.triageCategoryLabel,
    required this.status,
    required this.patientName,
    required this.patientGender,
    required this.location,
    required this.responder,
    required this.unit,
    this.patientAge,
    this.etaMinutes,
    this.note = '',
    this.responderNotes = '',
    this.heartRate,
    this.spo2,
    this.bloodPressure,
    this.reportedAt,
    this.transportType = '',
    this.triageCategory,
    this.unseen = false,
    this.hospitalName = '',
    this.incidentStatus = '',
    this.gcs,
  });

  final int id;
  final String title;
  final String triageCategoryLabel;
  final String status;
  final String patientName;
  final String patientGender;
  final String location;
  final String responder;
  final String unit;
  final int? patientAge;
  final int? etaMinutes;
  final String note;
  final String responderNotes;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final String? reportedAt;
  final String transportType;
  final String? triageCategory;
  final bool unseen;
  final String hospitalName;
  final String incidentStatus;
  final int? gcs;

  factory NurseCaseRecord.fromJson(Map<String, dynamic> json) {
    return NurseCaseRecord(
      id: _intValue(json['id'], 'incident id'),
      title: _stringValue(json['title'], 'case title'),
      triageCategoryLabel: _stringValue(
        json['triage_category_label'],
        'emergency category',
      ),
      status: _stringValue(json['status'], 'case status'),
      patientName: _stringValue(json['patient_name'], 'patient name'),
      patientGender: _stringValue(json['patient_gender'], 'patient gender'),
      location: _stringValue(json['location'], 'incident location'),
      responder: _stringValue(json['responder'], 'responder name'),
      unit: _stringValue(json['unit'], 'transport unit'),
      patientAge: _optionalInt(json['patient_age']),
      etaMinutes: _optionalInt(json['eta_minutes']),
      note: _stringValue(json['note'], 'clinical note'),
      responderNotes: _stringValue(json['responder_notes'], 'responder notes'),
      heartRate: _optionalInt(json['heart_rate']),
      spo2: _optionalInt(json['spo2']),
      bloodPressure: _optionalString(json['blood_pressure']),
      hospitalName: _stringValue(json['hospital'], 'hospital name'),
      incidentStatus: _stringValue(json['incident_status'], 'incident status'),
      gcs: _optionalInt(json['gcs']),
      reportedAt: _optionalString(json['reported_at']),
      transportType: _stringValue(json['transport_type'], 'transport type'),
      triageCategory: _optionalString(json['triage_category']),
      unseen: json['unseen'] == null
          ? false
          : _boolValue(json['unseen'], 'notification status'),
    );
  }
}

class NurseCaseHistoryRecord {
  const NurseCaseHistoryRecord({
    required this.id,
    required this.title,
    required this.status,
    required this.triageCategoryLabel,
    required this.patientName,
    required this.patientGender,
    required this.location,
    required this.reportedAt,
    required this.updatedAt,
    required this.notifiedAt,
    required this.responder,
    required this.unit,
    required this.note,
    required this.responderNotes,
    required this.patientAge,
    required this.heartRate,
    required this.spo2,
    required this.bloodPressure,
  });

  final int id;
  final String title;
  final String status;
  final String triageCategoryLabel;
  final String patientName;
  final String patientGender;
  final String location;
  final String reportedAt;
  final String updatedAt;
  final String notifiedAt;
  final String responder;
  final String unit;
  final String note;
  final String responderNotes;
  final int? patientAge;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;

  factory NurseCaseHistoryRecord.fromJson(Map<String, dynamic> json) {
    return NurseCaseHistoryRecord(
      id: _intValue(json['id'], 'incident id'),
      title: _stringValue(json['title'], 'case title'),
      status: _stringValue(json['status'], 'case status'),
      triageCategoryLabel:
          _stringValue(json['triage_category_label'], 'emergency category'),
      patientName: _stringValue(json['patient_name'], 'patient name'),
      patientGender: _stringValue(json['patient_gender'], 'patient gender'),
      location: _stringValue(json['location'], 'incident location'),
      reportedAt: _stringValue(json['reported_at'], 'reported time'),
      updatedAt: _stringValue(json['updated_at'], 'updated time'),
      notifiedAt: _stringValue(json['notified_at'], 'notification time'),
      responder: _stringValue(json['responder'], 'responder name'),
      unit: _stringValue(json['unit'], 'transport unit'),
      note: _stringValue(json['note'], 'clinical note'),
      responderNotes: _stringValue(json['responder_notes'], 'responder notes'),
      patientAge: _optionalInt(json['patient_age']),
      heartRate: _optionalInt(json['heart_rate']),
      spo2: _optionalInt(json['spo2']),
      bloodPressure: _optionalString(json['blood_pressure']),
    );
  }
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
  static final Map<String, List<NurseCaseHistoryRecord>> _history = {};
  static final Map<String, List<NurseShift>> _schedules = {};

  static NurseDashboardData? dashboard(String token) => _dashboards[token];
  static NurseProfile? profile(String token) => _profiles[token];
  static List<NurseCaseRecord>? cases(String token) => _cases[token];
  static List<NurseCaseHistoryRecord>? history(String token) => _history[token];
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

  static void saveHistory(String token, List<NurseCaseHistoryRecord> history) {
    _history[token] = history;
  }

  static void saveSchedule(
      String token, String month, List<NurseShift> shifts) {
    _schedules['$token:$month'] = shifts;
  }

  static void clear(String token) {
    _dashboards.remove(token);
    _profiles.remove(token);
    _cases.remove(token);
    _history.remove(token);
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
