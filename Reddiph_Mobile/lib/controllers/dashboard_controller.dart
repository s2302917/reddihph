import 'package:flutter/foundation.dart';

import 'auth_controller.dart';
import '../models/demo_data.dart';
import '../models/emergency.dart';
export '../models/emergency.dart';

/// Supplies dashboard data to views and handles user actions.
class DashboardController extends ChangeNotifier {
  DashboardController._();
  static final I = DashboardController._();

  Profile profileFor(String role) {
    final fallback = DemoData.profiles[role]!;
    if (role != 'Responder') return fallback;

    final user = AuthController.I.user;
    if (user == null) return fallback;

    final fullName =
        (user['full_name'] ?? user['name'] ?? fallback.title).toString();
    final vehicleType = (user['vehicle_type'] ?? 'Ambulance').toString();
    final callSign = (user['call_sign'] ?? 'RB-04').toString();
    final plateNumber = user['plate_number']?.toString();
    final initials =
        fullName.replaceAll(RegExp(r'\s'), '').substring(0, 2).toUpperCase();

    if (vehicleType.isNotEmpty && callSign.isNotEmpty) {
      final title = callSign;
      final subtitle = plateNumber == null || plateNumber.isEmpty
          ? '$fullName · $vehicleType'
          : '$fullName · $vehicleType · $plateNumber';
      return Profile(title, subtitle, initials);
    }

    return Profile(fallback.title, '$fullName · Responder', initials);
  }

  ResidentReport get residentReport => DemoData.residentReport;
  PatientCase get dispatch => DemoData.dispatch;
  List<QueueItem> get sosQueue => DemoData.sosQueue;
  List<PatientCase> get incomingPatients => DemoData.incomingPatients;
  List<ReviewCase> get reviewCases => DemoData.reviewCases;
  List<ResourceStat> get resources => DemoData.resources;
  List<AssistRequest> get requests => DemoData.requests;
  List<UserFeedback> get feedback => DemoData.feedback;

  final List<ChecklistItem> checklist = DemoData.checklist();
  void toggleCheck(int i, bool v) {
    checklist[i].done = v;
    notifyListeners();
  }
}
