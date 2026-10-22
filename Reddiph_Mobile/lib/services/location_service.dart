import 'dart:async';

import 'package:geolocator/geolocator.dart';

/// Thrown when the device location cannot be used for an SOS.
class LocationUnavailableException implements Exception {
  const LocationUnavailableException(this.message);

  final String message;

  @override
  String toString() => message;
}

class LocationService {
  const LocationService._();

  /// Asks for location permission when it is not granted yet. Returns true when allowed.
  static Future<bool> ensurePermission() async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    return permission == LocationPermission.whileInUse ||
        permission == LocationPermission.always;
  }

  /// Returns the device's current position or throws [LocationUnavailableException].
  static Future<Position> currentPosition() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationUnavailableException(
        'Turn on your device location (GPS) to send your SOS.',
      );
    }
    if (!await ensurePermission()) {
      throw const LocationUnavailableException(
        'Allow location access in your device settings to send your SOS.',
      );
    }
    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } on TimeoutException {
      // No fresh GPS fix (e.g. indoors): the last known position is better than no SOS.
      final lastKnown = await Geolocator.getLastKnownPosition();
      if (lastKnown != null) return lastKnown;
      throw const LocationUnavailableException(
        'Could not get your location. Move near a window or outdoors and try again.',
      );
    }
  }

  /// Streams updated GPS fixes for an active navigation session.
  static Stream<Position> positionStream() async* {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationUnavailableException(
        'Turn on your device location (GPS) to continue navigation.',
      );
    }
    if (!await ensurePermission()) {
      throw const LocationUnavailableException(
        'Allow location access in your device settings to continue navigation.',
      );
    }
    yield* Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 20,
      ),
    );
  }
}
