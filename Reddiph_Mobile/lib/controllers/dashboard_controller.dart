import 'package:flutter/foundation.dart';
import '../models/demo_data.dart';
import '../models/emergency.dart';
export '../models/emergency.dart';

/// Supplies dashboard data to views and handles user actions.
class DashboardController extends ChangeNotifier {
  DashboardController._();
  static final I = DashboardController._();

  Profile profileFor(String role) => DemoData.profiles[role]!;
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
