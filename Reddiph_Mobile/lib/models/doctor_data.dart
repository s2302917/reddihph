/// Doctor-side demo data (cases, timeline, schedule). Replace with API calls.
class DoctorCase {
  final String name, summary, severity, unit, bay, eta, distance, arrives;
  final String pulse, spo2, bp, blood;
  final String status; // 'Incoming' | 'In ER' | 'Done'
  final bool needsReview;
  final List<(String, String, String)> timeline; // title, detail, time
  const DoctorCase({
    required this.name,
    required this.summary,
    required this.severity,
    required this.status,
    this.unit = 'Unit RB-04',
    this.bay = 'ER bay 3',
    this.eta = '6 min',
    this.distance = '2.4 km',
    this.arrives = '11:38',
    this.pulse = '--',
    this.spo2 = '--',
    this.bp = '--',
    this.blood = '--',
    this.needsReview = false,
    this.timeline = const [],
  });

  bool get critical => severity == 'Critical';
}

enum ShiftType { day, night, onCall, off }

class DoctorData {
  static const cases = <DoctorCase>[
    DoctorCase(
      name: 'Dela Cruz, J.',
      summary: 'Vehicular collision · 42, male',
      severity: 'Critical',
      status: 'Incoming',
      pulse: '102',
      spo2: '92%',
      bp: '90/60',
      blood: 'O+',
      timeline: [
        ('Reported by responder', 'Unit RB-04 · Kuya Mark', '11:14'),
        ('Dispatched, en route', '', '11:16'),
        ('Arriving, ER bay 3 reserved', '', '11:22'),
      ],
    ),
    DoctorCase(
      name: 'Aling Nena',
      summary: 'Fall injury · 71, female',
      severity: 'Stable',
      status: 'Incoming',
      eta: '14 min',
      distance: '5.1 km',
      arrives: '11:46',
      pulse: '84',
      spo2: '96%',
      bp: '130/80',
      blood: 'A+',
      timeline: [
        ('Reported by resident', 'Purok 7', '11:20'),
        ('Dispatched, en route', '', '11:24'),
      ],
    ),
    DoctorCase(
      name: 'Dela Cruz, J.',
      summary: 'Vehicular collision · 42, male',
      severity: 'Review',
      status: 'In ER',
      needsReview: true,
      pulse: '98',
      spo2: '95%',
      bp: '110/70',
      blood: 'O+',
    ),
  ];

  static List<DoctorCase> get urgency =>
      cases.where((c) => !c.needsReview).toList();
  static List<DoctorCase> get review =>
      cases.where((c) => c.needsReview).toList();

  /// Shift per day for October 2026 (demo pattern).
  static ShiftType shiftFor(int day) {
    if (day == 3 || day == 10 || day == 17 || day == 24 || day == 31) {
      return ShiftType.onCall;
    }
    if (day % 7 == 1 || day % 7 == 2) return ShiftType.night;
    if (day % 7 == 4 || day % 7 == 5) return ShiftType.off;
    return ShiftType.day;
  }
}
