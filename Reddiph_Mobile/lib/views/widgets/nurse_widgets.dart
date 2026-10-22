import 'package:flutter/material.dart';

import '../../core/theme.dart';

const kNurse = kNurseBrand;
const kNurse2 = Color(0xFF313B3B);
const kNurseMid = Color(0xFF5E6A68);
const kNurseLight = Color(0xFFA9B3B1);

const kNurseGradient = LinearGradient(
  colors: [kNurse, kNurse2],
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
);

/// Dark green-grey button used on the nurse screens.
class NurseButton extends StatelessWidget {
  final String text;
  final VoidCallback? onTap;
  const NurseButton(this.text, {super.key, this.onTap});

  @override
  Widget build(BuildContext context) => Material(
        color: Colors.transparent,
        child: Ink(
          decoration: BoxDecoration(
            gradient: kNurseGradient,
            borderRadius: BorderRadius.circular(6),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(6),
            onTap: onTap,
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
