class ResponderReportPreview {
  final int? hospitalId;
  final String hospitalName;
  final double? hospitalLatitude;
  final double? hospitalLongitude;
  final double? estimatedRoadDistanceKm;
  final int? etaMinutes;

  const ResponderReportPreview({
    required this.hospitalId,
    required this.hospitalName,
    required this.hospitalLatitude,
    required this.hospitalLongitude,
    required this.estimatedRoadDistanceKm,
    required this.etaMinutes,
  });

  factory ResponderReportPreview.fromJson(Map<String, dynamic> json) {
    final hospital = json['hospital'];
    if (hospital is! Map<String, dynamic> ||
        hospital['name'] is! String ||
        hospital['id'] is! num) {
      throw const FormatException('Invalid hospital preview.');
    }

    return ResponderReportPreview(
      hospitalId: (hospital['id'] as num).toInt(),
      hospitalName: hospital['name'] as String,
      hospitalLatitude: (hospital['latitude'] as num?)?.toDouble(),
      hospitalLongitude: (hospital['longitude'] as num?)?.toDouble(),
      estimatedRoadDistanceKm:
          (json['estimated_road_distance_km'] as num?)?.toDouble(),
      etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
    );
  }
}
