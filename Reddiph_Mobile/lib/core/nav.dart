import 'package:flutter/material.dart';

/// One place for navigation so every screen change animates the same way.
/// Normal pushes use the themed page transition (see [appTheme]); resets
/// (login / logout / tab jumps from the SOS flow) cross-fade instead of
/// sliding, which looks right when the whole stack is replaced.
class Nav {
  Nav._();

  static void push(BuildContext context, Widget page) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));
  }

  static void replace(BuildContext context, Widget page) {
    Navigator.of(context)
        .pushReplacement(MaterialPageRoute<void>(builder: (_) => page));
  }

  static void resetTo(BuildContext context, Widget page) {
    Navigator.of(context).pushAndRemoveUntil(
      PageRouteBuilder<void>(
        transitionDuration: const Duration(milliseconds: 320),
        reverseTransitionDuration: const Duration(milliseconds: 200),
        pageBuilder: (_, __, ___) => page,
        transitionsBuilder: (_, animation, __, child) => FadeTransition(
          opacity: CurveTween(curve: Curves.easeOut).animate(animation),
          child: child,
        ),
      ),
      (_) => false,
    );
  }
}
