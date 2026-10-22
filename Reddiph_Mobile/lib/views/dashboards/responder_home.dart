import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';
import '../../controllers/responder_controller.dart';
import '../../core/nav.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/responder_dashboard.dart';
import '../screens/case_detail_screen.dart';
import '../screens/responder_incident_route_screen.dart';
import '../widgets/common.dart';
import '../widgets/open_street_map.dart';
import '../widgets/philippine_clock.dart';
import '../widgets/responder_widgets.dart';

class ResponderHome extends StatefulWidget {
  const ResponderHome({super.key});

  @override
  State<ResponderHome> createState() => _ResponderHomeState();
}

class _ResponderHomeState extends State<ResponderHome> {
  final _controller = ResponderController();

  @override
  void initState() {
    super.initState();
    _controller.start();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: _controller,
        builder: (context, _) {
          final data = _controller.dashboard;
          if (data == null) {
            return _controller.error == null
                ? const Center(child: CircularProgressIndicator())
                : _LoadError(
                    message: _controller.error!,
                    onRetry: _controller.load,
                  );
          }

          return ListView(
              padding: const EdgeInsets.only(bottom: 90),
              children: [
                _Header(data),
                const _OnDutyCard(),
                const Section('Active case'),
                _ActiveCase(
                  incident: data.activeCase,
                  responder: _controller.position,
                  routeEtaMinutes: _controller.activeRoute?.etaMinutes,
                  routeError: _controller.routeError,
                  onOpenRouting: (incident) async {
                    await _controller.load();
                    if (!context.mounted) return;
                    final current =
                        _controller.dashboard?.activeCase ?? incident;
                    Nav.push(
                      context,
                      ResponderIncidentRouteScreen(
                        incidentId: current.id,
                        incidentType: current.type,
                        location: current.location,
                        incident: current,
                      ),
                    );
                  },
                ),
                if (_controller.showUpcomingCases &&
                    data.queue.items.isNotEmpty) ...[
                  const Section('Upcoming cases'),
                  _UpcomingCases(
                    data.queue.items,
                    onFalseReportClosed: _controller.load,
                  ),
                ],
                const Section('Incoming SOS'),
                _IncomingList(controller: _controller, page: data.queue),
              ]);
        },
      );
}

class _Header extends StatelessWidget {
  final ResponderDashboard data;
  const _Header(this.data);

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
        child: Row(children: [
          Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              PhilippineClock(
                builder: (_, time) => Text(PhilippineTime.greeting(time),
                    style: const TextStyle(fontSize: 11)),
              ),
              Text(data.fullName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 18, fontWeight: FontWeight.bold)),
              Text(data.roleLabel,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 9, color: Colors.black54)),
            ]),
          ),
          const SizedBox(width: 12),
          CircleAvatar(
            radius: 18,
            backgroundColor: kResponderBrown,
            child: Text(data.initials,
                style: const TextStyle(color: Colors.white, fontSize: 12)),
          ),
        ]),
      );
}

class _OnDutyCard extends StatelessWidget {
  const _OnDutyCard();

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          gradient: roleButtonGradient(kResponderBrand),
        ),
        child: PhilippineClock(
          builder: (_, time) => Row(children: [
            Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('On Duty',
                        style: TextStyle(fontSize: 9, color: Colors.white70)),
                    const SizedBox(height: 14),
                    Text(time.day.toString().padLeft(2, '0'),
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white)),
                    Text(PhilippineTime.monthYearDay(time),
                        style:
                            const TextStyle(fontSize: 9, color: Colors.white)),
                    Text(PhilippineTime.clock(time),
                        style: const TextStyle(
                            fontSize: 9, color: Colors.white70)),
                  ]),
            ),
            const Icon(Icons.shield, size: 34, color: Colors.white70),
          ]),
        ),
      );
}

class _ActiveCase extends StatelessWidget {
  final ResponderIncident? incident;
  final LatLng? responder;
  final int? routeEtaMinutes;
  final String? routeError;
  final Future<void> Function(ResponderIncident incident) onOpenRouting;
  const _ActiveCase({
    required this.incident,
    required this.responder,
    required this.routeEtaMinutes,
    required this.routeError,
    required this.onOpenRouting,
  });

  @override
  Widget build(BuildContext context) {
    final current = incident;
    if (current == null) {
      return const Box(Text('No active case assigned to you.',
          style: TextStyle(fontSize: 11, color: Colors.black54)));
    }

    final point = current.point;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
          color: kResponderBrown, borderRadius: BorderRadius.circular(10)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(current.type,
            style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Colors.white)),
        Text(current.location,
            style: const TextStyle(fontSize: 9, color: Colors.white70)),
        if (current.sentToHospital && routeEtaMinutes != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              'Hospital ETA: $routeEtaMinutes min',
              style: const TextStyle(fontSize: 10, color: Colors.white),
            ),
          ),
        if (routeError != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              routeError!,
              style: const TextStyle(fontSize: 9, color: Colors.white70),
            ),
          ),
        if (point != null) ...[
          const SizedBox(height: 10),
          OpenStreetMapView(
            // A new key rebuilds the map when the case or the responder's fix changes.
            key: ValueKey('${current.id}-${responder != null}'),
            height: 90,
            center: point,
            markers: [
              OpenStreetMapMarker(
                point: point,
                label: 'Active case',
                icon: Icons.emergency,
              ),
              if (responder != null)
                OpenStreetMapMarker(
                  point: responder!,
                  label: 'You',
                  icon: Icons.support_agent,
                ),
            ],
          ),
        ],
        const SizedBox(height: 10),
        Btn('OPEN ROUTING', kResponderOrange,
            onTap: () => onOpenRouting(current)),
      ]),
    );
  }
}

class _UpcomingCases extends StatelessWidget {
  final List<ResponderIncident> incidents;
  final Future<void> Function() onFalseReportClosed;
  const _UpcomingCases(
    this.incidents, {
    required this.onFalseReportClosed,
  });

  @override
  Widget build(BuildContext context) => Box(Column(children: [
        const Text(
          'Your hospital ETA is 5 minutes or less. These cases are next in your area.',
          style: TextStyle(fontSize: 10, color: Colors.black54),
        ),
        for (int index = 0; index < incidents.length; index++) ...[
          if (index > 0) const Divider(height: 1),
          _IncomingRow(
            incidents[index],
            onFalseReportClosed: onFalseReportClosed,
          ),
        ],
      ]));
}

class _IncomingList extends StatelessWidget {
  final ResponderController controller;
  final IncidentPage page;
  const _IncomingList({required this.controller, required this.page});

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return const Box(Text('No incoming SOS right now.',
          style: TextStyle(fontSize: 11, color: Colors.black54)));
    }

    return Box(Column(children: [
      for (int i = 0; i < page.items.length; i++) ...[
        if (i > 0) const Divider(height: 1),
        _IncomingRow(
          page.items[i],
          onFalseReportClosed: controller.load,
        ),
      ],
      if (page.pages > 1) ...[
        const Divider(height: 1),
        IncidentPager(
          page: page,
          onPrevious: controller.previous,
          onNext: controller.next,
        ),
      ],
    ]));
  }
}

class _IncomingRow extends StatelessWidget {
  final ResponderIncident incident;
  final Future<void> Function() onFalseReportClosed;
  const _IncomingRow(
    this.incident, {
    required this.onFalseReportClosed,
  });

  Future<void> _openCase(BuildContext context) async {
    final closed = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => CaseDetailScreen(item: incident.toQueueItem()),
      ),
    );
    if (closed == true) await onFalseReportClosed();
  }

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: () => _openCase(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(children: [
            const Icon(Icons.circle, size: 8, color: kResponderBrown),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(incident.type,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w600)),
                    Text(incident.location,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 9, color: Colors.black54)),
                  ]),
            ),
            const SizedBox(width: 8),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text(incident.distanceLabel,
                  style: const TextStyle(
                      fontSize: 11, fontWeight: FontWeight.w600)),
              Text(incident.agoLabel,
                  style: const TextStyle(fontSize: 9, color: Colors.black54)),
            ]),
          ]),
        ),
      );
}

class _LoadError extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _LoadError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(message,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 11, color: Colors.black54)),
            TextButton(onPressed: onRetry, child: const Text('Retry')),
          ]),
        ),
      );
}
