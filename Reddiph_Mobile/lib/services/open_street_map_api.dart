import 'package:latlong2/latlong.dart';

/// OpenStreetMap tile API configuration and demo locations.
///
/// Keep tile requests on the public HTTPS endpoint and identify this app with
/// its Android application ID. Coordinates are demo locations near Alfonso,
/// Cavite; replace them with verified GPS coordinates when live case data is
/// available.
class OpenStreetMapApi {
  OpenStreetMapApi._();

  static const leafletCss =
      'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
  static const leafletJs =
      'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
  static const leafletRotateJs =
      'https://cdn.jsdelivr.net/npm/leaflet-rotate@0.2.8/dist/leaflet-rotate.js';
  static const tileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  static const userAgent = 'Reddiph/1.0.0 (com.example.reddiph)';
  static const attribution = '© OpenStreetMap contributors';
  static const attributionUrl = 'https://www.openstreetmap.org/copyright';

  static const townCenter = LatLng(14.12, 120.85);
  static const hospital = LatLng(14.123, 120.853);
  static const resident = LatLng(14.118, 120.847);
  static const incidentOne = LatLng(14.116, 120.855);
  static const incidentTwo = LatLng(14.125, 120.844);
  static const incidentThree = LatLng(14.113, 120.842);
}
