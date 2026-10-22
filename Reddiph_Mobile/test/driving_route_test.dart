import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:latlong2/latlong.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  test('requests a responder-to-incident route from the database destination',
      () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'destination': {
              'name': 'Barangay Hall',
              'latitude': 10.42,
              'longitude': 122.92,
            },
            'transport_assigned': false,
            'route': {
              'distance_m': 1200,
              'eta_minutes': 4,
              'geometry': [
                {'latitude': 10.4, 'longitude': 122.9},
                {'latitude': 10.42, 'longitude': 122.92},
              ],
              'map_roads': [],
              'instructions': [
                {'text': 'Continue to the incident', 'distance_m': 1200},
              ],
            },
          }),
          200,
        );
      }),
    );

    final route = await client.responderIncidentDirections(
      token: 'responder-token',
      incidentId: 41,
      latitude: 10.4,
      longitude: 122.9,
    );

    expect(request.url.path, endsWith('api/incidents/directions.php'));
    expect(jsonDecode(request.body), {
      'token': 'responder-token',
      'incident_id': 41,
      'latitude': 10.4,
      'longitude': 122.9,
      'destination': 'incident',
    });
    expect(route.destinationName, 'Barangay Hall');
    expect(route.hospitalName, 'Barangay Hall');
    expect(route.transportAssigned, isFalse);
  });

  test('requests the resident route without sending client coordinates',
      () async {
    late http.Request request;
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((captured) async {
        request = captured;
        return http.Response(
          jsonEncode({
            'success': true,
            'destination': {
              'name': 'Emergency location',
              'latitude': 10.42,
              'longitude': 122.92,
            },
            'transport_assigned': false,
            'route': {
              'distance_m': 1200,
              'eta_minutes': 4,
              'geometry': [
                {'latitude': 10.4, 'longitude': 122.9},
                {'latitude': 10.42, 'longitude': 122.92},
              ],
              'map_roads': [],
              'instructions': [
                {'text': 'Continue to the incident', 'distance_m': 1200},
              ],
            },
          }),
          200,
        );
      }),
    );

    final route = await client.responderStatusDirections(
      token: 'resident-token',
      incidentId: 41,
    );

    expect(jsonDecode(request.body), {
      'token': 'resident-token',
      'incident_id': 41,
      'destination': 'responder_incident',
    });
    expect(route.destinationName, 'Emergency location');
    expect(route.geometry, hasLength(2));
  });

  test('loads private directions and posts incident note updates', () async {
    final requests = <http.Request>[];
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        requests.add(request);
        if (request.url.path.endsWith('api/incidents/directions.php')) {
          return http.Response(
            jsonEncode({
              'success': true,
              'hospital': {'name': 'District Hospital'},
              'transport_assigned': true,
              'route': {
                'distance_m': 2500,
                'eta_minutes': 7,
                'traffic_aware': true,
                'geometry': [
                  {'latitude': 10.4, 'longitude': 122.9},
                  {'latitude': 10.42, 'longitude': 122.92},
                ],
                'map_roads': [
                  {
                    'from': {'latitude': 10.4, 'longitude': 122.9},
                    'to': {'latitude': 10.41, 'longitude': 122.91},
                  },
                ],
                'instructions': [
                  {'text': 'Turn left onto Main Road', 'distance_m': 400},
                  {'text': 'Arrive at the hospital', 'distance_m': 0},
                ],
              },
            }),
            200,
          );
        }
        return http.Response(
          jsonEncode(
              {'success': true, 'message': 'Hospital staff were notified.'}),
          201,
        );
      }),
    );

    final route = await client.incidentDirections(
      token: 'signed-token',
      incidentId: 27,
      latitude: 10.4,
      longitude: 122.9,
    );
    await client.updateIncidentNotes(
      token: 'signed-token',
      incidentId: 27,
      notes: 'Patient condition has changed.',
    );

    expect(route.hospitalName, 'District Hospital');
    expect(route.transportAssigned, isTrue);
    expect(route.distanceMeters, 2500);
    expect(route.etaMinutes, 7);
    expect(route.trafficAware, isTrue);
    expect(route.geometry, [
      const LatLng(10.4, 122.9),
      const LatLng(10.42, 122.92),
    ]);
    expect(route.mapRoads, hasLength(1));
    expect(route.mapRoads.first.from, const LatLng(10.4, 122.9));
    expect(route.instructions.first.text, 'Turn left onto Main Road');
    expect(requests, hasLength(2));
    expect(
      jsonDecode(requests.first.body),
      {
        'token': 'signed-token',
        'incident_id': 27,
        'latitude': 10.4,
        'longitude': 122.9,
      },
    );
    expect(
      requests.last.url.path,
      endsWith('api/incidents/update_notes.php'),
    );
    expect(jsonDecode(requests.last.body), {
      'token': 'signed-token',
      'incident_id': 27,
      'notes': 'Patient condition has changed.',
    });
  });
}
