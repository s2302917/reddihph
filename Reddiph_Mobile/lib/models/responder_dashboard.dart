import 'package:latlong2/latlong.dart';

import 'emergency.dart';

bool shouldShowUpcomingResponderCases({
  required bool sentToHospital,
  required int? etaMinutes,
}) =>
    sentToHospital && etaMinutes != null && etaMinutes <= 5;

/// First two letters of a full name, for the avatar.
String initialsOf(String fullName) {
  final letters = fullName.replaceAll(RegExp(r'\s'), '');
  return letters
      .substring(0, letters.length < 2 ? letters.length : 2)
      .toUpperCase();
}

/// An incident as the responder home page shows it, read from the incidents table.
class ResponderIncident {
  final int id;
  final String type, status, location;
  final String reportedBy;
  final bool hasReporterAccount;
  final bool sentToHospital;
  final String? responderName;
  final String? patientName, patientGender, transportName, transportType;
  final int? patientAge;
  final LatLng? point;
  final int? etaMinutes;
  final int minutesAgo;
  final double? distanceKm;

  const ResponderIncident({
    required this.id,
    required this.type,
    required this.status,
    required this.location,
    required this.minutesAgo,
    this.reportedBy = 'resident_sos',
    this.hasReporterAccount = false,
    this.sentToHospital = false,
    this.responderName,
    this.patientName,
    this.patientAge,
    this.patientGender,
    this.transportName,
    this.transportType,
    this.point,
    this.etaMinutes,
    this.distanceKm,
  });

  factory ResponderIncident.fromJson(Map<String, dynamic> json) {
    final latitude = (json['latitude'] as num?)?.toDouble();
    final longitude = (json['longitude'] as num?)?.toDouble();
    return ResponderIncident(
      id: (json['incident_id'] as num).toInt(),
      type: json['incident_type'] as String? ?? 'SOS emergency alert',
      status: json['status'] as String? ?? 'critical',
      reportedBy: json['reported_by'] as String? ?? 'resident_sos',
      hasReporterAccount: json['has_reporter_account'] as bool? ?? false,
      sentToHospital: json['sent_to_hospital'] as bool? ?? false,
      responderName: json['responder_name'] as String?,
      // The barangay name is the readable place; the raw location is the fallback.
      location: json['barangay'] as String? ??
          json['location'] as String? ??
          'Location unavailable',
      patientName: json['patient_name'] as String?,
      patientAge: (json['patient_age'] as num?)?.toInt(),
      patientGender: json['patient_gender'] as String?,
      transportName: json['transport_name'] as String?,
      transportType: json['transport_type'] as String?,
      point: latitude == null || longitude == null
          ? null
          : LatLng(latitude, longitude),
      etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
      minutesAgo: (json['minutes_ago'] as num?)?.toInt() ?? 0,
      distanceKm: (json['distance_km'] as num?)?.toDouble(),
    );
  }

  String get severity => switch (status) {
        'critical' => 'Critical',
        'urgent' => 'Urgent',
        'dispatched' => 'Dispatched',
        _ => 'Stable',
      };

  String get distanceLabel =>
      distanceKm == null ? '—' : '${distanceKm!.toStringAsFixed(1)} km';

  String get agoLabel {
    if (minutesAgo < 1) return 'Just now';
    if (minutesAgo < 60) return '$minutesAgo min ago';
    if (minutesAgo < 1440) return '${minutesAgo ~/ 60} h ago';
    return '${minutesAgo ~/ 1440} d ago';
  }

  String get vehicleLabel {
    final parts = [transportType, transportName].whereType<String>();
    return parts.isEmpty ? 'Unassigned' : parts.join(' ');
  }

  /// The shape the case detail screen already understands.
  QueueItem toQueueItem() => QueueItem(
        type,
        location,
        distanceLabel,
        agoLabel,
        severity: severity,
        patient: patientName ?? 'Unknown',
        vehicle: vehicleLabel,
        incidentId: id,
        canFlagFalseReport: reportedBy == 'resident_sos' && hasReporterAccount,
        patientAge: patientAge,
        patientGender: patientGender,
        responderName: responderName,
      );
}

/// One page of the incoming SOS list.
class IncidentPage {
  final List<ResponderIncident> items;
  final int page, pages, total;

  const IncidentPage({
    this.items = const [],
    this.page = 1,
    this.pages = 1,
    this.total = 0,
  });

  factory IncidentPage.fromJson(Map<String, dynamic> json) => IncidentPage(
        items: [
          for (final item in json['items'] as List<dynamic>)
            ResponderIncident.fromJson(item as Map<String, dynamic>),
        ],
        page: (json['page'] as num).toInt(),
        pages: (json['pages'] as num).toInt(),
        total: (json['total'] as num).toInt(),
      );

  bool get hasPrevious => page > 1;
  bool get hasNext => page < pages;
}

/// Everything the responder home page needs from the database.
class ResponderDashboard {
  final String fullName;
  final String? vehicleType, callSign, plateNumber;
  final ResponderIncident? activeCase;
  final IncidentPage queue;

  const ResponderDashboard({
    required this.fullName,
    required this.queue,
    this.vehicleType,
    this.callSign,
    this.plateNumber,
    this.activeCase,
  });

  factory ResponderDashboard.fromJson(Map<String, dynamic> json) {
    final responder = json['responder'] as Map<String, dynamic>;
    final vehicle = responder['vehicle'] as Map<String, dynamic>?;
    final active = json['active_case'] as Map<String, dynamic>?;
    return ResponderDashboard(
      fullName: responder['full_name'] as String,
      vehicleType: vehicle?['vehicle_type'] as String?,
      callSign: vehicle?['call_sign'] as String?,
      plateNumber: vehicle?['plate_number'] as String?,
      activeCase: active == null ? null : ResponderIncident.fromJson(active),
      queue: IncidentPage.fromJson(json['queue'] as Map<String, dynamic>),
    );
  }

  /// First two letters of the full name, for the avatar.
  String get initials {
    final letters = fullName.replaceAll(RegExp(r'\s'), '');
    return letters
        .substring(0, letters.length < 2 ? letters.length : 2)
        .toUpperCase();
  }

  /// "Responder", or "Responder - Ambulance RB-04 · ABC 123" for a vehicle's driver.
  String get roleLabel {
    final vehicle = [vehicleType, callSign].whereType<String>().join(' ');
    if (vehicle.isEmpty) return 'Responder';
    final plate = plateNumber == null ? '' : ' · $plateNumber';
    return 'Responder - $vehicle$plate';
  }
}

/// One page of the SOS queue tab with the number of incidents behind each filter.
class ResponderQueue {
  final String fullName;
  final Map<String, int> counts;
  final IncidentPage page;

  const ResponderQueue({
    required this.fullName,
    required this.counts,
    required this.page,
  });

  factory ResponderQueue.fromJson(Map<String, dynamic> json) => ResponderQueue(
        fullName:
            (json['responder'] as Map<String, dynamic>)['full_name'] as String,
        counts: (json['counts'] as Map<String, dynamic>)
            .map((key, value) => MapEntry(key, (value as num).toInt())),
        page: IncidentPage.fromJson(json['queue'] as Map<String, dynamic>),
      );

  String get initials => initialsOf(fullName);
}
