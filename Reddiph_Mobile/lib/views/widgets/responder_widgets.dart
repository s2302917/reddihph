import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../../models/responder_dashboard.dart';

const kResponderBrown = kResponderBrand;
const kResponderOrange = Color(0xFFDF8062);

/// Pill colours for a case severity ('Critical' / 'Urgent' / 'Stable').
({Color bg, Color fg}) severityColors(String severity) => switch (severity) {
      'Critical' => (bg: const Color(0xFFE9B7B1), fg: const Color(0xFF7A1410)),
      'Urgent' => (bg: const Color(0xFFF2E3A6), fg: const Color(0xFF7A5C00)),
      _ => (bg: const Color(0xFFDADADA), fg: const Color(0xFF333333)),
    };

/// "Previous / Page 1 of 3 / Next" row under a paged incident list.
class IncidentPager extends StatelessWidget {
  final IncidentPage page;
  final VoidCallback onPrevious, onNext;

  const IncidentPager({
    super.key,
    required this.page,
    required this.onPrevious,
    required this.onNext,
  });

  @override
  Widget build(BuildContext context) => Row(children: [
        TextButton(
          onPressed: page.hasPrevious ? onPrevious : null,
          child: const Text('Previous'),
        ),
        Expanded(
          child: Text('Page ${page.page} of ${page.pages}',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 10, color: Colors.black54)),
        ),
        TextButton(
          onPressed: page.hasNext ? onNext : null,
          child: const Text('Next'),
        ),
      ]);
}
