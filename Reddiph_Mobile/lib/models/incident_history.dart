import 'emergency.dart';

class IncidentHistoryEntry {
  const IncidentHistoryEntry({
    required this.incident,
    required this.reportedBy,
    this.patientName,
    this.patientAge,
    this.patientGender,
    this.resolvedAt,
    this.falseReportId,
    this.falseReportReason,
    this.falseReportNotes,
  });

  final IncidentDetails incident;
  final String reportedBy;
  final String? patientName;
  final int? patientAge;
  final String? patientGender;
  final DateTime? resolvedAt;
  final int? falseReportId;
  final String? falseReportReason;
  final String? falseReportNotes;

  bool get isClosed => incident.status == 'resolved';
  bool get wasFalseReport => falseReportId != null;
  bool get arrivedAtHospital => incident.hospitalArrivedAt != null;

  String get closureLabel {
    if (wasFalseReport) return 'False report';
    if (arrivedAtHospital) return 'Arrived at the hospital';
    return 'Closed';
  }

  String get patientLabel {
    final name = patientName?.trim();
    if (name == null || name.isEmpty) return 'Patient details unavailable';
    return name;
  }

  factory IncidentHistoryEntry.fromJson(Map<String, dynamic> json) {
    final incident = IncidentDetails.fromJson(json);
    return IncidentHistoryEntry(
      incident: incident,
      reportedBy: json['reported_by'] as String? ?? 'resident_sos',
      patientName: json['patient_name'] as String?,
      patientAge: (json['patient_age'] as num?)?.toInt(),
      patientGender: json['patient_gender'] as String?,
      resolvedAt: DateTime.tryParse(json['resolved_at'] as String? ?? ''),
      falseReportId: (json['false_report_id'] as num?)?.toInt(),
      falseReportReason: json['false_report_reason'] as String?,
      falseReportNotes: json['false_report_notes'] as String?,
    );
  }
}
