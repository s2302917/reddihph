import 'package:flutter/material.dart';

enum EmergencyStage {
  idle,
  sending,
  confirmed,
  assigned,
  tracking,
  arrived,
  completed,
}

/// Live details of the reporter's own SOS incident, read from the incidents table.
class IncidentDetails {
  final String type, location;
  final String? transportName, transportType, driverName, responderName;
  final String? volunteerName, volunteerAssistanceStatus;
  final String? barangay, hospital, responderBarangay, responderAlertStatus;
  final String status;
  final int incidentId;
  final bool sentToHospital;
  final DateTime? reportedAt,
      responderAssignedAt,
      responderAcknowledgedAt,
      responderArrivedAt,
      patientPickedUpAt,
      hospitalSentAt,
      hospitalArrivedAt;
  final int? volunteerReviewRating;
  final int? etaMinutes;
  final int? responderUserId;
  final double? distanceKm,
      latitude,
      longitude,
      responderLatitude,
      responderLongitude,
      hospitalLatitude,
      hospitalLongitude;

  const IncidentDetails({
    required this.type,
    required this.location,
    this.incidentId = 0,
    this.status = 'critical',
    this.sentToHospital = false,
    this.reportedAt,
    this.responderAssignedAt,
    this.responderAcknowledgedAt,
    this.responderArrivedAt,
    this.patientPickedUpAt,
    this.hospitalSentAt,
    this.hospitalArrivedAt,
    this.transportName,
    this.transportType,
    this.driverName,
    this.responderName,
    this.responderUserId,
    this.responderBarangay,
    this.responderAlertStatus,
    this.barangay,
    this.hospital,
    this.volunteerName,
    this.volunteerAssistanceStatus,
    this.volunteerReviewRating,
    this.etaMinutes,
    this.distanceKm,
    this.latitude,
    this.longitude,
    this.responderLatitude,
    this.responderLongitude,
    this.hospitalLatitude,
    this.hospitalLongitude,
  });

  factory IncidentDetails.fromJson(Map<String, dynamic> json) =>
      IncidentDetails(
        type: json['incident_type'] as String? ?? 'SOS emergency alert',
        location: json['location'] as String? ?? 'Location unavailable',
        incidentId: json['incident_id'] as int? ?? 0,
        status: json['status'] as String? ?? 'critical',
        sentToHospital:
            json['sent_to_hospital'] == true || json['sent_to_hospital'] == 1,
        reportedAt: DateTime.tryParse(json['reported_at'] as String? ?? ''),
        responderAssignedAt:
            DateTime.tryParse(json['responder_assigned_at'] as String? ?? ''),
        responderAcknowledgedAt: DateTime.tryParse(
            json['responder_acknowledged_at'] as String? ?? ''),
        responderArrivedAt:
            DateTime.tryParse(json['responder_arrived_at'] as String? ?? ''),
        patientPickedUpAt:
            DateTime.tryParse(json['patient_picked_up_at'] as String? ?? ''),
        hospitalSentAt:
            DateTime.tryParse(json['hospital_sent_at'] as String? ?? ''),
        hospitalArrivedAt:
            DateTime.tryParse(json['hospital_arrived_at'] as String? ?? ''),
        transportName: json['transport_name'] as String?,
        transportType: json['transport_type'] as String?,
        driverName: json['driver_name'] as String?,
        responderName: json['responder_name'] as String?,
        responderUserId: (json['responder_user_id'] as num?)?.toInt(),
        responderBarangay: json['responder_barangay'] as String?,
        responderAlertStatus: json['responder_alert_status'] as String?,
        barangay: json['barangay'] as String?,
        hospital: json['hospital'] as String?,
        volunteerName: json['volunteer_name'] as String?,
        volunteerAssistanceStatus:
            json['volunteer_assistance_status'] as String?,
        volunteerReviewRating:
            (json['volunteer_review_rating'] as num?)?.toInt(),
        etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
        distanceKm: (json['distance_km'] as num?)?.toDouble(),
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
        responderLatitude: (json['responder_latitude'] as num?)?.toDouble(),
        responderLongitude: (json['responder_longitude'] as num?)?.toDouble(),
        hospitalLatitude: (json['hospital_latitude'] as num?)?.toDouble(),
        hospitalLongitude: (json['hospital_longitude'] as num?)?.toDouble(),
      );

  /// "Ambulance · RB-04", or a placeholder until a vehicle is assigned.
  String get vehicleLabel {
    final parts = [transportType, transportName].whereType<String>();
    return parts.isEmpty ? 'Vehicle not assigned yet' : parts.join(' · ');
  }

  /// "2.4 km away · ETA 6 min", leaving out whatever is not known yet.
  String get distanceLabel {
    final parts = [
      if (distanceKm != null) '${distanceKm!.toStringAsFixed(1)} km away',
      if (etaMinutes != null) 'ETA $etaMinutes min',
    ];
    return parts.isEmpty ? 'Distance unavailable' : parts.join(' · ');
  }
}

class ResponderAlert {
  const ResponderAlert({
    required this.alertId,
    required this.incidentId,
    required this.incidentType,
    required this.location,
    this.barangay,
    this.etaMinutes,
  });

  final int alertId;
  final int incidentId;
  final String incidentType;
  final String location;
  final String? barangay;
  final int? etaMinutes;

  factory ResponderAlert.fromJson(Map<String, dynamic> json) {
    final alertId = (json['alert_id'] as num?)?.toInt();
    final incidentId = (json['incident_id'] as num?)?.toInt();
    final incidentType = json['incident_type'];
    final location = json['location'];
    if (alertId == null ||
        incidentId == null ||
        incidentType is! String ||
        location is! String) {
      throw const FormatException('Invalid responder alert.');
    }
    return ResponderAlert(
      alertId: alertId,
      incidentId: incidentId,
      incidentType: incidentType,
      location: location,
      barangay: json['barangay'] as String?,
      etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
    );
  }
}

class Profile {
  final String title, subtitle, initial;
  const Profile(this.title, this.subtitle, this.initial);
}

class ResidentReport {
  final String title, status, ambulance;
  final int step;
  const ResidentReport(this.title, this.status, this.step, this.ambulance);
}

class QueueItem {
  final String title, location, distance, ago;
  final String severity, patient, reportedBy, vehicle;
  final String? responderName;
  final int? patientAge;
  final String? patientGender;
  final int? incidentId;
  final bool canFlagFalseReport;
  const QueueItem(this.title, this.location, this.distance, this.ago,
      {this.severity = 'Stable',
      this.patient = 'Unknown',
      this.patientAge,
      this.patientGender,
      this.reportedBy = 'Resident SOS',
      this.vehicle = 'Ambulance RB-04',
      this.responderName,
      this.incidentId,
      this.canFlagFalseReport = false});
  bool get critical => severity == 'Critical';

  /// "Brgy. San Isidro · Fidel R." -> "Brgy. San Isidro"
  String get area => location.split(' · ').first;
}

class PatientCase {
  final String title, location, eta, severity;
  final String? pulse, spo2, bp;
  const PatientCase(this.title, this.location, this.eta, this.severity,
      {this.pulse, this.spo2, this.bp});
  bool get critical => severity == 'Critical';
}

class ReviewCase {
  final String title, detail;
  const ReviewCase(this.title, this.detail);
}

class ResourceStat {
  final String label, value, sub;
  final Color color;
  const ResourceStat(this.label, this.value, this.sub, this.color);
}

class ChecklistItem {
  final String label;
  bool done;
  ChecklistItem(this.label, this.done);
}

class AssistRequest {
  final String title, detail, action;
  const AssistRequest(this.title, this.detail, this.action);
}

class UserFeedback {
  final String rating, text;
  const UserFeedback(this.rating, this.text);
}
