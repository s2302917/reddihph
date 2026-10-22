class FalseReportCase {
  final int id;
  final int incidentId;
  final String reason;
  final String? notes;
  final String incidentType;
  final String location;
  final String? barangayName;
  final String reporterName;
  final String reporterEmail;
  final int warningCount;
  final String? bannedUntil;
  final String createdAt;

  const FalseReportCase({
    required this.id,
    required this.incidentId,
    required this.reason,
    required this.incidentType,
    required this.location,
    required this.reporterName,
    required this.reporterEmail,
    required this.warningCount,
    required this.createdAt,
    this.notes,
    this.barangayName,
    this.bannedUntil,
  });

  factory FalseReportCase.fromJson(Map<String, dynamic> json) =>
      FalseReportCase(
        id: (json['id'] as num).toInt(),
        incidentId: (json['incident_id'] as num).toInt(),
        reason: json['reason'] as String,
        notes: json['notes'] as String?,
        incidentType: json['incident_type'] as String,
        location: json['location'] as String,
        barangayName: json['barangay_name'] as String?,
        reporterName: json['reporter_name'] as String,
        reporterEmail: json['reporter_email'] as String,
        warningCount: (json['warning_count'] as num).toInt(),
        bannedUntil: json['banned_until'] as String?,
        createdAt: json['created_at'] as String,
      );

  String get reasonLabel => switch (reason) {
        'prank_or_test' => 'Prank or test',
        'wrong_location' => 'Wrong location',
        'already_resolved' => 'Already resolved',
        'cannot_find_anyone' => 'Cannot find anyone',
        _ => reason,
      };
}

class FalseReportReviewResult {
  final int warningCount;
  final String? bannedUntil;
  final int? banDays;
  final bool automaticBan;

  const FalseReportReviewResult({
    required this.warningCount,
    required this.automaticBan,
    this.bannedUntil,
    this.banDays,
  });

  factory FalseReportReviewResult.fromJson(Map<String, dynamic> json) =>
      FalseReportReviewResult(
        warningCount: (json['warning_count'] as num).toInt(),
        bannedUntil: json['banned_until'] as String?,
        banDays: (json['ban_days'] as num?)?.toInt(),
        automaticBan: json['automatic_ban'] as bool,
      );
}
