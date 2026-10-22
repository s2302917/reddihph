import 'package:flutter/material.dart';

import '../../core/theme.dart';

const kVol = kVolunteerBrand;
const kVolDark = Color(0xFF1A242F);
const kVolMid = Color(0xFF566277);
const kVolLight = Color(0xFF8A95A8);
const kStar = Color(0xFFF5A623);

const kVolGradient = LinearGradient(
  colors: [kVol, kVolDark],
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
);

/// Volunteer button. [light] = soft slate button (for use on dark cards).
class VolButton extends StatelessWidget {
  final String text;
  final VoidCallback? onTap;
  final bool light;
  const VolButton(this.text, {super.key, this.onTap, this.light = false});

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.transparent,
        child: Ink(
          decoration: BoxDecoration(
            gradient: light ? null : kVolGradient,
            color: light ? kVolLight.withValues(alpha: .55) : null,
            borderRadius: BorderRadius.circular(6),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(6),
            onTap: onTap ?? () {},
            child: Container(
              height: 40,
              alignment: Alignment.center,
              child: Text(
                text,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .3,
                ),
              ),
            ),
          ),
        ),
      );
}

/// Row of stars. Pass [onChanged] to make it tappable.
class Stars extends StatelessWidget {
  final int value;
  final double size;
  final int count;
  final ValueChanged<int>? onChanged;
  const Stars(this.value,
      {super.key, this.size = 12, this.count = 5, this.onChanged});

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (int i = 0; i < count; i++)
            GestureDetector(
              onTap: onChanged == null ? null : () => onChanged!(i + 1),
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: size * .12),
                child: Icon(
                  i < value ? Icons.star : Icons.star_border,
                  size: size,
                  color: i < value ? kStar : Colors.black87,
                ),
              ),
            ),
        ],
      );
}
