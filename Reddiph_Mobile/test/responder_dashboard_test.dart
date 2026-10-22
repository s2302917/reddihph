import 'package:flutter_test/flutter_test.dart';
import 'package:reddiph/core/philippine_time.dart';
import 'package:reddiph/models/responder_dashboard.dart';

void main() {
  group('PhilippineTime.greeting', () {
    String at(int hour, [int minute = 0]) =>
        PhilippineTime.greeting(DateTime.utc(2026, 10, 14, hour, minute));

    test('follows the three Philippine time bands', () {
      expect(at(0), 'Good Morning,');
      expect(at(11, 59), 'Good Morning,');
      expect(at(12), 'Good Afternoon,');
      expect(at(17, 59), 'Good Afternoon,');
      expect(at(18), 'Good Evening,');
      expect(at(23, 59), 'Good Evening,');
    });
  });

  test('formats the date, day and 12-hour clock', () {
    final time = DateTime.utc(2026, 10, 14, 16, 5);
    expect(PhilippineTime.monthYearDay(time), 'October 2026, Wednesday');
    expect(PhilippineTime.clock(time), '4:05 PM');
    expect(PhilippineTime.clock(DateTime.utc(2026, 10, 14, 0, 0)), '12:00 AM');
  });

  group('upcoming responder cases', () {
    test('shows upcoming cases only within five minutes of hospital arrival',
        () {
      expect(
        shouldShowUpcomingResponderCases(
          sentToHospital: true,
          etaMinutes: 5,
        ),
        isTrue,
      );
      expect(
        shouldShowUpcomingResponderCases(
          sentToHospital: true,
          etaMinutes: 6,
        ),
        isFalse,
      );
      expect(
        shouldShowUpcomingResponderCases(
          sentToHospital: false,
          etaMinutes: 1,
        ),
        isFalse,
      );
      expect(
        shouldShowUpcomingResponderCases(
          sentToHospital: true,
          etaMinutes: null,
        ),
        isFalse,
      );
    });
  });

  group('ResponderDashboard', () {
    Map<String, dynamic> json({Map<String, dynamic>? vehicle}) => {
          'responder': {'full_name': 'Mark Reyes', 'vehicle': vehicle},
          'active_case': null,
          'queue': {
            'items': [
              {
                'incident_id': 3,
                'incident_type': 'SOS emergency alert',
                'status': 'critical',
                'location': 'GPS 1, 2',
                'barangay': 'Barangay A',
                'patient_name': 'Jamie Cruz',
                'patient_age': 29,
                'patient_gender': 'female',
                'responder_name': 'Mark Reyes',
                'minutes_ago': 75,
                'distance_km': 2.44,
              },
            ],
            'page': 1,
            'pages': 2,
            'total': 6,
          },
        };

    test('shows only "Responder" without a vehicle', () {
      final data = ResponderDashboard.fromJson(json());
      expect(data.roleLabel, 'Responder');
      expect(data.initials, 'MA');
    });

    test('adds the vehicle and plate for a driver', () {
      final data = ResponderDashboard.fromJson(json(vehicle: {
        'vehicle_type': 'Ambulance',
        'call_sign': 'RB-04',
        'plate_number': 'ABC 123',
      }));
      expect(data.roleLabel, 'Responder - Ambulance RB-04 · ABC 123');
    });

    test('reads the incoming page and its labels', () {
      final queue = ResponderDashboard.fromJson(json()).queue;
      final item = queue.items.single;
      expect(queue.hasNext, isTrue);
      expect(queue.hasPrevious, isFalse);
      expect(item.location, 'Barangay A');
      expect(item.distanceLabel, '2.4 km');
      expect(item.agoLabel, '1 h ago');
      expect(item.severity, 'Critical');
      expect(item.patientName, 'Jamie Cruz');
      expect(item.patientAge, 29);
      expect(item.patientGender, 'female');
      expect(item.responderName, 'Mark Reyes');
    });

    test('reads whether the active incident was sent to hospital', () {
      final incident = ResponderIncident.fromJson({
        'incident_id': 9,
        'incident_type': 'SOS emergency alert',
        'status': 'urgent',
        'location': 'Barangay A',
        'minutes_ago': 2,
        'sent_to_hospital': true,
      });

      expect(incident.sentToHospital, isTrue);
    });

    test('only account-backed resident SOS cases can be flagged', () {
      final base = {
        'incident_id': 9,
        'incident_type': 'SOS emergency alert',
        'status': 'critical',
        'location': 'Barangay A',
        'minutes_ago': 2,
      };
      final eligible = ResponderIncident.fromJson({
        ...base,
        'reported_by': 'resident_sos',
        'has_reporter_account': true,
      }).toQueueItem();
      final anonymous = ResponderIncident.fromJson({
        ...base,
        'reported_by': 'resident_sos',
        'has_reporter_account': false,
      }).toQueueItem();
      final nonResidentCase = ResponderIncident.fromJson({
        ...base,
        'reported_by': 'responder_report',
        'has_reporter_account': true,
      }).toQueueItem();

      expect(eligible.canFlagFalseReport, isTrue);
      expect(anonymous.canFlagFalseReport, isFalse);
      expect(nonResidentCase.canFlagFalseReport, isFalse);
    });
  });

  test('ResponderQueue reads the page, counts and initials', () {
    final queue = ResponderQueue.fromJson({
      'responder': {'full_name': 'Mark Reyes'},
      'counts': {'all': 12, 'critical': 3, 'urgent': 4, 'stable': 5},
      'queue': {'items': [], 'page': 2, 'pages': 2, 'total': 12},
    });
    expect(queue.initials, 'MA');
    expect(queue.counts['critical'], 3);
    expect(queue.page.hasPrevious, isTrue);
    expect(queue.page.hasNext, isFalse);
  });
}
