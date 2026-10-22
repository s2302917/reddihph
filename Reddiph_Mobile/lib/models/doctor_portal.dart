class DoctorProfile {
  const DoctorProfile({
    required this.id,
    required this.fullName,
    required this.hospitalId,
    required this.hospitalName,
    required this.available,
    this.profileImage,
    this.hospitalLatitude,
    this.hospitalLongitude,
  });

  final int id;
  final String fullName;
  final int? hospitalId;
  final String hospitalName;
  final bool available;
  final String? profileImage;
  final double? hospitalLatitude;
  final double? hospitalLongitude;

  String get greetingName {
    final parts = fullName.trim().split(RegExp(r'\s+'));
    return parts.isEmpty || parts.last.isEmpty ? fullName : parts.last;
  }

  String get initial =>
      greetingName.isEmpty ? '?' : greetingName[0].toUpperCase();

  factory DoctorProfile.fromJson(Map<String, dynamic> json) {
    if (json['id'] is! num ||
        json['full_name'] is! String ||
        json['hospital_name'] is! String ||
        json['is_available'] is! bool) {
      throw const FormatException('Invalid doctor profile.');
    }
    return DoctorProfile(
      id: (json['id'] as num).toInt(),
      fullName: json['full_name'] as String,
      hospitalId: (json['hospital_id'] as num?)?.toInt(),
      hospitalName: json['hospital_name'] as String,
      available: json['is_available'] as bool,
      profileImage: json['profile_image'] as String?,
      hospitalLatitude: _decimal(json['hospital_latitude']),
      hospitalLongitude: _decimal(json['hospital_longitude']),
    );
  }
}

class DoctorTimelineEntry {
  const DoctorTimelineEntry({
    required this.title,
    required this.detail,
    required this.occurredAt,
    required this.timeLabel,
  });

  final String title;
  final String detail;
  final DateTime occurredAt;
  final String timeLabel;

  factory DoctorTimelineEntry.fromJson(Map<String, dynamic> json) {
    final occurredAt = _date(json['occurred_at']);
    if (json['title'] is! String ||
        json['detail'] is! String ||
        occurredAt == null ||
        json['time_label'] is! String) {
      throw const FormatException('Invalid doctor case timeline.');
    }
    return DoctorTimelineEntry(
      title: json['title'] as String,
      detail: json['detail'] as String,
      occurredAt: occurredAt,
      timeLabel: json['time_label'] as String,
    );
  }
}

class DoctorPatient {
  const DoctorPatient({
    required this.name,
    required this.age,
    required this.gender,
    required this.heartRate,
    required this.spo2,
    required this.bloodPressure,
    required this.notes,
  });

  final String name;
  final int age;
  final String gender;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final String notes;

  factory DoctorPatient.fromJson(Map<String, dynamic> json) {
    if (json['name'] is! String ||
        json['age'] is! num ||
        json['gender'] is! String ||
        json['notes'] is! String) {
      throw const FormatException('Invalid doctor patient data.');
    }
    return DoctorPatient(
      name: json['name'] as String,
      age: (json['age'] as num).toInt(),
      gender: json['gender'] as String,
      heartRate: (json['heart_rate'] as num?)?.toInt(),
      spo2: (json['spo2'] as num?)?.toInt(),
      bloodPressure: json['blood_pressure'] as String?,
      notes: json['notes'] as String,
    );
  }
}

class DoctorCaseRecord {
  const DoctorCaseRecord({
    required this.id,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.location,
    required this.patient,
    required this.patientCount,
    required this.patientGender,
    required this.responder,
    required this.hospital,
    required this.reportedAt,
    required this.notes,
    required this.patients,
    required this.timeline,
    required this.clinicalNote,
    this.latitude,
    this.longitude,
    this.patientAge,
    this.etaMinutes,
    this.heartRate,
    this.spo2,
    this.bloodPressure,
    this.resolvedAt,
    this.doctorArrivedAt,
  });

  final int id;
  final String title;
  final String status;
  final String statusLabel;
  final String location;
  final String patient;
  final int patientCount;
  final int? patientAge;
  final String patientGender;
  final int? etaMinutes;
  final String responder;
  final String hospital;
  final DateTime reportedAt;
  final DateTime? resolvedAt;
  final String notes;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final DateTime? doctorArrivedAt;
  final double? latitude;
  final double? longitude;
  final String clinicalNote;
  final List<DoctorPatient> patients;
  final List<DoctorTimelineEntry> timeline;

  bool get isIncoming =>
      status == 'critical' || status == 'urgent' || status == 'dispatched';

  bool get isInEr => status == 'stable';

  String get summary => [
        title,
        if (patientAge != null) '$patientAge',
        patientGender == 'unknown' ? '' : patientGender,
      ].where((part) => part.isNotEmpty).join(' · ');

  String get etaLabel =>
      etaMinutes == null ? 'ETA unavailable' : 'ETA $etaMinutes min';

  factory DoctorCaseRecord.fromJson(Map<String, dynamic> json) {
    final reportedAt = _date(json['reported_at']);
    if (json['id'] is! num ||
        json['title'] is! String ||
        json['status'] is! String ||
        json['status_label'] is! String ||
        json['location'] is! String ||
        json['patient'] is! String ||
        json['patient_count'] is! num ||
        json['patient_gender'] is! String ||
        json['responder'] is! String ||
        json['hospital'] is! String ||
        reportedAt == null ||
        json['notes'] is! String) {
      throw const FormatException('Invalid doctor case.');
    }
    final patientsJson = json['patients'];
    final timelineJson = json['timeline'];
    return DoctorCaseRecord(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      status: json['status'] as String,
      statusLabel: json['status_label'] as String,
      location: json['location'] as String,
      patient: json['patient'] as String,
      patientCount: (json['patient_count'] as num).toInt(),
      patientAge: (json['patient_age'] as num?)?.toInt(),
      patientGender: json['patient_gender'] as String,
      etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
      responder: json['responder'] as String,
      hospital: json['hospital'] as String,
      reportedAt: reportedAt,
      resolvedAt: _date(json['resolved_at']),
      notes: json['notes'] as String,
      heartRate: (json['heart_rate'] as num?)?.toInt(),
      spo2: (json['spo2'] as num?)?.toInt(),
      bloodPressure: json['blood_pressure'] as String?,
      doctorArrivedAt: _date(json['doctor_arrived_at']),
      latitude: _decimal(json['latitude']),
      longitude: _decimal(json['longitude']),
      clinicalNote: json['clinical_note'] as String? ?? '',
      patients: patientsJson is List
          ? patientsJson.map((patient) {
              if (patient is! Map<String, dynamic>) {
                throw const FormatException('Invalid doctor patient data.');
              }
              return DoctorPatient.fromJson(patient);
            }).toList(growable: false)
          : const [],
      timeline: timelineJson is List
          ? timelineJson.map((entry) {
              if (entry is! Map<String, dynamic>) {
                throw const FormatException('Invalid doctor case timeline.');
              }
              return DoctorTimelineEntry.fromJson(entry);
            }).toList(growable: false)
          : const [],
    );
  }
}

class DoctorHistoryRecord {
  const DoctorHistoryRecord({
    required this.id,
    required this.title,
    required this.status,
    required this.patient,
    required this.reportedAt,
    required this.reportedLabel,
    this.resolvedAt,
    this.confirmedAt,
  });

  final int id;
  final String title;
  final String status;
  final String patient;
  final DateTime reportedAt;
  final String reportedLabel;
  final DateTime? resolvedAt;
  final DateTime? confirmedAt;

  factory DoctorHistoryRecord.fromJson(Map<String, dynamic> json) {
    final reportedAt = _date(json['reported_at']);
    if (json['id'] is! num ||
        json['title'] is! String ||
        json['status'] is! String ||
        json['patient'] is! String ||
        reportedAt == null ||
        json['reported_label'] is! String) {
      throw const FormatException('Invalid doctor history record.');
    }
    return DoctorHistoryRecord(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      status: json['status'] as String,
      patient: json['patient'] as String,
      reportedAt: reportedAt,
      reportedLabel: json['reported_label'] as String,
      resolvedAt: _date(json['resolved_at']),
      confirmedAt: _date(json['confirmed_at']),
    );
  }
}

class DoctorHistoryData {
  const DoctorHistoryData({
    required this.cases,
    required this.total,
    required this.critical,
    required this.averageMinutes,
    required this.weeklyCounts,
  });

  final List<DoctorHistoryRecord> cases;
  final int total;
  final int critical;
  final int? averageMinutes;
  final List<int> weeklyCounts;

  factory DoctorHistoryData.fromJson(Map<String, dynamic> json) {
    final casesJson = json['cases'];
    final stats = json['stats'];
    if (casesJson is! List ||
        stats is! Map<String, dynamic> ||
        stats['total'] is! num ||
        stats['critical'] is! num ||
        stats['weekly_counts'] is! List) {
      throw const FormatException('Invalid doctor case history.');
    }
    final weekly = (stats['weekly_counts'] as List).map((count) {
      if (count is! num) {
        throw const FormatException('Invalid weekly case summary.');
      }
      return count.toInt();
    }).toList(growable: false);
    if (weekly.length != 7) {
      throw const FormatException('Invalid weekly case summary.');
    }
    return DoctorHistoryData(
      cases: casesJson.map((item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid doctor history record.');
        }
        return DoctorHistoryRecord.fromJson(item);
      }).toList(growable: false),
      total: (stats['total'] as num).toInt(),
      critical: (stats['critical'] as num).toInt(),
      averageMinutes: (stats['average_minutes'] as num?)?.toInt(),
      weeklyCounts: weekly,
    );
  }
}

class DoctorScheduleEntry {
  const DoctorScheduleEntry({
    required this.id,
    required this.date,
    required this.type,
    required this.start,
    required this.end,
    required this.status,
  });

  final int id;
  final DateTime date;
  final String type;
  final String start;
  final String end;
  final String status;

  factory DoctorScheduleEntry.fromJson(Map<String, dynamic> json) {
    final date = _date(json['date']);
    if (json['id'] is! num ||
        date == null ||
        json['type'] is! String ||
        json['start'] is! String ||
        json['end'] is! String ||
        json['status'] is! String) {
      throw const FormatException('Invalid doctor schedule entry.');
    }
    return DoctorScheduleEntry(
      id: (json['id'] as num).toInt(),
      date: date,
      type: json['type'] as String,
      start: json['start'] as String,
      end: json['end'] as String,
      status: json['status'] as String,
    );
  }

  String get typeLabel => switch (type) {
        'morning' => 'Day shift · ER',
        'evening' => 'Night shift · ER',
        'oncall' => 'On-call',
        'leave' => 'Off duty',
        _ => type,
      };
}

DateTime? _date(Object? value) {
  if (value is! String || value.isEmpty) return null;
  return DateTime.tryParse(value.replaceFirst(' ', 'T'));
}

double? _decimal(Object? value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}
