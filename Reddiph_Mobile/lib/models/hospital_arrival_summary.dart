class HospitalArrivalSummary {
  const HospitalArrivalSummary({
    required this.hospitalName,
    required this.dispatchedAt,
    required this.patientPickedUpAt,
    required this.arrivedAt,
    this.volunteerName,
    this.volunteerVehicleType,
    this.volunteerAssistanceStatus,
    this.volunteerReviewRating,
  });

  final String hospitalName;
  final DateTime dispatchedAt;
  final DateTime? patientPickedUpAt;
  final DateTime arrivedAt;
  final String? volunteerName;
  final String? volunteerVehicleType;
  final String? volunteerAssistanceStatus;
  final int? volunteerReviewRating;

  bool get hasVolunteer => volunteerName?.trim().isNotEmpty == true;

  bool get canThankVolunteer =>
      hasVolunteer &&
      volunteerAssistanceStatus == 'completed' &&
      volunteerReviewRating == null;

  int get responseTimeMinutes {
    final responseAt = patientPickedUpAt ?? arrivedAt;
    final minutes = responseAt.difference(dispatchedAt).inMinutes;
    return minutes < 0 ? 0 : minutes;
  }

  factory HospitalArrivalSummary.fromJson(Map<String, dynamic> json) {
    final name = json['hospital_name'];
    final dispatched = json['dispatched_at'];
    final pickedUp = json['patient_picked_up_at'];
    final arrived = json['arrived_at'];
    final volunteerName = json['volunteer_name'];
    final volunteerVehicleType = json['volunteer_vehicle_type'];
    final volunteerStatus = json['volunteer_assistance_status'];
    final volunteerRating = json['volunteer_review_rating'];
    if (name is! String ||
        dispatched is! String ||
        (pickedUp != null && pickedUp is! String) ||
        arrived is! String ||
        (volunteerName != null && volunteerName is! String) ||
        (volunteerVehicleType != null && volunteerVehicleType is! String) ||
        (volunteerStatus != null && volunteerStatus is! String) ||
        (volunteerRating != null && volunteerRating is! int)) {
      throw const FormatException('Invalid hospital arrival summary.');
    }

    return HospitalArrivalSummary(
      hospitalName: name,
      dispatchedAt: DateTime.parse(dispatched),
      patientPickedUpAt:
          pickedUp == null ? null : DateTime.parse(pickedUp as String),
      arrivedAt: DateTime.parse(arrived),
      volunteerName: volunteerName as String?,
      volunteerVehicleType: volunteerVehicleType as String?,
      volunteerAssistanceStatus: volunteerStatus as String?,
      volunteerReviewRating: volunteerRating as int?,
    );
  }
}
