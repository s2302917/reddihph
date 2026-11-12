import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../../models/responder_dashboard.dart';

const kResponderBrown = kResponderBrand;
const kResponderOrange = Color(0xFFDF8062);

/// Pill colours for the four emergency categories.
({Color bg, Color fg}) severityColors(String severity) => switch (severity) {
      'Emergent' => (bg: const Color(0xFFDC2626), fg: Colors.white),
      'Urgent' => (bg: const Color(0xFFEAB308), fg: Colors.black),
      'Non-Urgent' => (bg: const Color(0xFF16A34A), fg: Colors.white),
      'Expectant' => (bg: const Color(0xFF000000), fg: Colors.white),
      _ => (bg: const Color(0xFF6B7280), fg: Colors.white),
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
