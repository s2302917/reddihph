import 'package:latlong2/latlong.dart';

import 'emergency.dart';
import 'triage_category.dart';

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
  final String? triageCategory;
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
    this.triageCategory,
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
      id: (json['incident_id'] as num?)?.toInt() ?? 0,
      type: json['incident_type'] as String? ?? 'SOS emergency alert',
      status: json['status'] as String? ?? 'open',
      triageCategory: json['triage_category'] as String?,
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

  String get severity =>
      EmergencyCategory.fromValue(triageCategory)?.label ?? 'Unclassified';

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

  factory IncidentPage.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'];
    final items = <ResponderIncident>[];
    if (itemsJson is List) {
      for (final item in itemsJson) {
        if (item is Map<String, dynamic>) {
          items.add(ResponderIncident.fromJson(item));
        }
      }
    }
    return IncidentPage(
      items: items,
      page: (json['page'] as num?)?.toInt() ?? 1,
      pages: (json['pages'] as num?)?.toInt() ?? 1,
      total: (json['total'] as num?)?.toInt() ?? items.length,
    );
  }

  bool get hasPrevious => page > 1;
  bool get hasNext => page < pages;
}

/// Everything the responder home page needs from the database.
class ResponderDashboard {
  final String fullName;
  final String? vehicleType, callSign, plateNumber;
  final int monthlyDispatchCount;
  final int hospitalArrivalsThisMonth;
  final ResponderIncident? activeCase;
  final IncidentPage queue;

  const ResponderDashboard({
    required this.fullName,
    required this.queue,
    this.vehicleType,
    this.callSign,
    this.plateNumber,
    this.monthlyDispatchCount = 0,
    this.hospitalArrivalsThisMonth = 0,
    this.activeCase,
  });

  factory ResponderDashboard.fromJson(Map<String, dynamic> json) {
    final responderRaw = json['responder'];
    final responder = responderRaw is Map<String, dynamic>
        ? responderRaw
        : <String, dynamic>{};
    final vehicleRaw = responder['vehicle'];
    final vehicle = vehicleRaw is Map<String, dynamic> ? vehicleRaw : null;
    final activeRaw = json['active_case'];
    final active = activeRaw is Map<String, dynamic> ? activeRaw : null;
    final queueRaw = json['queue'];
    final queue = queueRaw is Map<String, dynamic>
        ? IncidentPage.fromJson(queueRaw)
        : const IncidentPage();

    return ResponderDashboard(
      fullName: responder['full_name'] as String? ?? 'Responder',
      vehicleType: vehicle?['vehicle_type'] as String?,
      callSign: vehicle?['call_sign'] as String?,
      plateNumber: vehicle?['plate_number'] as String?,
      monthlyDispatchCount: ((responder['monthly_dispatch_count'] ??
                  responder['dispatches_this_month'] ??
                  json['monthly_dispatch_count'] ??
                  json['dispatches_this_month']) as num?)
              ?.toInt() ??
          0,
      hospitalArrivalsThisMonth: ((responder['hospital_arrivals_this_month'] ??
                  json['hospital_arrivals_this_month']) as num?)
              ?.toInt() ??
          0,
      activeCase: active == null ? null : ResponderIncident.fromJson(active),
      queue: queue,
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

  factory ResponderQueue.fromJson(Map<String, dynamic> json) {
    final responderRaw = json['responder'];
    final responder = responderRaw is Map<String, dynamic>
        ? responderRaw
        : <String, dynamic>{};
    final countsJson = json['counts'];
    final counts = <String, int>{};
    if (countsJson is Map) {
      for (final entry in countsJson.entries) {
        final value = entry.value;
        if (value is num) {
          counts[entry.key.toString()] = value.toInt();
        }
      }
    }
    final queueRaw = json['queue'];
    final queue = queueRaw is Map<String, dynamic>
        ? IncidentPage.fromJson(queueRaw)
        : const IncidentPage();

    return ResponderQueue(
      fullName: responder['full_name'] as String? ?? 'Responder',
      counts: counts,
      page: queue,
    );
  }

  String get initials => initialsOf(fullName);
}

class ResponderDutyPeriod {
  const ResponderDutyPeriod({
    required this.dayOfWeek,
    required this.startTime,
    required this.endTime,
  });

  final int dayOfWeek;
  final String startTime;
  final String endTime;

  Map<String, Object> toJson() => {
        'day_of_week': dayOfWeek,
        'start_time': startTime,
        'end_time': endTime,
      };

  factory ResponderDutyPeriod.fromJson(Map<String, dynamic> json) =>
      ResponderDutyPeriod(
        dayOfWeek: (json['day_of_week'] as num).toInt(),
        startTime: json['start_time'] as String,
        endTime: json['end_time'] as String,
      );
}

class ResponderProfileSettings {
  const ResponderProfileSettings({
    required this.fullName,
    required this.driverResponderEnabled,
    required this.scheduleConfigured,
    required this.availabilityEnabled,
    required this.isOnDuty,
    required this.schedules,
    required this.monthlyDispatchCount,
    required this.hospitalArrivalsThisMonth,
    this.profileImageUrl,
    this.vehicleType,
    this.callSign,
    this.plateNumber,
  });

  final String fullName;
  final String? profileImageUrl;
  final bool driverResponderEnabled;
  final String? vehicleType;
  final String? callSign;
  final String? plateNumber;
  final bool scheduleConfigured;
  final bool availabilityEnabled;
  final bool isOnDuty;
  final List<ResponderDutyPeriod> schedules;
  final int monthlyDispatchCount;
  final int hospitalArrivalsThisMonth;

  factory ResponderProfileSettings.fromJson(Map<String, dynamic> json) {
    final rawProfile = json['profile'];
    if (rawProfile is! Map<String, dynamic>) {
      throw const FormatException('Invalid responder profile.');
    }
    final rawVehicle = rawProfile['vehicle'];
    final vehicle = rawVehicle is Map<String, dynamic> ? rawVehicle : null;
    final rawSchedules = rawProfile['schedules'];
    final schedules = rawSchedules is List
        ? rawSchedules
            .whereType<Map<String, dynamic>>()
            .map(ResponderDutyPeriod.fromJson)
            .toList(growable: false)
        : const <ResponderDutyPeriod>[];

    bool readBool(String key) =>
        rawProfile[key] == true ||
        rawProfile[key] == 1 ||
        rawProfile[key] == '1';

    return ResponderProfileSettings(
      fullName: rawProfile['full_name'] as String? ?? 'Responder',
      profileImageUrl: (rawProfile['profile_image_url'] ??
          rawProfile['profile_image']) as String?,
      driverResponderEnabled: readBool('driver_responder_enabled'),
      vehicleType: vehicle?['vehicle_type'] as String?,
      callSign: vehicle?['call_sign'] as String?,
      plateNumber: vehicle?['plate_number'] as String?,
      scheduleConfigured: readBool('schedule_configured'),
      availabilityEnabled: readBool('availability_enabled'),
      isOnDuty: readBool('is_on_duty'),
      schedules: schedules,
      monthlyDispatchCount:
          (json['monthly_dispatch_count'] as num?)?.toInt() ?? 0,
      hospitalArrivalsThisMonth:
          (json['hospital_arrivals_this_month'] as num?)?.toInt() ?? 0,
    );
  }
}
