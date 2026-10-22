import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../models/emergency.dart';
import '../models/incident_patient.dart';
import '../services/location_service.dart';
import '../services/mobile_api_client.dart';
import 'auth_controller.dart';

/// Lets the SOS report show its result on whichever screen is open.
final sosMessengerKey = GlobalKey<ScaffoldMessengerState>();

class SosController extends ChangeNotifier {
  SosController._();
  static final I = SosController._();

  EmergencyStage stage = EmergencyStage.idle;
  DateTime? sentAt;
  int? incidentId;
  String? reportError;

  /// The sent incident as stored in the database; null until it has loaded.
  IncidentDetails? details;
  Timer? _refresh;
  bool _loadingDetails = false;
  static const _refreshEvery = Duration(seconds: 5);

  String get message =>
      reportError ??
      'Your location was shared with your barangay response team.';

  /// Sends the "SOS emergency alert" incident with the device location and any
  /// known user details. Failures are stored in [reportError], never thrown.
  Future<void> submitReport() async {
    reportError = null;
    try {
      final user = AuthController.I.user;
      final token = user?['token'] as String?;
      if (token != null) {
        final active = await MobileApiClient.I.activeIncident(token: token);
        if (active != null) {
          _adoptIncident(active);
          _notifyResult('You already have an active emergency report.');
          return;
        }
      }

      final position = await LocationService.currentPosition();
      incidentId = await MobileApiClient.I.sendSos(
        latitude: position.latitude,
        longitude: position.longitude,
        token: token,
      );
      await _loadDetails();
      _startRefresh();
    } on LocationUnavailableException catch (e) {
      reportError = e.message;
    } on MobileApiException catch (e) {
      reportError = e.message;
    } catch (_) {
      reportError = 'Could not send your SOS. Please try again or call 911.';
    }
    notifyListeners();
    _notifyResult(reportError ??
        'SOS sent. Your report is with the barangay response team.');
  }

  /// Restores the signed-in user's active SOS after login or app restart.
  Future<void> loadActiveIncident() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) return;
    try {
      final active = await MobileApiClient.I.activeIncident(token: token);
      if (active != null) {
        _adoptIncident(active);
      } else if (stage != EmergencyStage.sending) {
        incidentId = null;
        details = null;
        stage = EmergencyStage.idle;
        _stopRefresh();
        notifyListeners();
      }
    } on MobileApiException catch (error) {
      reportError = error.message;
      notifyListeners();
    }
  }

  void _adoptIncident(IncidentDetails incident) {
    incidentId = incident.incidentId;
    details = incident;
    sentAt ??= DateTime.now();
    stage = incident.responderName == null
        ? EmergencyStage.confirmed
        : EmergencyStage.assigned;
    _startRefresh();
    notifyListeners();
  }

  void _startRefresh() {
    _refresh?.cancel();
    _refresh = Timer.periodic(_refreshEvery, (_) => _loadDetails());
  }

  void _notifyResult(String message) {
    sosMessengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(message),
        duration: const Duration(seconds: 6),
      ));
  }

  /// Creates a resident report only after the resident confirms the review.
  Future<int> submitResidentReport({
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
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      throw const MobileApiException('Sign in again before sending a report.');
    }

    _refresh?.cancel();
    stage = EmergencyStage.sending;
    reportError = null;
    details = null;
    incidentId = null;
    notifyListeners();

    final int submittedIncidentId;
    try {
      submittedIncidentId = await MobileApiClient.I.submitResidentReport(
        token: token,
        latitude: latitude,
        longitude: longitude,
        incidentType: incidentType,
        status: status,
        patientName: patientName,
        patientAge: patientAge,
        patientGender: patientGender,
        notes: notes,
        volunteerUserId: volunteerUserId,
        heartRate: heartRate,
        spo2: spo2,
        bloodPressure: bloodPressure,
        sceneConditions: sceneConditions,
        photo: photo,
        patients: patients,
        requestAnotherVehicle: requestAnotherVehicle,
        reportedBy: reportedBy,
      );
      incidentId = submittedIncidentId;
    } on MobileApiException catch (error) {
      stage = EmergencyStage.idle;
      reportError = error.message;
      notifyListeners();
      rethrow;
    }

    sentAt = DateTime.now();
    stage = EmergencyStage.confirmed;
    await _loadDetails();
    _refresh = Timer.periodic(_refreshEvery, (_) => _loadDetails());
    notifyListeners();
    return submittedIncidentId;
  }

  /// Reloads the incident so the card follows what the admin dispatch assigns.
  /// A failed refresh keeps the last details instead of reporting an error.
  Future<void> _loadDetails() async {
    final id = incidentId;
    if (id == null || _loadingDetails) return;
    _loadingDetails = true;
    try {
      final updatedDetails = await MobileApiClient.I.incidentDetails(
        incidentId: id,
        token: AuthController.I.user?['token'] as String? ?? '',
      );
      if (incidentId != id) return;
      details = updatedDetails;
      reportError = null;
      if (details?.responderName != null && stage == EmergencyStage.confirmed) {
        stage = EmergencyStage.assigned;
      }
      if (details?.status == 'resolved') {
        stage = EmergencyStage.completed;
        _stopRefresh();
      }
      if (stage == EmergencyStage.completed &&
          _isVolunteerAssistanceTerminal(details)) {
        _stopRefresh();
      }
      notifyListeners();
    } on MobileApiException catch (error) {
      if (incidentId != id) return;
      reportError = error.message;
      notifyListeners();
    } finally {
      _loadingDetails = false;
    }
  }

  bool _isVolunteerAssistanceTerminal(IncidentDetails? incident) =>
      incident == null ||
      incident.volunteerName == null ||
      incident.volunteerAssistanceStatus == 'completed' ||
      incident.volunteerAssistanceStatus == 'declined';

  Future<void> refreshDetails() => _loadDetails();

  void _stopRefresh() {
    _refresh?.cancel();
    _refresh = null;
  }

  String get status {
    switch (stage) {
      case EmergencyStage.idle:
        return 'Ready';
      case EmergencyStage.sending:
        return 'Sending SOS';
      case EmergencyStage.confirmed:
        return 'SOS confirmed';
      case EmergencyStage.assigned:
        return 'Responder assigned';
      case EmergencyStage.tracking:
        return 'En route';
      case EmergencyStage.arrived:
        return 'Responder arrived';
      case EmergencyStage.completed:
        return 'Case completed';
    }
  }

  void send() {
    if (stage != EmergencyStage.idle && stage != EmergencyStage.completed) {
      return;
    }

    _stopRefresh();
    stage = EmergencyStage.sending;
    sentAt = null;
    incidentId = null;
    details = null;
    reportError = null;
    notifyListeners();
  }

  void confirm() {
    if (stage == EmergencyStage.sending) {
      sentAt = DateTime.now();
      stage = EmergencyStage.confirmed;
      notifyListeners();
    }
  }

  void assignResponder() {
    if (stage == EmergencyStage.confirmed) {
      stage = EmergencyStage.assigned;
      notifyListeners();
    }
  }

  void startTracking() {
    if (stage == EmergencyStage.assigned || stage == EmergencyStage.confirmed) {
      stage = EmergencyStage.tracking;
      notifyListeners();
    }
  }

  void markArrived() {
    if (stage == EmergencyStage.tracking) {
      stage = EmergencyStage.arrived;
      notifyListeners();
    }
  }

  void completeCase() {
    if (stage == EmergencyStage.completed) return;
    stage = EmergencyStage.completed;
    if (_isVolunteerAssistanceTerminal(details)) _stopRefresh();
    notifyListeners();
  }

  void reset() {
    if (stage == EmergencyStage.idle && sentAt == null && incidentId == null) {
      return;
    }
    stage = EmergencyStage.idle;
    sentAt = null;
    incidentId = null;
    details = null;
    reportError = null;
    _stopRefresh();
    notifyListeners();
  }
}
