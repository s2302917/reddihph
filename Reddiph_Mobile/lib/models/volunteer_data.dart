class VolunteerPerson {
  final String name, thanks;
  const VolunteerPerson(this.name, this.thanks);
  String get initial => name[0];
}

class AppreciationEntry {
  final String name, role, quote, from, when;
  final int stars;
  const AppreciationEntry({
    required this.name,
    required this.role,
    required this.quote,
    required this.from,
    required this.when,
    this.stars = 5,
  });
}

class HistoryEntry {
  final String title, detail, rating, day;
  const HistoryEntry(this.title, this.detail, this.rating, this.day);
}

/// Volunteer demo data. Replace with API calls.
class VolunteerData {
  static const people = <VolunteerPerson>[
    VolunteerPerson('Kuya Mark', '12 thanks'),
    VolunteerPerson('Rina', '8 thanks'),
    VolunteerPerson('Mang Rudy', '7 thanks'),
  ];

  static List<AppreciationEntry> wall() => const [
        AppreciationEntry(
          name: 'Kuya Mark',
          role: 'Volunteer · Vehicle transport',
          quote: 'Maraming salamat po sa mabilis na tulong.',
          from: 'From Responder Santos',
          when: 'Today',
        ),
        AppreciationEntry(
          name: 'Rina Domingo',
          role: 'Volunteer · Vehicle transport',
          quote: 'Got us to the health center fast when no ambulance was free.',
          from: 'From Responder Santos',
          when: 'Today',
        ),
        AppreciationEntry(
          name: 'Mang Rudy',
          role: 'Volunteer · Vehicle transport',
          quote: 'Came within minutes and drove carefully.',
          from: 'From Responder Santos',
          when: '3 days ago',
        ),
      ];

  static const history = <HistoryEntry>[
    HistoryEntry(
        'Vehicle transport — Aling Nena', 'Purok 7 · Today', '5.0', 'Today'),
    HistoryEntry('Report assist — Reyes family', 'Purok 2 · Yesterday', '4.5',
        'Yesterday'),
  ];
}
