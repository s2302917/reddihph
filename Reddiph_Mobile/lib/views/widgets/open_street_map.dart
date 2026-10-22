import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:latlong2/latlong.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../core/theme.dart';
import '../../services/open_street_map_api.dart';

class OpenStreetMapMarker {
  final LatLng point;
  final String label;
  final Color color;
  final IconData icon;
  final VoidCallback? onTap;
  final bool showLabel;

  const OpenStreetMapMarker({
    required this.point,
    required this.label,
    this.color = kResponderBrand,
    this.icon = Icons.location_on,
    this.onTap,
    this.showLabel = false,
  });
}

/// Leaflet map backed by OpenStreetMap tiles and contributor attribution.
class OpenStreetMapView extends StatefulWidget {
  final double? height;
  final double radius;
  final LatLng center;
  final double zoom;
  final List<OpenStreetMapMarker> markers;
  final List<LatLng> route;
  final Color routeColor;
  final VoidCallback? onTap;
  final int recenterSignal;
  final double bearing;
  final bool followCenter;
  final bool rotationEnabled;
  final bool fitRouteOnUpdate;

  const OpenStreetMapView({
    super.key,
    this.height,
    this.radius = 6,
    this.center = OpenStreetMapApi.townCenter,
    this.zoom = 14,
    this.markers = const [],
    this.route = const [],
    this.routeColor = const Color(0xFFD9382B),
    this.onTap,
    this.recenterSignal = 0,
    this.bearing = 0,
    this.followCenter = false,
    this.rotationEnabled = false,
    this.fitRouteOnUpdate = false,
  });

  @override
  State<OpenStreetMapView> createState() => _OpenStreetMapViewState();
}

class _OpenStreetMapViewState extends State<OpenStreetMapView> {
  late final WebViewController _controller;
  bool _mapReady = false;

  @override
  void didUpdateWidget(covariant OpenStreetMapView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_mapReady && oldWidget.recenterSignal != widget.recenterSignal) {
      unawaited(_recenterMap());
    }
    if (_mapReady &&
        (oldWidget.center != widget.center ||
            oldWidget.zoom != widget.zoom ||
            oldWidget.routeColor != widget.routeColor ||
            oldWidget.bearing != widget.bearing ||
            oldWidget.followCenter != widget.followCenter ||
            oldWidget.rotationEnabled != widget.rotationEnabled ||
            oldWidget.fitRouteOnUpdate != widget.fitRouteOnUpdate ||
            !_sameRoute(oldWidget.route, widget.route) ||
            !_sameMarkers(oldWidget.markers, widget.markers))) {
      final routeChanged = !_sameRoute(oldWidget.route, widget.route);
      unawaited(_synchronizeMap(
        fitRoute: widget.fitRouteOnUpdate &&
            (routeChanged ||
                (!oldWidget.fitRouteOnUpdate && widget.route.length > 1)),
      ));
    }
  }

  @override
  void initState() {
    super.initState();
    _controller = WebViewController();
    unawaited(_initializeController());
  }

  Future<void> _initializeController() async {
    await _controller.setJavaScriptMode(JavaScriptMode.unrestricted);
    await _controller.setBackgroundColor(Colors.white);
    await _controller.setUserAgent(OpenStreetMapApi.userAgent);
    await _controller.addJavaScriptChannel(
      'MarkerSelection',
      onMessageReceived: (message) {
        final index = int.tryParse(message.message);
        if (index != null && index >= 0 && index < widget.markers.length) {
          widget.markers[index].onTap?.call();
        }
      },
    );
    await _controller.addJavaScriptChannel(
      'MapSelection',
      onMessageReceived: (_) => widget.onTap?.call(),
    );
    await _controller.setNavigationDelegate(
      NavigationDelegate(
        onPageFinished: (_) {
          if (!mounted) return;
          _mapReady = true;
          unawaited(_synchronizeMap(
            fitRoute: widget.fitRouteOnUpdate && widget.route.length > 1,
          ));
        },
        onWebResourceError: (error) {
          debugPrint(
            'Map resource failed: ${error.description} (${error.errorCode})',
          );
        },
      ),
    );
    await _controller.loadHtmlString(
      _document,
      baseUrl: 'https://www.openstreetmap.org/',
    );
  }

  Future<void> _synchronizeMap({bool fitRoute = false}) async {
    try {
      await _controller.runJavaScript(
        'window.updateReddiphMap('
        '$_centerJson, $_routeJson, $_markersJson, "$_routeColorHex", '
        '${widget.bearing}, ${widget.followCenter}, $fitRoute);',
      );
    } on PlatformException catch (error) {
      debugPrint('Map update failed: ${error.message}');
    }
  }

  Future<void> _recenterMap() async {
    try {
      await _controller.runJavaScript('window.recenterReddiphMap();');
    } on PlatformException catch (error) {
      debugPrint('Map recenter failed: ${error.message}');
    }
  }

  String get _centerJson =>
      jsonEncode([widget.center.latitude, widget.center.longitude]);

  String get _markersJson => jsonEncode([
        for (var i = 0; i < widget.markers.length; i++)
          {
            'lat': widget.markers[i].point.latitude,
            'lon': widget.markers[i].point.longitude,
            'label': widget.markers[i].label,
            'color':
                '#${widget.markers[i].color.toARGB32().toRadixString(16).substring(2)}',
            'symbol': _symbolFor(widget.markers[i].icon),
            'index': i,
            'showLabel': widget.markers[i].showLabel,
          },
      ]).replaceAll('<', r'\u003c').replaceAll('>', r'\u003e');

  String get _routeJson => jsonEncode([
        for (final point in widget.route) [point.latitude, point.longitude],
      ]);

  String get _routeColorHex =>
      '#${widget.routeColor.toARGB32().toRadixString(16).substring(2)}';

  String get _document {
    return '''
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <link rel="stylesheet" href="${OpenStreetMapApi.leafletCss}">
  <style>
    html, body, #map { width: 100%; height: 100%; margin: 0; padding: 0; }
    .leaflet-div-icon { background: transparent; border: 0; }
    .map-attribution {
      position: absolute; top: 5px; left: 50%; z-index: 1000;
      transform: translateX(-50%); padding: 3px 6px; border-radius: 3px;
      background: #ffffffdd; color: #333; font: 10px sans-serif;
      white-space: nowrap;
    }

    .map-attribution a { color: #1769aa; }
    .map-pin {
      display: flex; align-items: center; justify-content: center;
      width: 28px; height: 28px; border: 2px solid white; border-radius: 50%;
      color: white; background: var(--pin-color); font: 700 14px sans-serif;
      box-shadow: 0 1px 5px #0008;
    }
    .responder-label {
      border: 0; border-radius: 8px; background: #ffffff;
      color: #202124; box-shadow: 0 2px 8px #0003;
      font: 700 12px sans-serif; padding: 7px 10px;
    }
    .responder-label::before { display: none; }
  </style>
  <script src="${OpenStreetMapApi.leafletJs}"></script>
  ${widget.rotationEnabled ? '<script src="${OpenStreetMapApi.leafletRotateJs}"></script>' : ''}
</head>
<body>
  <div id="map"></div>
  <script>
    const map = L.map('map', {
      zoomControl: true,
      attributionControl: false,
      rotate: ${widget.rotationEnabled},
      bearing: ${widget.bearing}
    }).setView($_centerJson, ${widget.zoom});
    const canRotate = typeof map.setBearing === 'function';
    if (${widget.rotationEnabled} && !canRotate) {
      console.error('Map rotation support failed to load.');
    }
    map.on('click', () => MapSelection.postMessage('map'));
    const tiles = L.tileLayer('${OpenStreetMapApi.tileUrl}', {
      maxZoom: 19
    }).addTo(map);
    tiles.on('tileerror', (event) => console.error('Tile failed: ' + event.tile.src));
    const attribution = document.createElement('div');
    attribution.className = 'map-attribution';
    attribution.innerHTML = '&copy; <a href="${OpenStreetMapApi.attributionUrl}">OpenStreetMap contributors</a>';
    document.getElementById('map').appendChild(attribution);
    let routeLayer = null;
    const markerLayers = [];
    let latestCenter = $_centerJson;
    let latestBearing = ${widget.bearing};
    let followCenter = ${widget.followCenter};
    window.recenterReddiphMap = () => {
      map.setView(latestCenter, map.getZoom(), { animate: false });
      if (canRotate) map.setBearing(latestBearing);
    };
    window.updateReddiphMap = (
      center, route, points, routeColor, bearing, shouldFollowCenter, fitRoute
    ) => {
      latestCenter = center;
      latestBearing = bearing;
      followCenter = shouldFollowCenter;
      if (followCenter) {
        map.setView(center, map.getZoom(), { animate: false });
      }
      if (canRotate) map.setBearing(bearing);
      if (routeLayer) map.removeLayer(routeLayer);
      routeLayer = null;
      if (route.length > 1) {
        routeLayer = L.polyline(route, {
          color: routeColor,
          weight: 5,
          opacity: 0.9,
          lineCap: 'round',
          lineJoin: 'round'
        }).addTo(map);
        if (fitRoute && !followCenter) {
          map.fitBounds(L.latLngBounds(route), {
            padding: [48, 48],
            maxZoom: 16
          });
        }
      }
      markerLayers.forEach((marker) => map.removeLayer(marker));
      markerLayers.length = 0;
      points.forEach((point) => {
        const icon = L.divIcon({
          className: 'leaflet-div-icon',
          html: '<div class="map-pin" style="--pin-color:' +
            point.color + '">' + point.symbol + '</div>',
          iconSize: [28, 28],
          iconAnchor: [14, 14]
        });
        const marker = L.marker([point.lat, point.lon], { icon }).addTo(map);
        const label = document.createElement('span');
        label.textContent = point.label;
        if (point.showLabel) {
          marker.bindTooltip(label, {
            permanent: true,
            direction: 'top',
            offset: [0, -12],
            className: 'responder-label'
          }).openTooltip();
        } else {
          marker.bindTooltip(label);
        }
        marker.on('click', () => MarkerSelection.postMessage(String(point.index)));
        markerLayers.push(marker);
      });
    };
    window.updateReddiphMap(
      $_centerJson,
      $_routeJson,
      $_markersJson,
      '$_routeColorHex',
      ${widget.bearing},
      ${widget.followCenter},
      ${widget.route.length > 1}
    );
    setTimeout(() => map.invalidateSize(), 100);
  </script>
</body>
</html>
''';
  }

  bool _sameRoute(List<LatLng> first, List<LatLng> second) {
    if (first.length != second.length) return false;
    for (var i = 0; i < first.length; i++) {
      if (first[i] != second[i]) return false;
    }
    return true;
  }

  bool _sameMarkers(
    List<OpenStreetMapMarker> first,
    List<OpenStreetMapMarker> second,
  ) {
    if (first.length != second.length) return false;
    for (var i = 0; i < first.length; i++) {
      final a = first[i];
      final b = second[i];
      if (a.point != b.point ||
          a.label != b.label ||
          a.color != b.color ||
          a.icon != b.icon ||
          a.showLabel != b.showLabel) {
        return false;
      }
    }
    return true;
  }

  String _symbolFor(IconData icon) {
    if (icon == Icons.local_hospital) return 'H';
    if (icon == Icons.local_shipping) return 'A';
    if (icon == Icons.support_agent) return 'R';
    if (icon == Icons.person) return 'Y';
    if (icon == Icons.emergency) return '!';
    return '•';
  }

  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: BorderRadius.circular(widget.radius),
        child: SizedBox(
          height: widget.height,
          width: double.infinity,
          child: WebViewWidget(controller: _controller),
        ),
      );
}
