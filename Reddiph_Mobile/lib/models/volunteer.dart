class VolunteerProfile {
  const VolunteerProfile({
    required this.id,
    required this.name,
    required this.vehicleType,
    required this.rating,
    required this.assistedIncidents,
    required this.distanceKm,
    required this.available,
  });

  final int id;
  final String name;
  final String vehicleType;
  final double? rating;
  final int assistedIncidents;
  final double? distanceKm;
  final bool available;

  factory VolunteerProfile.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final name = json['name'];
    final vehicleType = json['vehicle_type'];
    final assistedIncidents = json['assisted_incidents'];
    final available = json['available'];
    if (id is! int ||
        name is! String ||
        vehicleType is! String ||
        assistedIncidents is! int ||
        available is! bool) {
      throw const FormatException('Invalid volunteer profile.');
    }

    return VolunteerProfile(
      id: id,
      name: name,
      vehicleType: vehicleType,
      rating: (json['rating'] as num?)?.toDouble(),
      assistedIncidents: assistedIncidents,
      distanceKm: (json['distance_km'] as num?)?.toDouble(),
      available: available,
    );
  }
}

class ResidentHospitalPreview {
  const ResidentHospitalPreview({
    required this.hospitalId,
    required this.name,
    required this.latitude,
    required this.longitude,
    required this.estimatedRoadDistanceKm,
    required this.etaMinutes,
  });

  final int? hospitalId;
  final String name;
  final double? latitude;
  final double? longitude;
  final double? estimatedRoadDistanceKm;
  final int? etaMinutes;

  factory ResidentHospitalPreview.fromJson(Map<String, dynamic> json) {
    final hospital = json['hospital'];
    if (hospital is! Map<String, dynamic> || hospital['name'] is! String) {
      throw const FormatException('Invalid hospital preview.');
    }

    double? decimal(Object? value) => switch (value) {
          num number => number.toDouble(),
          String text => double.tryParse(text),
          _ => null,
        };

    return ResidentHospitalPreview(
      hospitalId: (hospital['id'] as num?)?.toInt(),
      name: hospital['name'] as String,
      latitude: decimal(hospital['latitude']),
      longitude: decimal(hospital['longitude']),
      estimatedRoadDistanceKm: decimal(json['estimated_road_distance_km']),
      etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
    );
  }
}

class VolunteerAssistanceRequest {
  const VolunteerAssistanceRequest({
    required this.assistanceId,
    required this.incidentId,
    required this.incidentType,
    required this.severity,
    required this.location,
    required this.patientName,
    required this.patientAge,
    required this.notes,
    required this.status,
    required this.createdAt,
    this.latitude,
    this.longitude,
    this.requesterName = 'Resident',
    this.transportName,
    this.hospitalName,
    this.hospitalLatitude,
    this.hospitalLongitude,
    this.responderLatitude,
    this.responderLongitude,
  });

  final int assistanceId;
  final int incidentId;
  final String incidentType;
  final String severity;
  final String location;
  final String patientName;
  final int? patientAge;
  final String notes;
  final String status;
  final String createdAt;
  final double? latitude;
  final double? longitude;
  final String requesterName;
  final String? transportName;
  final String? hospitalName;
  final double? hospitalLatitude;
  final double? hospitalLongitude;
  final double? responderLatitude;
  final double? responderLongitude;

  bool get needsVehicle => transportName == null || transportName!.isEmpty;

  factory VolunteerAssistanceRequest.fromJson(Map<String, dynamic> json) {
    final assistanceId = json['assistance_id'];
    final incidentId = json['incident_id'];
    final incidentType = json['incident_type'];
    final severity = json['severity'];
    final location = json['location'];
    final patientName = json['patient_name'];
    final notes = json['notes'];
    final status = json['status'];
    final createdAt = json['created_at'];
    if (assistanceId is! int ||
        incidentId is! int ||
        incidentType is! String ||
        severity is! String ||
        location is! String ||
        patientName is! String ||
        notes is! String ||
        status is! String ||
        createdAt is! String) {
      throw const FormatException('Invalid volunteer assistance request.');
    }
    return VolunteerAssistanceRequest(
      assistanceId: assistanceId,
      incidentId: incidentId,
      incidentType: incidentType,
      severity: severity,
      location: location,
      patientName: patientName,
      patientAge: (json['patient_age'] as num?)?.toInt(),
      notes: notes,
      status: status,
      createdAt: createdAt,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      requesterName: json['requester_name'] as String? ?? 'Resident',
      transportName: json['transport_name'] as String?,
      hospitalName: json['hospital_name'] as String?,
      hospitalLatitude: (json['hospital_latitude'] as num?)?.toDouble(),
      hospitalLongitude: (json['hospital_longitude'] as num?)?.toDouble(),
      responderLatitude: (json['responder_latitude'] as num?)?.toDouble(),
      responderLongitude: (json['responder_longitude'] as num?)?.toDouble(),
    );
  }
}

class VolunteerHistoryItem {
  const VolunteerHistoryItem({
    required this.assistanceId,
    required this.incidentId,
    required this.completedAt,
    required this.incidentType,
    required this.location,
    required this.patientName,
    this.transportType,
    this.rating,
    this.review,
  });

  final int assistanceId;
  final int incidentId;
  final DateTime completedAt;
  final String incidentType;
  final String location;
  final String patientName;
  final String? transportType;
  final int? rating;
  final String? review;

  factory VolunteerHistoryItem.fromJson(Map<String, dynamic> json) {
    final completedAt = DateTime.tryParse(
      (json['completed_at'] as String? ?? '').replaceFirst(' ', 'T'),
    );
    if (json['assistance_id'] is! int ||
        json['incident_id'] is! int ||
        completedAt == null ||
        json['incident_type'] is! String ||
        json['location'] is! String ||
        json['patient_name'] is! String) {
      throw const FormatException('Invalid volunteer history entry.');
    }

    return VolunteerHistoryItem(
      assistanceId: json['assistance_id'] as int,
      incidentId: json['incident_id'] as int,
      completedAt: completedAt,
      incidentType: json['incident_type'] as String,
      location: json['location'] as String,
      patientName: json['patient_name'] as String,
      transportType: json['transport_type'] as String?,
      rating: (json['rating'] as num?)?.toInt(),
      review: json['review'] as String?,
    );
  }
}

class VolunteerCommunityPerson {
  const VolunteerCommunityPerson({
    required this.id,
    required this.name,
    required this.thanksCount,
    required this.thanksThisMonth,
    required this.assistedIncidents,
    this.vehicleType,
    this.rating,
  });

  final int id;
  final String name;
  final int thanksCount;
  final int thanksThisMonth;
  final int assistedIncidents;
  final String? vehicleType;
  final double? rating;

  factory VolunteerCommunityPerson.fromJson(Map<String, dynamic> json) {
    if (json['id'] is! int || json['name'] is! String) {
      throw const FormatException('Invalid community volunteer.');
    }
    return VolunteerCommunityPerson(
      id: json['id'] as int,
      name: json['name'] as String,
      thanksCount: (json['thanks_count'] as num?)?.toInt() ?? 0,
      thanksThisMonth: (json['thanks_this_month'] as num?)?.toInt() ?? 0,
      assistedIncidents: (json['assisted_incidents'] as num?)?.toInt() ?? 0,
      vehicleType: json['vehicle_type'] as String?,
      rating: (json['rating'] as num?)?.toDouble(),
    );
  }
}

class VolunteerCommunityEntry {
  const VolunteerCommunityEntry({
    required this.id,
    required this.volunteerId,
    required this.volunteerName,
    required this.reviewerName,
    required this.rating,
    required this.review,
    required this.createdAt,
    this.vehicleType,
  });

  final int id;
  final int volunteerId;
  final String volunteerName;
  final String reviewerName;
  final int rating;
  final String review;
  final DateTime createdAt;
  final String? vehicleType;

  factory VolunteerCommunityEntry.fromJson(Map<String, dynamic> json) {
    final createdAt = DateTime.tryParse(
      (json['created_at'] as String? ?? '').replaceFirst(' ', 'T'),
    );
    if (json['id'] is! int ||
        json['volunteer_id'] is! int ||
        json['volunteer_name'] is! String ||
        json['reviewer_name'] is! String ||
        json['rating'] is! num ||
        createdAt == null) {
      throw const FormatException('Invalid community review.');
    }
    return VolunteerCommunityEntry(
      id: json['id'] as int,
      volunteerId: json['volunteer_id'] as int,
      volunteerName: json['volunteer_name'] as String,
      reviewerName: json['reviewer_name'] as String,
      rating: (json['rating'] as num).toInt(),
      review: json['review'] as String? ?? '',
      createdAt: createdAt,
      vehicleType: json['vehicle_type'] as String?,
    );
  }
}

class VolunteerDetails {
  const VolunteerDetails({
    required this.id,
    required this.name,
    required this.assistedIncidents,
    required this.thanksCount,
    required this.thanksThisMonth,
    required this.reviews,
    this.vehicleType,
    this.rating,
  });

  final int id;
  final String name;
  final String? vehicleType;
  final double? rating;
  final int assistedIncidents;
  final int thanksCount;
  final int thanksThisMonth;
  final List<VolunteerProfileReview> reviews;

  factory VolunteerDetails.fromJson(Map<String, dynamic> json) {
    if (json['id'] is! int ||
        json['name'] is! String ||
        json['reviews'] is! List) {
      throw const FormatException('Invalid volunteer details.');
    }

    try {
      return VolunteerDetails(
        id: json['id'] as int,
        name: json['name'] as String,
        vehicleType: json['vehicle_type'] as String?,
        rating: (json['rating'] as num?)?.toDouble(),
        assistedIncidents: (json['assisted_incidents'] as num?)?.toInt() ?? 0,
        thanksCount: (json['thanks_count'] as num?)?.toInt() ?? 0,
        thanksThisMonth:
            (json['thanks_this_month'] as num?)?.toInt() ?? 0,
        reviews: (json['reviews'] as List).map((review) {
          if (review is! Map<String, dynamic>) {
            throw const FormatException('Invalid volunteer review.');
          }
          return VolunteerProfileReview.fromJson(review);
        }).toList(growable: false),
      );
    } on TypeError {
      throw const FormatException('Invalid volunteer details.');
    }
  }
}

class VolunteerProfileReview {
  const VolunteerProfileReview({
    required this.reviewerName,
    required this.rating,
    required this.review,
    required this.createdAt,
  });

  final String reviewerName;
  final int rating;
  final String review;
  final DateTime createdAt;

  factory VolunteerProfileReview.fromJson(Map<String, dynamic> json) {
    final createdAt = DateTime.tryParse(
      (json['created_at'] as String? ?? '').replaceFirst(' ', 'T'),
    );
    if (json['reviewer_name'] is! String ||
        json['rating'] is! num ||
        createdAt == null) {
      throw const FormatException('Invalid volunteer review.');
    }
    return VolunteerProfileReview(
      reviewerName: json['reviewer_name'] as String,
      rating: (json['rating'] as num).toInt(),
      review: json['review'] as String? ?? '',
      createdAt: createdAt,
    );
  }
}
