/// Philippine Standard Time (UTC+8), independent of the device's own time zone.
class PhilippineTime {
  const PhilippineTime._();

  static const _months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];
  static const _days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];

  /// The current Philippine date and time, read from the device's UTC clock.
  static DateTime now() => DateTime.now().toUtc().add(const Duration(hours: 8));

  /// Morning before noon, afternoon until 5:59 PM, evening after that.
  static String greeting(DateTime time) => time.hour < 12
      ? 'Good Morning,'
      : time.hour < 18
          ? 'Good Afternoon,'
          : 'Good Evening,';

  /// "October 2026, Wednesday"
  static String monthYearDay(DateTime time) =>
      '${_months[time.month - 1]} ${time.year}, ${_days[time.weekday - 1]}';

  /// "4:15 PM"
  static String clock(DateTime time) {
    final hour = time.hour % 12 == 0 ? 12 : time.hour % 12;
    final minute = time.minute.toString().padLeft(2, '0');
    return '$hour:$minute ${time.hour < 12 ? 'AM' : 'PM'}';
  }
}
