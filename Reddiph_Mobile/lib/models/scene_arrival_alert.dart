class SceneArrivalAlert {
  const SceneArrivalAlert({
    required this.id,
    required this.incidentId,
    required this.incidentType,
    required this.location,
    required this.responderName,
    required this.message,
    required this.createdAt,
  });

  final int id;
  final int incidentId;
  final String incidentType;
  final String location;
  final String responderName;
  final String message;
  final String createdAt;

  factory SceneArrivalAlert.fromJson(Map<String, dynamic> json) {
    if (json['id'] is! num ||
        json['incident_id'] is! num ||
        json['incident_type'] is! String ||
        json['location'] is! String ||
        json['responder_name'] is! String ||
        json['message'] is! String ||
        json['created_at'] is! String) {
      throw const FormatException('Invalid scene arrival alert.');
    }

    return SceneArrivalAlert(
      id: (json['id'] as num).toInt(),
      incidentId: (json['incident_id'] as num).toInt(),
      incidentType: json['incident_type'] as String,
      location: json['location'] as String,
      responderName: json['responder_name'] as String,
      message: json['message'] as String,
      createdAt: json['created_at'] as String,
    );
  }
}
