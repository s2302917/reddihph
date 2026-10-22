import 'package:latlong2/latlong.dart';

class RouteInstruction {
  const RouteInstruction({
    required this.text,
    required this.distanceMeters,
  });

  final String text;
  final int distanceMeters;

  factory RouteInstruction.fromJson(Map<String, dynamic> json) {
    final text = json['text'];
    final distance = json['distance_m'];
    if (text is! String || distance is! num) {
      throw const FormatException('Invalid driving instruction.');
    }
    return RouteInstruction(text: text, distanceMeters: distance.toInt());
  }

  String get distanceLabel => distanceMeters >= 1000
      ? '${(distanceMeters / 1000).toStringAsFixed(1)} km'
      : '$distanceMeters m';
}

class RoadSegment {
  const RoadSegment({required this.from, required this.to});

  final LatLng from;
  final LatLng to;

  factory RoadSegment.fromJson(Map<String, dynamic> json) {
    final from = json['from'];
    final to = json['to'];
    if (from is! Map<String, dynamic> ||
        to is! Map<String, dynamic> ||
        from['latitude'] is! num ||
        from['longitude'] is! num ||
        to['latitude'] is! num ||
        to['longitude'] is! num) {
      throw const FormatException('Invalid nearby road geometry.');
    }
    return RoadSegment(
      from: LatLng(
        (from['latitude'] as num).toDouble(),
        (from['longitude'] as num).toDouble(),
      ),
      to: LatLng(
        (to['latitude'] as num).toDouble(),
        (to['longitude'] as num).toDouble(),
      ),
    );
  }
}

class DrivingRoute {
  const DrivingRoute({
    required this.destinationName,
    required this.transportAssigned,
    required this.distanceMeters,
    required this.etaMinutes,
    required this.geometry,
    required this.mapRoads,
    required this.instructions,
    this.trafficAware = false,
  });

  final String destinationName;
  String get hospitalName => destinationName;
  final bool transportAssigned;
  final int distanceMeters;
  final int etaMinutes;
  final List<LatLng> geometry;
  final List<RoadSegment> mapRoads;
  final List<RouteInstruction> instructions;
  final bool trafficAware;

  factory DrivingRoute.fromJson(Map<String, dynamic> json) {
    final destination = json['destination'] ?? json['hospital'];
    final route = json['route'];
    if (destination is! Map<String, dynamic> ||
        destination['name'] is! String ||
        (json['transport_assigned'] != null &&
            json['transport_assigned'] is! bool) ||
        route is! Map<String, dynamic> ||
        route['distance_m'] is! num ||
        route['eta_minutes'] is! num ||
        route['geometry'] is! List ||
        route['map_roads'] is! List ||
        route['instructions'] is! List ||
        (route['traffic_aware'] != null && route['traffic_aware'] is! bool)) {
      throw const FormatException('Invalid hospital route.');
    }

    final points = <LatLng>[];
    for (final point in route['geometry'] as List) {
      if (point is! Map<String, dynamic> ||
          point['latitude'] is! num ||
          point['longitude'] is! num) {
        throw const FormatException('Invalid hospital route geometry.');
      }
      points.add(LatLng(
        (point['latitude'] as num).toDouble(),
        (point['longitude'] as num).toDouble(),
      ));
    }
    if (points.length < 2) {
      throw const FormatException('Hospital route geometry is incomplete.');
    }

    final mapRoads = <RoadSegment>[];
    for (final road in route['map_roads'] as List) {
      if (road is! Map<String, dynamic>) {
        throw const FormatException('Invalid nearby road geometry.');
      }
      mapRoads.add(RoadSegment.fromJson(road));
    }

    final instructions = <RouteInstruction>[];
    for (final instruction in route['instructions'] as List) {
      if (instruction is! Map<String, dynamic>) {
        throw const FormatException('Invalid hospital route directions.');
      }
      instructions.add(RouteInstruction.fromJson(instruction));
    }
    if (instructions.isEmpty) {
      throw const FormatException('Hospital route directions are empty.');
    }

    return DrivingRoute(
      destinationName: destination['name'] as String,
      transportAssigned: json['transport_assigned'] as bool? ?? false,
      distanceMeters: (route['distance_m'] as num).toInt(),
      etaMinutes: (route['eta_minutes'] as num).toInt(),
      geometry: points,
      mapRoads: mapRoads,
      instructions: instructions,
      trafficAware: route['traffic_aware'] == true,
    );
  }

  String get distanceLabel => distanceMeters >= 1000
      ? '${(distanceMeters / 1000).toStringAsFixed(1)} km'
      : '$distanceMeters m';
}
