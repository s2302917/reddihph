import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'controllers/auth_controller.dart';
import 'controllers/sos_controller.dart';
import 'core/theme.dart';
import 'models/nurse_portal.dart';
import 'services/app_language.dart';
import 'services/mobile_api_client.dart';
import 'services/location_service.dart';
import 'views/screens/opening_screen.dart';
import 'views/screens/portal_screen.dart';

void main() => runApp(const ReddiphApp());

class ReddiphApp extends StatefulWidget {
  const ReddiphApp({super.key});

  @override
  State<ReddiphApp> createState() => _ReddiphAppState();
}

class _ReddiphAppState extends State<ReddiphApp> with WidgetsBindingObserver {
  bool _openingComplete = false;
  Timer? _sessionTimer;
  bool _handlingInvalidSession = false;
  bool _appResumed = true;

  static final _navigatorKey = GlobalKey<NavigatorState>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    MobileApiClient.onSessionInvalid = _handleInvalidSession;
    unawaited(AppLanguageService.I.load());
    _sessionTimer = Timer.periodic(
      const Duration(seconds: 60),
      (_) => _validateSession(),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sessionTimer?.cancel();
    MobileApiClient.onSessionInvalid = null;
    super.dispose();
  }

  // Location is the only way to find the user, so ask on every open until allowed.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appResumed = state == AppLifecycleState.resumed;
    if (_appResumed && _openingComplete) {
      _askForLocation();
      _validateSession();
    }
  }

  Future<void> _validateSession() async {
    if (!_appResumed) return;
    final token = AuthController.I.user?['token'];
    if (token is! String || token.isEmpty || _handlingInvalidSession) return;
    try {
      await MobileApiClient.I.validateSession(token: token);
    } on MobileApiException catch (error) {
      debugPrint('Session validation deferred: ${error.message}');
    }
  }

  void _handleInvalidSession(String message) {
    if (_handlingInvalidSession || AuthController.I.user == null) return;
    _handlingInvalidSession = true;
    final token = AuthController.I.user?['token'];
    if (token is String) NursePortalCache.clear(token);
    AuthController.I.logout();
    final navigator = _navigatorKey.currentState;
    navigator?.pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const PortalScreen()),
      (_) => false,
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      sosMessengerKey.currentState?.showSnackBar(
        SnackBar(content: Text(message)),
      );
      _handlingInvalidSession = false;
    });
  }

  Future<void> _askForLocation() async {
    try {
      await LocationService.ensurePermission();
    } catch (_) {
      // Unsupported platform or no location plugin; SOS reports the problem later.
    }
  }

  void _finishOpening() {
    if (!mounted || _openingComplete) return;
    setState(() => _openingComplete = true);
    _askForLocation();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: AppLanguageService.I,
        builder: (context, _) => MaterialApp(
          title: 'Reddiph',
          navigatorKey: _navigatorKey,
          scaffoldMessengerKey: sosMessengerKey,
          debugShowCheckedModeBanner: false,
          theme: appTheme(),
          locale: AppLanguageService.I.locale,
          supportedLocales: const [Locale('en'), Locale('tl'), Locale('hil')],
          localizationsDelegates: [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: _openingComplete
              ? const PortalScreen()
              : OpeningScreen(onFinished: _finishOpening),
        ),
      );
}
