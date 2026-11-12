import 'package:flutter/material.dart';

enum EmergencyCategory {
  emergent(
    'emergent',
    'Emergent',
    Color(0xFFDC2626),
    Colors.white,
  ),
  urgent(
    'urgent',
    'Urgent',
    Color(0xFFEAB308),
    Colors.black,
  ),
  nonUrgent(
    'non_urgent',
    'Non-Urgent',
    Color(0xFF16A34A),
    Colors.white,
  ),
  expectant(
    'expectant',
    'Expectant',
    Color(0xFF000000),
    Colors.white,
  );

  const EmergencyCategory(this.value, this.label, this.color, this.textColor);

  final String value;
  final String label;
  final Color color;
  final Color textColor;

  static EmergencyCategory? fromValue(String? value) {
    for (final category in values) {
      if (category.value == value) return category;
    }
    return null;
  }
}
