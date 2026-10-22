import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../models/driving_route.dart';
import '../models/doctor_portal.dart';
import '../models/emergency.dart';
import '../models/false_report.dart';
import '../models/incident_patient.dart';
import '../models/incident_history.dart';
import '../models/nurse_portal.dart';
import '../models/responder_report_preview.dart';
import '../models/scene_arrival_alert.dart';
import '../models/responder_dashboard.dart';
import '../models/volunteer.dart';

class MobileApiException implements Exception {
  final String message;

  const MobileApiException(this.message);

  @override
  String toString() => message;
}

class MobileBarangay {
  const MobileBarangay({required this.id, required this.name});

  final int id;
  final String name;
}

class MobileHospital {
  const MobileHospital({
    required this.id,
    required this.name,
    required this.barangays,
  });

  final int id;
  final String name;
  final List<MobileBarangay> barangays;
}

/// Choices for the signup forms, all read from the database.
class SignupOptions {
  const SignupOptions({required this.hospitals, required this.genders});

  final List<MobileHospital> hospitals;
  final List<String> genders;
}

class MobileApiClient {
  MobileApiClient({
    http.Client? client,
    String? baseUrl,
  })  : _client = client ?? http.Client(),
        _baseUrl = baseUrl ??
            const String.fromEnvironment(
              'REDDIPH_API_BASE_URL',
              defaultValue: 'https://reddiph.com/Reddiph_mobile_api/',
            );

  static final MobileApiClient I = MobileApiClient();
  static void Function()? onSessionInvalid;

  final http.Client _client;
  final String _baseUrl;

  Future<Map<String, dynamic>> _doctorPortal({
    required String token,
    required String action,
    Map<String, dynamic> fields = const {},
  }) =>
      _send(
        'POST',
        'api/doctors/portal.php',
        body: {'token': token, 'action': action, ...fields},
      );

  Future<Map<String, dynamic>> _nursePortal({
    required String token,
    required String action,
    Map<String, Object?> fields = const {},
  }) =>
      _send(
        'POST',
        'api/nurses/portal.php',
        body: {'token': token, 'action': action, ...fields},
      );

  Future<NurseDashboardData> nurseDashboard({required String token}) async {
    final response = await _nursePortal(token: token, action: 'dashboard');
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'Nurse dashboard could not be loaded.',
      );
    }
    try {
      return NurseDashboardData.fromJson(response);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid nurse dashboard data.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid nurse dashboard data.',
      );
    }
  }

  Future<NurseProfile> nurseProfile({required String token}) async {
    final response = await _nursePortal(token: token, action: 'settings');
    final profile = response['profile'];
    if (response['success'] != true || profile is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ?? 'Nurse settings could not be loaded.',
      );
    }
    try {
      return NurseProfile.fromJson(profile);
    } on FormatException {
      throw const MobileApiException(
          'The server returned invalid nurse settings.');
    } on TypeError {
      throw const MobileApiException(
          'The server returned invalid nurse settings.');
    }
  }

  Future<List<NurseCaseRecord>> nurseCases({required String token}) async {
    final response = await _nursePortal(token: token, action: 'cases');
    return _nurseRecords<NurseCaseRecord>(
      response,
      'cases',
      NurseCaseRecord.fromJson,
      'nurse cases',
    );
  }

  Future<NurseCaseRecord> nurseCaseDetails({
    required String token,
    required int incidentId,
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'case_detail',
      fields: {'incident_id': incidentId},
    );
    final item = response['case'];
    if (response['success'] != true || item is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'Nurse case details could not be loaded.',
      );
    }
    try {
      return NurseCaseRecord.fromJson(item);
    } on FormatException {
      throw const MobileApiException(
          'The server returned invalid nurse case details.');
    } on TypeError {
      throw const MobileApiException(
          'The server returned invalid nurse case details.');
    }
  }

  Future<NurseCaseRecord> toggleNursePrepTask({
    required String token,
    required int incidentId,
    required int taskId,
    required bool done,
  }) =>
      _nurseCaseAction(token, 'toggle_task', {
        'incident_id': incidentId,
        'task_id': taskId,
        'done': done,
      });

  Future<NurseCaseRecord> saveNurseNote({
    required String token,
    required int incidentId,
    required String note,
  }) =>
      _nurseCaseAction(token, 'save_note', {
        'incident_id': incidentId,
        'note': note,
      });

  Future<NurseCaseRecord> assignNurseBed({
    required String token,
    required int incidentId,
    required int bedId,
  }) =>
      _nurseCaseAction(token, 'assign_bed', {
        'incident_id': incidentId,
        'bed_id': bedId,
      });

  Future<NurseCaseRecord> confirmNurseArrival({
    required String token,
    required int incidentId,
  }) =>
      _nurseCaseAction(token, 'confirm_arrival', {'incident_id': incidentId});

  Future<NurseCaseRecord> _nurseCaseAction(
    String token,
    String action,
    Map<String, Object?> fields,
  ) async {
    final response = await _nursePortal(
      token: token,
      action: action,
      fields: fields,
    );
    final item = response['case'];
    if (response['success'] != true || item is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'The nurse case could not be updated.',
      );
    }
    try {
      return NurseCaseRecord.fromJson(item);
    } on FormatException {
      throw const MobileApiException(
          'The server returned invalid nurse case data.');
    } on TypeError {
      throw const MobileApiException(
          'The server returned invalid nurse case data.');
    }
  }

  Future<List<NurseBed>> nurseBeds({required String token}) async {
    final response = await _nursePortal(token: token, action: 'beds');
    return _nurseRecords<NurseBed>(
      response,
      'beds',
      NurseBed.fromJson,
      'nurse bed inventory',
    );
  }

  Future<List<NurseBed>> nurseBedAction({
    required String token,
    required int bedId,
    required String action,
    int? incidentId,
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'bed_action',
      fields: {
        'bed_id': bedId,
        'bed_action': action,
        if (incidentId != null) 'incident_id': incidentId,
      },
    );
    return _nurseRecords<NurseBed>(
      response,
      'beds',
      NurseBed.fromJson,
      'nurse bed inventory',
    );
  }

  Future<List<NurseHandoff>> nurseHandoffs({required String token}) async {
    final response = await _nursePortal(token: token, action: 'handoffs');
    return _nurseRecords<NurseHandoff>(
      response,
      'handoffs',
      NurseHandoff.fromJson,
      'nurse handoffs',
    );
  }

  Future<List<NurseHandoff>> createNurseHandoff({
    required String token,
    required int incidentId,
    required String pulse,
    required String spo2,
    required String bloodPressure,
    required String notes,
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'create_handoff',
      fields: {
        'incident_id': incidentId,
        'pulse': pulse,
        'spo2': spo2,
        'blood_pressure': bloodPressure,
        'notes': notes,
      },
    );
    return _nurseRecords<NurseHandoff>(
      response,
      'handoffs',
      NurseHandoff.fromJson,
      'nurse handoffs',
    );
  }

  Future<List<NurseShift>> nurseSchedule({
    required String token,
    required String month,
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'schedule',
      fields: {'month': month},
    );
    return _nurseRecords<NurseShift>(
      response,
      'shifts',
      NurseShift.fromJson,
      'nurse schedule',
    );
  }

  Future<void> requestNurseShiftSwap({
    required String token,
    required String date,
    int? shiftId,
    String message = '',
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'request_swap',
      fields: {
        'date': date,
        if (shiftId != null) 'shift_id': shiftId,
        'message': message,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'Shift change request failed.',
      );
    }
  }

  Future<NurseProfile> updateNurseSettings({
    required String token,
    required bool isAvailable,
    required bool notificationsEnabled,
    required String language,
  }) async {
    final response = await _nursePortal(
      token: token,
      action: 'update_settings',
      fields: {
        'is_available': isAvailable,
        'notifications_enabled': notificationsEnabled,
        'language': language,
      },
    );
    final profile = response['profile'];
    if (response['success'] != true || profile is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ?? 'Nurse settings could not be saved.',
      );
    }
    try {
      return NurseProfile.fromJson(profile);
    } on FormatException {
      throw const MobileApiException(
          'The server returned invalid nurse settings.');
    } on TypeError {
      throw const MobileApiException(
          'The server returned invalid nurse settings.');
    }
  }

  List<T> _nurseRecords<T>(
    Map<String, dynamic> response,
    String key,
    T Function(Map<String, dynamic>) parse,
    String label,
  ) {
    final records = response[key];
    if (response['success'] != true || records is! List) {
      throw MobileApiException(
        response['message'] as String? ?? 'The server returned invalid $label.',
      );
    }
    try {
      return records.map((record) {
        if (record is! Map<String, dynamic>) {
          throw const FormatException('Invalid nurse record.');
        }
        return parse(record);
      }).toList(growable: false);
    } on FormatException {
      throw MobileApiException('The server returned invalid $label.');
    } on TypeError {
      throw MobileApiException('The server returned invalid $label.');
    }
  }

  Future<
      ({
        DoctorProfile profile,
        List<DoctorCaseRecord> cases,
        List<DoctorCaseRecord> reviewCases,
      })> doctorDashboard({required String token}) async {
    final response = await _doctorPortal(token: token, action: 'dashboard');
    if (response['success'] != true ||
        response['profile'] is! Map<String, dynamic> ||
        response['cases'] is! List ||
        response['review_cases'] is! List) {
      throw const MobileApiException(
        'The server returned invalid doctor dashboard data.',
      );
    }
    try {
      List<DoctorCaseRecord> records(Object? value) =>
          (value as List).map((item) {
            if (item is! Map<String, dynamic>) {
              throw const FormatException('Invalid doctor case.');
            }
            return DoctorCaseRecord.fromJson(item);
          }).toList(growable: false);
      return (
        profile: DoctorProfile.fromJson(
          response['profile'] as Map<String, dynamic>,
        ),
        cases: records(response['cases']),
        reviewCases: records(response['review_cases']),
      );
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid doctor dashboard data.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid doctor dashboard data.',
      );
    }
  }

  Future<List<DoctorCaseRecord>> doctorCases({
    required String token,
    String filter = 'all',
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'cases',
      fields: {'filter': filter},
    );
    final cases = response['cases'];
    if (response['success'] != true || cases is! List) {
      throw const MobileApiException(
          'The server returned invalid doctor cases.');
    }
    try {
      return cases.map((item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid doctor case.');
        }
        return DoctorCaseRecord.fromJson(item);
      }).toList(growable: false);
    } on FormatException {
      throw const MobileApiException(
          'The server returned invalid doctor cases.');
    } on TypeError {
      throw const MobileApiException(
          'The server returned invalid doctor cases.');
    }
  }

  Future<DoctorCaseRecord> doctorCaseDetails({
    required String token,
    required int incidentId,
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'case_detail',
      fields: {'incident_id': incidentId},
    );
    final item = response['case'];
    if (response['success'] != true || item is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'The server returned invalid doctor case details.',
      );
    }
    try {
      return DoctorCaseRecord.fromJson(item);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid doctor case details.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid doctor case details.',
      );
    }
  }

  Future<DoctorCaseRecord> saveDoctorCaseReview({
    required String token,
    required int incidentId,
    required String clinicalNote,
    bool confirmArrival = false,
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'save_review',
      fields: {
        'incident_id': incidentId,
        'clinical_note': clinicalNote.trim(),
        'confirm_arrival': confirmArrival,
      },
    );
    final item = response['case'];
    if (response['success'] != true || item is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'The clinical note could not be saved.',
      );
    }
    try {
      return DoctorCaseRecord.fromJson(item);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid doctor case details.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid doctor case details.',
      );
    }
  }

  Future<DoctorHistoryData> doctorHistory({
    required String token,
    String period = 'week',
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'history',
      fields: {'period': period},
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'The server returned invalid doctor case history.',
      );
    }
    try {
      return DoctorHistoryData.fromJson(response);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid doctor case history.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid doctor case history.',
      );
    }
  }

  Future<List<DoctorScheduleEntry>> doctorSchedule({
    required String token,
    required String month,
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'schedule',
      fields: {'month': month},
    );
    final shifts = response['shifts'];
    if (response['success'] != true || shifts is! List) {
      throw const MobileApiException(
        'The server returned invalid doctor schedule data.',
      );
    }
    try {
      return shifts.map((shift) {
        if (shift is! Map<String, dynamic>) {
          throw const FormatException('Invalid doctor schedule entry.');
        }
        return DoctorScheduleEntry.fromJson(shift);
      }).toList(growable: false);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid doctor schedule data.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid doctor schedule data.',
      );
    }
  }

  Future<void> saveDoctorSchedule({
    required String token,
    int? scheduleId,
    required DateTime date,
    required String type,
    required String start,
    required String end,
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'save_schedule',
      fields: {
        if (scheduleId != null) 'schedule_id': scheduleId,
        'date': '${date.year.toString().padLeft(4, '0')}-'
            '${date.month.toString().padLeft(2, '0')}-'
            '${date.day.toString().padLeft(2, '0')}',
        'type': type,
        'start': start,
        'end': end,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'The schedule could not be saved.',
      );
    }
  }

  Future<void> deleteDoctorSchedule({
    required String token,
    required int scheduleId,
  }) async {
    final response = await _doctorPortal(
      token: token,
      action: 'delete_schedule',
      fields: {'schedule_id': scheduleId},
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'The schedule could not be removed.',
      );
    }
  }

  /// Loads hospitals, their barangays and the gender choices from the database.
  Future<SignupOptions> signupOptions() async {
    final response = await _send('GET', 'api/auth/signup_options.php');
    final hospitalsJson = response['hospitals'];
    final gendersJson = response['genders'];
    if (response['success'] != true ||
        hospitalsJson is! List ||
        gendersJson is! List) {
      throw const MobileApiException(
        'The server returned invalid hospital and barangay data.',
      );
    }

    final hospitals = hospitalsJson.map((hospitalJson) {
      if (hospitalJson is! Map<String, dynamic> ||
          hospitalJson['id'] is! int ||
          hospitalJson['name'] is! String ||
          hospitalJson['barangays'] is! List) {
        throw const MobileApiException(
          'The server returned invalid hospital and barangay data.',
        );
      }

      final barangays = (hospitalJson['barangays'] as List).map((barangayJson) {
        if (barangayJson is! Map<String, dynamic> ||
            barangayJson['id'] is! int ||
            barangayJson['name'] is! String) {
          throw const MobileApiException(
            'The server returned invalid hospital and barangay data.',
          );
        }
        return MobileBarangay(
          id: barangayJson['id'] as int,
          name: barangayJson['name'] as String,
        );
      }).toList();

      return MobileHospital(
        id: hospitalJson['id'] as int,
        name: hospitalJson['name'] as String,
        barangays: barangays,
      );
    }).toList();

    return SignupOptions(
      hospitals: hospitals,
      genders: gendersJson.whereType<String>().toList(),
    );
  }

  /// Creates an account for any role and returns the new user id.
  /// [licenseNumber] is only sent for doctors.
  Future<int> createAccount({
    required String role,
    required String fullName,
    required String workEmail,
    required String phoneNumber,
    required int age,
    required String gender,
    required int hospitalId,
    required int barangayId,
    required String password,
    required String confirmPassword,
    String? licenseNumber,
    String? vehicleType,
    String? signupCode,
  }) async {
    final response = await _send(
      'POST',
      'api/auth/signup.php',
      body: {
        'role': role,
        'full_name': fullName.trim(),
        'work_email': workEmail.trim(),
        'phone_number': phoneNumber.trim(),
        'age': age,
        'gender': gender,
        'hospital_id': hospitalId,
        'barangay_id': barangayId,
        'password': password,
        'confirm_password': confirmPassword,
        if (licenseNumber != null) 'license_number': licenseNumber.trim(),
        if (vehicleType != null) 'vehicle_type': vehicleType.trim(),
        if (signupCode != null) 'signup_code': signupCode.trim(),
      },
    );
    final userId = response['user_id'];
    if (response['success'] != true || userId is! int || userId < 1) {
      throw const MobileApiException(
        'The server returned an invalid signup response.',
      );
    }
    return userId;
  }

  Future<Map<String, dynamic>> barangayAdminPortal({
    required String token,
    required String action,
    int? page,
    String? search,
    int? incidentId,
    int? accountId,
    String? decision,
    String? currentStatus,
    String? status,
    String? period,
    List<String>? includes,
  }) =>
      _send(
        'POST',
        'api/admin/barangay_portal.php',
        body: {
          'token': token,
          'action': action,
          if (page != null) 'page': page,
          if (search != null) 'search': search,
          if (incidentId != null) 'incident_id': incidentId,
          if (accountId != null) 'account_id': accountId,
          if (decision != null) 'decision': decision,
          if (currentStatus != null) 'current_status': currentStatus,
          if (status != null) 'status': status,
          if (period != null) 'period': period,
          if (includes != null) 'includes': includes,
        },
      );

  /// Signs any role in by email and returns the profile with its signed token.
  Future<Map<String, dynamic>> login({
    required String role,
    required String email,
    required String password,
  }) async {
    final response = await _send(
      'POST',
      'api/auth/login.php',
      body: {
        'role': role,
        'email': email.trim(),
        'password': password,
      },
    );
    final token = response['token'];
    if (response['success'] != true ||
        response['user'] is! Map<String, dynamic> ||
        token is! String) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }

    // The signed token rides along with the user so later requests can prove who they are.
    return {...(response['user'] as Map<String, dynamic>), 'token': token};
  }

  Future<void> validateSession({required String token}) async {
    final response = await _send(
      'POST',
      'api/auth/session.php',
      body: {'token': token},
    );
    if (response['success'] != true) {
      throw const MobileApiException('Unable to verify your session.');
    }
  }

  /// Asks the server to email a temporary password. Returns the server's generic message.
  Future<String> forgotPassword({
    required String role,
    required String email,
  }) async {
    final response = await _send(
      'POST',
      'api/auth/forgot_password.php',
      body: {'role': role, 'email': email.trim()},
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return response['message'] as String? ??
        'If that email is registered, a temporary password has been sent.';
  }

  /// Replaces the signed-in user's password (used to swap out a temporary one).
  Future<void> changePassword({
    required String token,
    required String password,
    required String confirmPassword,
  }) async {
    final response = await _send(
      'POST',
      'api/auth/change_password.php',
      body: {
        'token': token,
        'password': password,
        'confirm_password': confirmPassword,
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
  }

  /// Sends an SOS incident ("SOS emergency alert") and returns its incident id.
  /// The server reads the reporter's name, age and gender from the database
  /// using the signed [token]; without a token the SOS is anonymous.
  Future<int> sendSos({
    required double latitude,
    required double longitude,
    String? token,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/sos.php',
      body: {
        'latitude': latitude,
        'longitude': longitude,
        if (token != null) 'token': token,
      },
    );
    final incidentId = response['incident_id'];
    if (response['success'] != true || incidentId is! int) {
      throw const MobileApiException(
        'The server returned an invalid SOS response.',
      );
    }
    return incidentId;
  }

  Future<ResidentHospitalPreview> previewResidentReport({
    required String token,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/sos.php?action=preview',
      body: {
        'token': token,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid hospital preview.',
      );
    }
    try {
      return ResidentHospitalPreview.fromJson(response);
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid hospital preview.',
      );
    }
  }

  Future<List<VolunteerProfile>> volunteersForIncident({
    required String token,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/roster.php',
      body: {
        'token': token,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    final roster = response['volunteers'];
    if (response['success'] != true || roster is! List) {
      throw const MobileApiException(
        'The server returned an invalid volunteer roster.',
      );
    }
    try {
      return roster.map((entry) {
        if (entry is! Map<String, dynamic>) {
          throw const FormatException('Invalid volunteer profile.');
        }
        return VolunteerProfile.fromJson(entry);
      }).toList();
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid volunteer roster.',
      );
    }
  }

  Future<int> submitResidentReport({
    required String token,
    required double latitude,
    required double longitude,
    required String incidentType,
    required String status,
    required String patientName,
    required int patientAge,
    required String patientGender,
    required String notes,
    int? volunteerUserId,
    int? heartRate,
    int? spo2,
    String? bloodPressure,
    List<String> sceneConditions = const [],
    XFile? photo,
    List<DraftIncidentPatient>? patients,
    bool requestAnotherVehicle = false,
    String reportedBy = 'resident_sos',
  }) async {
    final reportPatients = patients ??
        [
          DraftIncidentPatient(
            clientId: 'resident-report',
            name: patientName,
            age: patientAge,
            gender: patientGender,
            heartRate: heartRate,
            spo2: spo2,
            bloodPressure: bloodPressure,
            notes: notes,
            sceneConditions: sceneConditions,
            photo: photo,
          ),
        ];
    final fields = <String, String>{
      'token': token,
      'latitude': latitude.toString(),
      'longitude': longitude.toString(),
      'incident_type': incidentType.trim(),
      'status': status,
      'reported_by': reportedBy,
      'request_another_vehicle': requestAnotherVehicle ? '1' : '0',
      if (volunteerUserId != null)
        'volunteer_user_id': volunteerUserId.toString(),
      'patients': jsonEncode([
        for (final patient in reportPatients)
          {
            'patient_name': patient.name,
            'patient_age': patient.age,
            'patient_gender': patient.gender,
            'heart_rate': patient.heartRate,
            'spo2': patient.spo2,
            'blood_pressure': patient.bloodPressure,
            'notes': patient.notes,
            'scene_conditions': patient.sceneConditions,
          },
      ]),
    };
    final response = await _sendMultipart(
      'api/incidents/sos.php',
      fields: fields,
      photos: [
        for (var index = 0; index < reportPatients.length; index++)
          if (reportPatients[index].photo != null)
            (index, reportPatients[index].photo!),
      ],
    );
    final incidentId = response['incident_id'];
    if (response['success'] != true || incidentId is! int || incidentId < 1) {
      throw const MobileApiException(
        'The server returned an invalid incident response.',
      );
    }
    return incidentId;
  }

  Future<void> updateVolunteerLocation({
    required String token,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/location.php',
      body: {
        'token': token,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid location response.',
      );
    }
  }

  Future<List<VolunteerAssistanceRequest>> volunteerRequests({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/requests.php',
      body: {'token': token},
    );
    final requests = response['requests'];
    if (response['success'] != true || requests is! List) {
      throw const MobileApiException(
        'The server returned invalid volunteer requests.',
      );
    }
    try {
      return requests.map((entry) {
        if (entry is! Map<String, dynamic>) {
          throw const FormatException('Invalid volunteer request.');
        }
        return VolunteerAssistanceRequest.fromJson(entry);
      }).toList();
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid volunteer requests.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid volunteer requests.',
      );
    }
  }

  Future<Map<String, dynamic>> volunteerDashboard({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/dashboard.php',
      body: {'token': token},
    );
    final profile = response['profile'];
    if (response['success'] != true || profile is! Map<String, dynamic>) {
      throw const MobileApiException(
        'The server returned invalid volunteer dashboard data.',
      );
    }
    return profile;
  }

  Future<List<VolunteerHistoryItem>> volunteerHistory({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/history.php',
      body: {'token': token},
    );
    final items = response['items'];
    if (response['success'] != true || items is! List) {
      throw const MobileApiException(
        'The server returned invalid volunteer history.',
      );
    }
    try {
      return items.map((item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid volunteer history item.');
        }
        return VolunteerHistoryItem.fromJson(item);
      }).toList(growable: false);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid volunteer history.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid volunteer history.',
      );
    }
  }

  Future<Map<String, dynamic>> volunteerCommunity({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/community.php',
      body: {'token': token},
    );
    if (response['success'] != true ||
        response['people'] is! List ||
        response['entries'] is! List ||
        response['profile'] is! Map<String, dynamic>) {
      throw const MobileApiException(
        'The server returned invalid volunteer community data.',
      );
    }
    try {
      return {
        'thanks_this_month':
            (response['thanks_this_month'] as num?)?.toInt() ?? 0,
        'community_rating': (response['community_rating'] as num?)?.toDouble(),
        'profile': response['profile'] as Map<String, dynamic>,
        'people': (response['people'] as List).map((person) {
          if (person is! Map<String, dynamic>) {
            throw const FormatException('Invalid community volunteer.');
          }
          return VolunteerCommunityPerson.fromJson(person);
        }).toList(growable: false),
        'entries': (response['entries'] as List).map((entry) {
          if (entry is! Map<String, dynamic>) {
            throw const FormatException('Invalid community entry.');
          }
          return VolunteerCommunityEntry.fromJson(entry);
        }).toList(growable: false),
      };
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid volunteer community data.',
      );
    } on TypeError {
      throw const MobileApiException(
        'The server returned invalid volunteer community data.',
      );
    }
  }

  Future<VolunteerDetails> volunteerDetails({
    required String token,
    required int volunteerId,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/details.php',
      body: {'token': token, 'volunteer_id': volunteerId},
    );
    final profile = response['profile'];
    if (response['success'] != true || profile is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'The server returned invalid volunteer details.',
      );
    }
    try {
      return VolunteerDetails.fromJson(profile);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid volunteer details.',
      );
    }
  }

  Future<Map<String, dynamic>> volunteerAssistanceRoute({
    required String token,
    required int assistanceId,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/assistance_route.php',
      body: {
        'token': token,
        'assistance_id': assistanceId,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    final incident = response['incident'];
    if (response['success'] != true ||
        incident is! Map<String, dynamic> ||
        response['route'] is! Map<String, dynamic>) {
      throw MobileApiException(
        response['message'] as String? ??
            'The server returned invalid assistance route data.',
      );
    }
    try {
      return {
        'route': DrivingRoute.fromJson(response),
        'incident': incident,
      };
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid assistance route data.',
      );
    }
  }

  Future<void> respondToVolunteerRequest({
    required String token,
    required int assistanceId,
    required String action,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/request_action.php',
      body: {
        'token': token,
        'assistance_id': assistanceId,
        'action': action,
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid request response.',
      );
    }
  }

  Future<void> rateVolunteer({
    required String token,
    required int incidentId,
    required int rating,
    required String review,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/rate.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'rating': rating,
        'review': review.trim(),
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid volunteer rating response.',
      );
    }
  }

  Future<void> setVolunteerAvailability({
    required String token,
    required bool available,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/availability.php',
      body: {'token': token, 'available': available},
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid availability response.',
      );
    }
  }

  Future<String?> updateVolunteerVehicle({
    required String token,
    required String vehicleType,
  }) async {
    final response = await _send(
      'POST',
      'api/volunteers/profile.php',
      body: {'token': token, 'vehicle_type': vehicleType.trim()},
    );
    if (response['success'] != true ||
        (response['vehicle_type'] != null &&
            response['vehicle_type'] is! String)) {
      throw const MobileApiException(
        'The server returned an invalid vehicle profile response.',
      );
    }
    return response['vehicle_type'] as String?;
  }

  Future<void> submitFalseReport({
    required String token,
    required int incidentId,
    required String reason,
    String? notes,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/false_report.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'reason': reason,
        'notes': notes?.trim() ?? '',
      },
    );
    if (response['success'] != true || response['false_report_id'] is! int) {
      throw const MobileApiException(
        'The server returned an invalid false-report response.',
      );
    }
  }

  Future<List<FalseReportCase>> barangayFalseReports({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/admin/false_reports.php?action=queue',
      body: {'token': token},
    );
    final reports = response['reports'];
    if (response['success'] != true || reports is! List) {
      throw const MobileApiException(
        'The server returned an invalid false-report queue.',
      );
    }
    final cases = <FalseReportCase>[];
    for (final report in reports) {
      if (report is! Map<String, dynamic>) {
        throw const MobileApiException(
          'The server returned an invalid false-report queue.',
        );
      }
      cases.add(FalseReportCase.fromJson(report));
    }
    return cases;
  }

  Future<FalseReportReviewResult> reviewFalseReport({
    required String token,
    required int falseReportId,
    required String decision,
    bool banFirstWarning = false,
    int? banDays,
  }) async {
    final response = await _send(
      'POST',
      'api/admin/false_reports.php?action=review',
      body: {
        'token': token,
        'false_report_id': falseReportId,
        'decision': decision,
        'ban_first_warning': banFirstWarning,
        if (banDays != null) 'ban_days': banDays,
      },
    );
    if (response['success'] != true || response['warning_count'] is! num) {
      throw const MobileApiException(
        'The server returned an invalid review response.',
      );
    }
    return FalseReportReviewResult.fromJson(response);
  }

  /// Loads the live details of an SOS incident for the person who sent it.
  Future<IncidentDetails> incidentDetails({
    required int incidentId,
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/status.php',
      body: {
        'incident_id': incidentId,
        'token': token,
      },
    );
    final incident = response['incident'];
    if (response['success'] != true || incident is! Map<String, dynamic>) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return IncidentDetails.fromJson(incident);
  }

  /// Loads the signed-in user's active SOS, if one exists.
  Future<IncidentDetails?> activeIncident({required String token}) async {
    final response = await _send(
      'POST',
      'api/incidents/active.php',
      body: {'token': token},
    );
    final incident = response['incident'];
    if (response['success'] != true ||
        (incident != null && incident is! Map<String, dynamic>)) {
      throw const MobileApiException(
        'The server returned an invalid active incident response.',
      );
    }
    return incident == null ? null : IncidentDetails.fromJson(incident);
  }

  /// Loads the signed-in resident's complete report history.
  Future<List<IncidentHistoryEntry>> incidentHistory({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/history.php',
      body: {'token': token},
    );
    final incidents = response['incidents'];
    if (response['success'] != true || incidents is! List) {
      throw const MobileApiException(
        'The server returned an invalid report history response.',
      );
    }
    return incidents.map((incident) {
      if (incident is! Map<String, dynamic>) {
        throw const MobileApiException(
          'The server returned an invalid report history item.',
        );
      }
      return IncidentHistoryEntry.fromJson(incident);
    }).toList(growable: false);
  }

  Future<List<ResponderAlert>> responderAlerts({
    required String token,
    double? latitude,
    double? longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/responder_alerts.php',
      body: {
        'token': token,
        'action': 'queue',
        if (latitude != null && longitude != null) ...{
          'latitude': latitude,
          'longitude': longitude,
        },
      },
    );
    final alerts = response['alerts'];
    if (response['success'] != true || alerts is! List) {
      throw const MobileApiException(
        'The server returned an invalid responder alert list.',
      );
    }
    try {
      return alerts.map((alert) {
        if (alert is! Map<String, dynamic>) {
          throw const FormatException('Invalid responder alert.');
        }
        return ResponderAlert.fromJson(alert);
      }).toList();
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid responder alert.',
      );
    }
  }

  Future<void> acknowledgeResponderAlert({
    required String token,
    required int alertId,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/responder_alerts.php',
      body: {'token': token, 'action': 'acknowledge', 'alert_id': alertId},
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'Unable to stop this alert.',
      );
    }
  }

  Future<void> alertAssignedResponder({
    required String token,
    required int incidentId,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/responder_alerts.php',
      body: {
        'token': token,
        'action': 'alert',
        'incident_id': incidentId,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'Unable to alert the responder.',
      );
    }
  }

  /// Loads the responder home page: profile, active case and one page of the SOS list.
  Future<ResponderDashboard> responderDashboard({
    required String token,
    int page = 1,
    double? latitude,
    double? longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/dashboard.php',
      body: {
        'token': token,
        'page': page,
        if (latitude != null && longitude != null) ...{
          'latitude': latitude,
          'longitude': longitude,
        },
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return ResponderDashboard.fromJson(response);
  }

  Future<void> updateResponderPresence({
    required String token,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/dashboard.php',
      body: {
        'token': token,
        'page': 1,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'Unable to update your responder location.',
      );
    }
  }

  /// Loads one page of the SOS queue for a status filter ('all', 'critical', ...).
  Future<ResponderQueue> responderQueue({
    required String token,
    required String status,
    int page = 1,
    double? latitude,
    double? longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/queue.php',
      body: {
        'token': token,
        'status': status,
        'page': page,
        if (latitude != null && longitude != null) ...{
          'latitude': latitude,
          'longitude': longitude,
        },
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return ResponderQueue.fromJson(response);
  }

  /// Open incidents of the responder's barangay and dispatch list, for the Map tab.
  Future<List<ResponderIncident>> responderMap({
    required String token,
    double? latitude,
    double? longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/map.php',
      body: {
        'token': token,
        if (latitude != null && longitude != null) ...{
          'latitude': latitude,
          'longitude': longitude,
        },
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return [
      for (final item in response['incidents'] as List<dynamic>)
        ResponderIncident.fromJson(item as Map<String, dynamic>),
    ];
  }

  /// Saves an incident filed by a responder from the Incoming page.
  Future<ResponderReportPreview> previewResponderReport({
    required String token,
    required double latitude,
    required double longitude,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/report.php?action=preview',
      body: {
        'token': token,
        'latitude': latitude,
        'longitude': longitude,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'The hospital assignment could not be loaded.',
      );
    }

    try {
      return ResponderReportPreview.fromJson(response);
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid hospital assignment.',
      );
    }
  }

  /// Saves an incident filed by a responder from the Incoming page.
  Future<int> createResponderReport({
    required String token,
    required String incidentType,
    required String status,
    required String reportedBy,
    required String location,
    required double latitude,
    required double longitude,
    required List<DraftIncidentPatient> patients,
    required bool requestAnotherVehicle,
    int? volunteerUserId,
  }) async {
    final fields = <String, String>{
      'token': token,
      'incident_type': incidentType,
      'status': status,
      'reported_by': reportedBy,
      'location': location,
      'latitude': latitude.toString(),
      'longitude': longitude.toString(),
      'request_another_vehicle': requestAnotherVehicle ? '1' : '0',
      if (volunteerUserId != null)
        'volunteer_user_id': volunteerUserId.toString(),
      'patients': jsonEncode([
        for (final patient in patients)
          {
            'patient_name': patient.name,
            'patient_age': patient.age,
            'patient_gender': patient.gender,
            'heart_rate': patient.heartRate,
            'spo2': patient.spo2,
            'blood_pressure': patient.bloodPressure,
            'notes': patient.notes,
            'scene_conditions': patient.sceneConditions,
          },
      ]),
    };
    final response = await _sendMultipart(
      'api/responder/report.php',
      fields: fields,
      photos: [
        for (var i = 0; i < patients.length; i++)
          if (patients[i].photo != null) (i, patients[i].photo!),
      ],
    );
    final incidentId = response['incident_id'];
    if (response['success'] != true || incidentId is! num) {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    }
    return incidentId.toInt();
  }

  Future<List<IncidentPatient>> responderIncidentPatients({
    required String token,
    required int incidentId,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/patients.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'action': 'list',
      },
    );
    final patients = response['patients'];
    if (response['success'] != true || patients is! List) {
      throw const MobileApiException(
        'The server returned an invalid patient list.',
      );
    }
    return [
      for (final patient in patients)
        IncidentPatient.fromJson(patient as Map<String, dynamic>),
    ];
  }

  Future<IncidentPatient> saveResponderPatient({
    required String token,
    required int incidentId,
    required String name,
    required int age,
    required String gender,
    int? heartRate,
    int? spo2,
    String? bloodPressure,
    String? notes,
    List<String> sceneConditions = const [],
    XFile? photo,
  }) async {
    final fields = <String, String>{
      'token': token,
      'incident_id': incidentId.toString(),
      'patient_name': name,
      'patient_age': age.toString(),
      'patient_gender': gender,
      if (heartRate != null) 'heart_rate': heartRate.toString(),
      if (spo2 != null) 'spo2': spo2.toString(),
      if (bloodPressure != null) 'blood_pressure': bloodPressure,
      if (notes != null) 'notes': notes,
      'scene_conditions': jsonEncode(sceneConditions),
    };
    final response = await _sendMultipart(
      'api/responder/patient.php',
      fields: fields,
      photo: photo,
    );
    final patient = response['patient'];
    if (response['success'] != true || patient is! Map<String, dynamic>) {
      throw const MobileApiException(
        'The server returned an invalid patient record.',
      );
    }
    return IncidentPatient.fromJson(patient);
  }

  Future<void> deleteResponderPatient({
    required String token,
    required int incidentId,
    required int patientId,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/patients.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'patient_id': patientId,
        'action': 'delete',
      },
    );
    if (response['success'] != true) {
      throw const MobileApiException('Unable to delete this patient record.');
    }
  }

  Future<Map<String, dynamic>> responderIncidentAction({
    required String token,
    required int incidentId,
    required String action,
    double? latitude,
    double? longitude,
    String? notes,
    String? incidentType,
    String? status,
    int? volunteerUserId,
    int? rating,
    String? review,
  }) async {
    final response = await _send(
      'POST',
      'api/responder/incident_action.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'action': action,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
        if (notes != null) 'notes': notes,
        if (incidentType != null) 'incident_type': incidentType,
        if (status != null) 'status': status,
        if (volunteerUserId != null) 'volunteer_user_id': volunteerUserId,
        if (rating != null) 'rating': rating,
        if (review != null) 'review': review,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'Unable to complete that incident action.',
      );
    }
    return response;
  }

  Future<DrivingRoute> incidentDirections({
    required String token,
    required int incidentId,
    required double latitude,
    required double longitude,
  }) =>
      _fetchIncidentDirections(
        token: token,
        incidentId: incidentId,
        latitude: latitude,
        longitude: longitude,
      );

  Future<DrivingRoute> responderIncidentDirections({
    required String token,
    required int incidentId,
    required double latitude,
    required double longitude,
  }) =>
      _fetchIncidentDirections(
        token: token,
        incidentId: incidentId,
        latitude: latitude,
        longitude: longitude,
        destination: 'incident',
      );

  Future<DrivingRoute> responderStatusDirections({
    required String token,
    required int incidentId,
  }) =>
      _fetchIncidentDirections(
        token: token,
        incidentId: incidentId,
        destination: 'responder_incident',
      );

  Future<DrivingRoute> _fetchIncidentDirections({
    required String token,
    required int incidentId,
    double? latitude,
    double? longitude,
    String destination = 'hospital',
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/directions.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        if (latitude != null && longitude != null) ...{
          'latitude': latitude,
          'longitude': longitude,
        },
        if (destination != 'hospital') 'destination': destination,
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'Unable to load route directions.',
      );
    }
    try {
      return DrivingRoute.fromJson(response);
    } on FormatException {
      throw const MobileApiException(
        'The server returned invalid route directions.',
      );
    }
  }

  Future<List<SceneArrivalAlert>> barangaySceneArrivalAlerts({
    required String token,
  }) async {
    final response = await _send(
      'POST',
      'api/admin/scene_arrival_alerts.php',
      body: {'token': token, 'action': 'queue'},
    );
    final alerts = response['alerts'];
    if (response['success'] != true || alerts is! List) {
      throw const MobileApiException(
        'The server returned an invalid arrival alert list.',
      );
    }
    try {
      final parsedAlerts = <SceneArrivalAlert>[];
      for (final alert in alerts) {
        if (alert is! Map<String, dynamic>) {
          throw const FormatException('Invalid scene arrival alert.');
        }
        parsedAlerts.add(SceneArrivalAlert.fromJson(alert));
      }
      return parsedAlerts;
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid arrival alert.',
      );
    }
  }

  Future<void> acknowledgeBarangaySceneArrivalAlert({
    required String token,
    required int alertId,
  }) async {
    final response = await _send(
      'POST',
      'api/admin/scene_arrival_alerts.php',
      body: {'token': token, 'action': 'acknowledge', 'alert_id': alertId},
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ??
            'Unable to acknowledge this arrival alert.',
      );
    }
  }

  Future<void> updateIncidentNotes({
    required String token,
    required int incidentId,
    required String notes,
  }) async {
    final response = await _send(
      'POST',
      'api/incidents/update_notes.php',
      body: {
        'token': token,
        'incident_id': incidentId,
        'notes': notes.trim(),
      },
    );
    if (response['success'] != true) {
      throw MobileApiException(
        response['message'] as String? ?? 'Unable to send this update.',
      );
    }
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, Object?>? body,
  }) async {
    final baseUrl = _baseUrl.trim();
    final baseUri = Uri.tryParse(baseUrl);
    if (baseUri == null ||
        baseUri.scheme != 'https' ||
        baseUri.host.isEmpty ||
        baseUri.userInfo.isNotEmpty ||
        baseUri.query.isNotEmpty ||
        baseUri.fragment.isNotEmpty) {
      throw const MobileApiException(
        'Set a valid HTTPS API URL before continuing.',
      );
    }

    final normalizedBaseUrl = baseUrl.endsWith('/') ? baseUrl : '$baseUrl/';
    final uri = Uri.parse(normalizedBaseUrl).resolve(path);

    try {
      final request = http.Request(method, uri)
        ..followRedirects = false
        ..headers['Accept'] = 'application/json';
      if (body != null) {
        request.headers['Content-Type'] = 'application/json; charset=utf-8';
        request.body = jsonEncode(body);
      }

      final streamedResponse =
          await _client.send(request).timeout(const Duration(seconds: 15));
      final response = await http.Response.fromStream(streamedResponse)
          .timeout(const Duration(seconds: 15));
      final Object? decoded;
      try {
        decoded = jsonDecode(response.body);
      } on FormatException {
        throw MobileApiException(
          'API endpoint ${uri.path} returned HTTP ${response.statusCode} '
          'instead of JSON. Deploy the updated mobile API endpoint.',
        );
      }

      if (decoded is! Map<String, dynamic>) {
        throw MobileApiException(
          'API endpoint ${uri.path} returned HTTP ${response.statusCode} '
          'instead of a JSON object.',
        );
      }
      if (response.statusCode < 200 || response.statusCode >= 300) {
        if (response.statusCode == 401 &&
            body != null &&
            body['token'] is String) {
          onSessionInvalid?.call();
        }
        final message = decoded['message'];
        throw MobileApiException(
          message is String ? message : 'The request failed. Please try again.',
        );
      }
      return decoded;
    } on MobileApiException {
      rethrow;
    } on TimeoutException {
      throw const MobileApiException(
        'The server took too long to respond. Please try again.',
      );
    } on http.ClientException {
      throw const MobileApiException(
        'Could not reach the server. Check your connection and try again.',
      );
    }
  }

  Future<Map<String, dynamic>> _sendMultipart(
    String path, {
    required Map<String, String> fields,
    XFile? photo,
    List<(int, XFile)> photos = const [],
  }) async {
    final baseUrl = _baseUrl.trim();
    final baseUri = Uri.tryParse(baseUrl);
    if (baseUri == null ||
        baseUri.scheme != 'https' ||
        baseUri.host.isEmpty ||
        baseUri.userInfo.isNotEmpty ||
        baseUri.query.isNotEmpty ||
        baseUri.fragment.isNotEmpty) {
      throw const MobileApiException(
        'Set a valid HTTPS API URL before continuing.',
      );
    }
    final uri =
        Uri.parse(baseUrl.endsWith('/') ? baseUrl : '$baseUrl/').resolve(path);

    try {
      final request = http.MultipartRequest('POST', uri)
        ..followRedirects = false
        ..headers['Accept'] = 'application/json'
        ..fields.addAll(fields);
      if (photo != null) {
        request.files.add(http.MultipartFile.fromBytes(
          'photo',
          await photo.readAsBytes(),
          filename: photo.name,
        ));
      }
      for (final (index, image) in photos) {
        request.files.add(http.MultipartFile.fromBytes(
          'photo_$index',
          await image.readAsBytes(),
          filename: image.name,
        ));
      }
      final streamed = await _client.send(request).timeout(
            const Duration(seconds: 30),
          );
      final response = await http.Response.fromStream(streamed).timeout(
        const Duration(seconds: 15),
      );
      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        throw const MobileApiException(
          'The server returned an invalid response.',
        );
      }
      if (response.statusCode < 200 || response.statusCode >= 300) {
        if (response.statusCode == 401 && fields['token'] != null) {
          onSessionInvalid?.call();
        }
        throw MobileApiException(
          decoded['message'] as String? ??
              'The request failed. Please try again.',
        );
      }
      return decoded;
    } on MobileApiException {
      rethrow;
    } on TimeoutException {
      throw const MobileApiException(
        'The server took too long to respond. Please try again.',
      );
    } on FormatException {
      throw const MobileApiException(
        'The server returned an invalid response.',
      );
    } on http.ClientException {
      throw const MobileApiException(
        'Could not reach the server. Check your connection and try again.',
      );
    }
  }
}
