import 'package:flutter/material.dart';
import '../../controllers/responder_queue_controller.dart';
import '../../core/nav.dart';
import '../../models/responder_dashboard.dart';
import '../../services/mobile_api_client.dart';
import '../screens/case_detail_screen.dart';
import '../screens/report_form_screen.dart';
import '../widgets/common.dart';
import '../widgets/responder_widgets.dart';

/// "Incoming" tab: the incidents of the responder's barangay, filterable by status,
/// 10 per page, refreshed every second from the incidents table.
class ResponderQueueTab extends StatefulWidget {
  const ResponderQueueTab({super.key});

  @override
  State<ResponderQueueTab> createState() => _ResponderQueueTabState();
}

class _ResponderQueueTabState extends State<ResponderQueueTab> {
  final _controller = ResponderQueueController();

  /// The incident the responder tapped; it stays selected across the live refresh.
  int? _selectedId;

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

  Future<void> _openCase(ResponderIncident incident) async {
    try {
      final activeCase = await _controller.refreshActiveCase();
      if (!mounted) return;
      final closed = await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => CaseDetailScreen(
            item: incident.toQueueItem(),
            activeIncident: activeCase?.id == incident.id ? activeCase : null,
          ),
        ),
      );
      if (closed == true) await _controller.load();
    } on MobileApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: _controller,
        builder: (context, _) {
          final data = _controller.queue;
          if (data == null) {
            return Center(
              child: _controller.error == null
                  ? const CircularProgressIndicator()
                  : Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Text(_controller.error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                                fontSize: 11, color: Colors.black54)),
                        TextButton(
                            onPressed: _controller.load,
                            child: const Text('Retry')),
                      ]),
                    ),
            );
          }

          return ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 90),
              children: [
                _Title(initials: data.initials),
                const SizedBox(height: 12),
                _Filters(controller: _controller, counts: data.counts),
                const SizedBox(height: 14),
                if (data.page.items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 40),
                    child: Center(
                        child: Text('No cases in this group',
                            style: TextStyle(
                                fontSize: 11, color: Colors.black54))),
                  ),
                for (final incident in data.page.items)
                  _QueueCard(
                    incident,
                    selected: incident.id == _selectedId,
                    onSelect: () => setState(() => _selectedId =
                        incident.id == _selectedId ? null : incident.id),
                    onViewCase: () => _openCase(incident),
                  ),
                if (data.page.pages > 1)
                  IncidentPager(
                    page: data.page,
                    onPrevious: _controller.previous,
                    onNext: _controller.next,
                  ),
              ]);
        },
      );
}

class _Title extends StatelessWidget {
  final String initials;
  const _Title({required this.initials});

  @override
  Widget build(BuildContext context) => Row(children: [
        const Expanded(
            child: Text('Incoming SOS',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
        IconButton(
          tooltip: 'File a report',
          visualDensity: VisualDensity.compact,
          icon: const Icon(Icons.add_circle_outline, size: 20),
          onPressed: () => Nav.push(context, const ReportFormScreen()),
        ),
        CircleAvatar(
            radius: 18,
            backgroundColor: kResponderBrown,
            child: Text(initials,
                style: const TextStyle(color: Colors.white, fontSize: 12))),
      ]);
}

class _Filters extends StatelessWidget {
  final ResponderQueueController controller;
  final Map<String, int> counts;
  const _Filters({required this.controller, required this.counts});

  static String _label(String filter) =>
      filter[0].toUpperCase() + filter.substring(1);

  @override
  Widget build(BuildContext context) =>
      Wrap(spacing: 6, runSpacing: 6, children: [
        for (final filter in ResponderQueueController.filters)
          GestureDetector(
            onTap: () => controller.select(filter),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: controller.filter == filter
                    ? kResponderBrown
                    : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                    color: controller.filter == filter
                        ? kResponderBrown
                        : Colors.black26),
              ),
              child: Text('${_label(filter)} ${counts[filter] ?? 0}',
                  style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.w600,
                      color: controller.filter == filter
                          ? Colors.white
                          : Colors.black87)),
            ),
          ),
      ]);
}

class _QueueCard extends StatelessWidget {
  final ResponderIncident incident;
  final bool selected;
  final VoidCallback onSelect;
  final VoidCallback onViewCase;
  const _QueueCard(this.incident,
      {required this.selected,
      required this.onSelect,
      required this.onViewCase});

  @override
  Widget build(BuildContext context) {
    final sev = severityColors(incident.severity);

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onSelect,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
              color: selected ? const Color(0xFFD9382B) : Colors.black12,
              width: selected ? 1.2 : 1),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Pill(incident.severity, sev.bg, sev.fg),
          const SizedBox(height: 6),
          Row(children: [
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Text(incident.type,
                      style: const TextStyle(
                          fontSize: 11, fontWeight: FontWeight.w700)),
                  Text('${incident.location} · ${incident.agoLabel}',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54)),
                ])),
            Text(incident.distanceLabel,
                style:
                    const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ]),
          if (selected) ...[
            const SizedBox(height: 8),
            SizedBox(
                height: 30,
                child: Btn('VIEW CASE', kResponderBrown,
                    fontSize: 9, onTap: onViewCase)),
          ],
        ]),
      ),
    );
  }
}
