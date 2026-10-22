import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:latlong2/latlong.dart';
import 'package:reddiph/controllers/responder_controller.dart';
import 'package:reddiph/models/responder_dashboard.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  test('responder dashboard loads without waiting for location', () async {
    final client = _DashboardClient();
    final position = Completer<LatLng?>();
    final controller = ResponderController(
      client: client,
      positionLoader: () => position.future,
    );
    final loaded = Completer<void>();
    controller.addListener(() {
      if (controller.dashboard != null && !loaded.isCompleted) {
        loaded.complete();
      }
    });
    addTearDown(controller.dispose);

    controller.start();
    await loaded.future;

    expect(client.requestCount, 1);
    expect(client.lastLatitude, isNull);
    expect(client.lastLongitude, isNull);
    expect(controller.dashboard?.fullName, 'Test Responder');

    position.complete(const LatLng(10, 122));
  });
}

class _DashboardClient extends MobileApiClient {
  int requestCount = 0;
  double? lastLatitude;
  double? lastLongitude;

  @override
  Future<ResponderDashboard> responderDashboard({
    required String token,
    int page = 1,
    double? latitude,
    double? longitude,
  }) async {
    requestCount++;
    lastLatitude = latitude;
    lastLongitude = longitude;
    return const ResponderDashboard(
      fullName: 'Test Responder',
      queue: IncidentPage(),
    );
  }
}
