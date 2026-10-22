import 'package:flutter/material.dart';
import 'emergency.dart';

/// Static demo data. Replace with API / repository calls later.
class DemoData {
  static const profiles = <String, Profile>{
    'Resident': Profile(
        'Hi, Sean',
        'Brgy. San Isidro, Alfonso\nReport an emergency or check your case status',
        'S'),
    'Responder': Profile('Unit RB-04', 'Kuya Mark · Ambulance', 'M'),
    'Doctor': Profile('Dr. Villanueva', 'District Hospital · ER shift', 'V'),
    'Nurse': Profile('Nurse Cruz', 'District Hospital · Ward 2', 'C'),
    'Volunteer': Profile('Hi, Rina', '4.8 ★  31 assists', 'R'),
  };

  static const residentReport = ResidentReport('Difficulty breathing — Mother',
      'En route', 2, 'Barangay ambulance\n4 min away');

  static const dispatch = PatientCase('Vehicular collision',
      'Purok 3, near covered court', '6 min', 'Critical');

  static const sosQueue = <QueueItem>[
    QueueItem('Difficulty breathing', 'Brgy. San Isidro · Fidel R.', '0.8 km',
        '1 min ago',
        severity: 'Critical', patient: 'Fidel R.'),
    QueueItem(
        'Fall injury, elderly', 'Purok 7 · Aling Nena', '1.6 km', '4 min ago',
        severity: 'Urgent', patient: 'Aling Nena'),
    QueueItem('Fever, child', 'Purok 2 · Reported by volunteer', '2.1 km',
        '9 min ago',
        severity: 'Stable', patient: 'Child', reportedBy: 'Volunteer'),
  ];

  static const incomingPatients = <PatientCase>[
    PatientCase('Vehicular collision — Patient 1',
        'Purok 3, near covered court', '6 min', 'Critical',
        pulse: '102', spo2: '92%', bp: '90/60'),
    PatientCase('Fall injury — Aling Nena', 'Purok 7', '14 mins', 'Stable'),
  ];

  static const reviewCases = <ReviewCase>[
    ReviewCase('Reyes, M. — Discharged', 'Admitted 08:14 · Fracture, arm'),
    ReviewCase(
        'Santos, D. — Under observation', 'Admitted 08:50 · Dengue, day 2'),
  ];

  static const resources = <ResourceStat>[
    ResourceStat('Beds', '15', 'of 20 open', Color(0xFF323C3C)),
    ResourceStat('Beds', '2', 'of 6 open', Color(0xFF7A3A22)),
    ResourceStat('Ambulances', '1', 'of 4 available', Color(0xFF7A1410)),
    ResourceStat('Doctors', '5', 'on duty', Color(0xFF323C3C)),
  ];

  static List<ChecklistItem> checklist() => [
        ChecklistItem('ER bay 3 reserved', true),
        ChecklistItem('Blood type on file — O+', true),
        ChecklistItem('Trauma team notified', false),
      ];

  static const requests = <AssistRequest>[
    AssistRequest('Vehicle needed — ambulance unavailable',
        'Purok 7 · Fall injury, elderly · 1.2 km', 'offer my vehicle'),
    AssistRequest('Report assist requested',
        'Purok 2 · Family needs help filing SOS', 'Assist now'),
  ];

  static const feedback = <UserFeedback>[
    UserFeedback('★ 5.0',
        'Family of Aling Nena: "Rina got us to the health center fast when no ambulance was free. Grateful."'),
  ];
}
