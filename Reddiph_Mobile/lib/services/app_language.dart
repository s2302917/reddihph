import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppLanguageService extends ChangeNotifier {
  AppLanguageService._();

  static final I = AppLanguageService._();

  static const String _key = 'app_language';
  static const List<String> supportedLanguages = [
    'English',
    'Tagalog',
    'Hiligaynon',
  ];

  static const Map<String, String> _languageCodes = {
    'English': 'en',
    'Tagalog': 'tl',
    'Hiligaynon': 'hil',
  };

  static const Map<String, String> _fallBackStrings = {
    'Profile': 'Profile',
    'Vehicle': 'Vehicle',
    'Vehicle type': 'Vehicle type',
    'Call sign': 'Call sign',
    'Plate': 'Plate',
    'Settings': 'Settings',
    'Log out': 'Log out',
    'This month': 'This month',
    'Dispatches handled': 'Dispatches handled',
  };

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_key) ?? 'English';
    if (supportedLanguages.contains(saved)) {
      _current = saved;
    } else {
      _current = 'English';
    }
    notifyListeners();
  }

  String _current = 'English';

  String get currentLanguage => _current;

  Locale get locale {
    final code = _languageCodes[_current] ?? 'en';
    return Locale(code);
  }

  String translate(String key, {String fallback = ''}) {
    final value = _translations[_current]?[key];
    if (value != null && value.isNotEmpty) {
      return value;
    }
    return fallback.isNotEmpty ? fallback : (_fallBackStrings[key] ?? key);
  }

  Future<void> setLanguage(String language) async {
    if (!supportedLanguages.contains(language)) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, language);
    _current = language;
    notifyListeners();
  }

  static const Map<String, Map<String, String>> _translations = {
    'English': {
      'Profile': 'Profile',
      'Vehicle': 'Vehicle',
      'Vehicle type': 'Vehicle type',
      'Call sign': 'Call sign',
      'Plate': 'Plate',
      'Settings': 'Settings',
      'Log out': 'Log out',
      'This month': 'This month',
      'Dispatches handled': 'Dispatches handled',
      'Duty schedule': 'Duty schedule',
      'Language': 'Language',
    },
    'Tagalog': {
      'Profile': 'Profile',
      'Vehicle': 'Sasakyan',
      'Vehicle type': 'Uri ng sasakyan',
      'Call sign': 'Pangalan ng tawag',
      'Plate': 'Plaka',
      'Settings': 'Mga Setting',
      'Log out': 'Mag-log out',
      'This month': 'Ngayong buwan',
      'Dispatches handled': 'Mga naihatid',
      'Duty schedule': 'Iskedyul ng duty',
      'Language': 'Wika',
    },
    'Hiligaynon': {
      'Profile': 'Profile',
      'Vehicle': 'Kotse',
      'Vehicle type': 'Tipo sang kotse',
      'Call sign': 'Pangalan sang tawag',
      'Plate': 'Plaka',
      'Settings': 'Mga setting',
      'Log out': 'Mag-logout',
      'This month': 'Ini nga bulan',
      'Dispatches handled': 'Mga na-handle',
      'Duty schedule': 'Eskedyul sang duty',
      'Language': 'Pinulongan',
    },
  };
}
