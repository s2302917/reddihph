import 'package:image_picker/image_picker.dart';

class ResponderIncidentDraft {
  final String incidentType;
  final String status;
  final String reportedBy;
  final String location;
  final double latitude;
  final double longitude;
  final String? patientName, patientGender;
  final int? patientAge;

  const ResponderIncidentDraft({
    required this.incidentType,
    required this.status,
    required this.reportedBy,
    required this.location,
    required this.latitude,
    required this.longitude,
    this.patientName,
    this.patientAge,
    this.patientGender,
  });
}

class DraftIncidentPatient {
  final String clientId;
  final String name;
  final int age;
  final String gender;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final String? notes;
  final List<String> sceneConditions;
  final XFile? photo;

  const DraftIncidentPatient({
    required this.clientId,
    required this.name,
    required this.age,
    required this.gender,
    this.heartRate,
    this.spo2,
    this.bloodPressure,
    this.notes,
    this.sceneConditions = const [],
    this.photo,
  });
}

class IncidentPatient {
  final int id;
  final String name;
  final int age;
  final String gender;
  final int? heartRate;
  final int? spo2;
  final String? bloodPressure;
  final String? notes;
  final List<String> sceneConditions;
  final bool hasPhoto;

  const IncidentPatient({
    required this.id,
    required this.name,
    required this.age,
    required this.gender,
    this.heartRate,
    this.spo2,
    this.bloodPressure,
    this.notes,
    this.sceneConditions = const [],
    this.hasPhoto = false,
  });

  factory IncidentPatient.fromJson(Map<String, dynamic> json) =>
      IncidentPatient(
        id: (json['id'] as num).toInt(),
        name: json['patient_name'] as String,
        age: (json['patient_age'] as num).toInt(),
        gender: json['patient_gender'] as String,
        heartRate: (json['heart_rate'] as num?)?.toInt(),
        spo2: (json['spo2'] as num?)?.toInt(),
        bloodPressure: json['nurse_blood_pressure'] as String?,
        notes: json['notes'] as String?,
        sceneConditions: (json['scene_conditions'] as List<dynamic>? ?? [])
            .whereType<String>()
            .toList(),
        hasPhoto: json['has_photo'] as bool? ?? false,
      );
}
