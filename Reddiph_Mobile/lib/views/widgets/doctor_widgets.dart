import 'package:flutter/material.dart';
import '../../core/theme.dart';

const kNavy = kDoctorBrand;
const kNavy2 = Color(0xFF02043A);
const kSlate = Color(0xFF6E7EA6);
const kSlateLight = Color(0xFFAEB7D3);
const kCritBg = Color(0xFFC9918B);
const kStableBg = Color(0xFF9AA39C);
const kReviewBg = Color(0xFFF2E3A6);

const kNavyGradient = LinearGradient(
  colors: [kNavy, kNavy2],
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
);

/// Pill colours for a doctor case severity ('Critical' / 'Review' / 'Stable').
({Color bg, Color fg}) doctorSeverity(String s) => switch (s) {
      'Critical' => (bg: kCritBg, fg: kSos),
      'Review' => (bg: kReviewBg, fg: const Color(0xFF7A5C00)),
      _ => (bg: kStableBg, fg: Colors.white),
    };

/// Navy gradient button used on the doctor screens.
class NavyButton extends StatelessWidget {
  final String text;
  final VoidCallback? onTap;
  const NavyButton(this.text, {super.key, this.onTap});

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.transparent,
        child: Ink(
          decoration: BoxDecoration(
            gradient: kNavyGradient,
            borderRadius: BorderRadius.circular(6),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(6),
            onTap: onTap ?? () {},
            child: Container(
              height: 46,
              alignment: Alignment.center,
              child: Text(
                text,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .3,
                ),
              ),
            ),
          ),
        ),
      );
}

/// One rounded vital-sign tile (value + label).
class VitalTile extends StatelessWidget {
  final String value, label;
  const VitalTile(this.value, this.label, {super.key});

  @override
  Widget build(BuildContext context) => Expanded(
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 3),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: Colors.white70),
          ),
          child: Column(children: [
            Text(
              value,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              label,
              style: const TextStyle(color: Colors.white70, fontSize: 8),
            ),
          ]),
        ),
      );
}

/// Pulse / SpO2 / BP / Blood row.
class VitalsRow extends StatelessWidget {
  final String pulse, spo2, bp, blood;
  const VitalsRow({
    super.key,
    required this.pulse,
    required this.spo2,
    required this.bp,
    required this.blood,
  });

  @override
  Widget build(BuildContext context) => Row(children: [
        VitalTile(pulse, 'Pulse'),
        VitalTile(spo2, 'SpO2'),
        VitalTile(bp, 'BP'),
        VitalTile(blood, 'Blood'),
      ]);
}

/// Outlined white card with rounded corners.
class DCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;
  final EdgeInsets margin;
  const DCard(
    this.child, {
    super.key,
    this.padding = const EdgeInsets.all(12),
    this.margin = const EdgeInsets.only(bottom: 10),
  });

  @override
  Widget build(BuildContext context) => Container(
        margin: margin,
        padding: padding,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.black12),
        ),
        child: child,
      );
}

/// Small severity / status pill.
class SmallPill extends StatelessWidget {
  final String text;
  final ({Color bg, Color fg}) c;
  const SmallPill(this.text, this.c, {super.key});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
        decoration: BoxDecoration(
          color: c.bg,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          text,
          style: TextStyle(
            fontSize: 8,
            color: c.fg,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
}
