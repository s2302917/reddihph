import 'package:flutter/material.dart';

const kBrand = Color(0xFF4B3F9E);
const kResidentBrand = Color(0xFFA8B0B7);
const kResidentNavigation = Color(0xFF59636D);
const kResponderBrand = Color(0xFFA6623D);
const kDoctorBrand = Color(0xFF020544);
const kNurseBrand = Color(0xFF3A4545);
const kVolunteerBrand = Color(0xFF1F2A37);
const kBarangayAdminBrand = Color(0xFF491717);
const kSos = Color(0xFF7A1410);
const kGrey = Color(0xFFD9D9D9);

Color roleButtonShade(Color color) =>
    Color.alphaBlend(const Color(0x26000000), color);

LinearGradient roleButtonGradient(Color color) => LinearGradient(
      colors: [color, roleButtonShade(color)],
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
    );

/// Shared animation timings / curves so every screen feels the same.
const kFast = Duration(milliseconds: 160);
const kMedium = Duration(milliseconds: 260);
const kEase = Curves.easeOutCubic;

/// Fade + short slide-in for the incoming page, with a light parallax on the
/// page underneath. Uses only Fade/Slide transitions (no layout or opacity
/// layers rebuilt per frame), so it stays smooth on low-end phones.
class SmoothPageTransitionsBuilder extends PageTransitionsBuilder {
  const SmoothPageTransitionsBuilder();

  static final _fade = CurveTween(curve: Curves.easeOut);
  static final _curve = CurveTween(curve: Curves.easeOutCubic);
  static final _slideIn = Tween<Offset>(
    begin: const Offset(0.08, 0),
    end: Offset.zero,
  );
  static final _parallax = Tween<Offset>(
    begin: Offset.zero,
    end: const Offset(-0.03, 0),
  );

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    return SlideTransition(
      position: _parallax.chain(_curve).animate(secondaryAnimation),
      child: FadeTransition(
        opacity: _fade.animate(animation),
        child: SlideTransition(
          position: _slideIn.chain(_curve).animate(animation),
          child: child,
        ),
      ),
    );
  }
}

ThemeData? _cachedTheme;

/// Built once and reused.
ThemeData appTheme() => _cachedTheme ??= ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: Colors.white,
      colorSchemeSeed: kBrand,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: SmoothPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.fuchsia: SmoothPageTransitionsBuilder(),
          TargetPlatform.linux: SmoothPageTransitionsBuilder(),
          TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.windows: SmoothPageTransitionsBuilder(),
        },
      ),
    );
