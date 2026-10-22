import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../../models/incident_history.dart';
import '../../services/mobile_api_client.dart';

enum _HistoryFilter { all, active, closed }

class ResidentReportHistoryScreen extends StatefulWidget {
  const ResidentReportHistoryScreen({
    required this.token,
    this.apiClient,
    super.key,
  });

  final String? token;
  final MobileApiClient? apiClient;

  @override
  State<ResidentReportHistoryScreen> createState() =>
      _ResidentReportHistoryScreenState();
}

class _ResidentReportHistoryScreenState
    extends State<ResidentReportHistoryScreen> {
  late final MobileApiClient _api = widget.apiClient ?? MobileApiClient.I;
  List<IncidentHistoryEntry> _entries = const [];
  IncidentHistoryEntry? _selected;
  _HistoryFilter _filter = _HistoryFilter.all;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = widget.token;
    if (token == null || token.isEmpty) {
      setState(() {
        _loading = false;
        _error = 'Sign in to view your report history.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final entries = await _api.incidentHistory(token: token);
      if (!mounted) return;
      setState(() {
        _entries = entries;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    }
  }

  List<IncidentHistoryEntry> get _filteredEntries => _entries.where((entry) {
        switch (_filter) {
          case _HistoryFilter.active:
            return !entry.isClosed;
          case _HistoryFilter.closed:
            return entry.isClosed;
          case _HistoryFilter.all:
            return true;
        }
      }).toList(growable: false);

  @override
  Widget build(BuildContext context) {
    final selected = _selected;
    return selected == null ? _historyView() : _detailView(selected);
  }

  Widget _historyView() {
    final entries = _filteredEntries;
    if (_loading && _entries.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: kResidentBrand),
      );
    }
    if (_error != null && _entries.isEmpty) {
      return _messageView(
        icon: Icons.cloud_off_outlined,
        message: _error!,
        actionLabel: 'Try again',
        onAction: _load,
      );
    }

    return RefreshIndicator(
      color: kResidentBrand,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(22, 24, 22, 28),
        children: [
          const Text(
            'Report history',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 5),
          const Text(
            'Every emergency you have reported',
            style: TextStyle(color: Colors.black38, fontSize: 12),
          ),
          const SizedBox(height: 22),
          _summaryCard(),
          const SizedBox(height: 10),
          _filterRow(),
          if (_loading)
            const Padding(
              padding: EdgeInsets.only(top: 5),
              child: LinearProgressIndicator(
                minHeight: 2,
                color: kResidentBrand,
                backgroundColor: Colors.transparent,
              ),
            ),
          if (_error != null)
            _inlineError(_error!)
          else if (entries.isEmpty)
            _emptyResults()
          else
            ..._groupedCards(entries),
        ],
      ),
    );
  }

  Widget _summaryCard() {
    final activeCount = _entries.where((entry) => !entry.isClosed).length;
    final closedCount = _entries.where((entry) => entry.isClosed).length;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 6),
      decoration: BoxDecoration(
        color: const Color(0xFFADB5BA),
        borderRadius: BorderRadius.circular(7),
      ),
      child: Row(
        children: [
          _summaryValue('Total reports', _entries.length),
          _summaryDivider(),
          _summaryValue('Active', activeCount),
          _summaryDivider(),
          _summaryValue('Closed', closedCount),
        ],
      ),
    );
  }

  Widget _summaryDivider() => Container(
        width: 1,
        height: 30,
        color: Colors.white.withValues(alpha: 0.7),
      );

  Widget _summaryValue(String label, int value) => Expanded(
        child: Column(
          children: [
            Text(
              '$value',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 24,
                height: 1,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 9, color: Colors.white),
            ),
          ],
        ),
      );

  Widget _filterRow() => Wrap(
        spacing: 5,
        children: [
          _filterButton(_HistoryFilter.all, 'All'),
          _filterButton(_HistoryFilter.active, 'Active'),
          _filterButton(_HistoryFilter.closed, 'Closed'),
        ],
      );

  Widget _filterButton(_HistoryFilter filter, String label) {
    final selected = _filter == filter;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      onSelected: (_) => setState(() => _filter = filter),
      labelStyle: TextStyle(
        color: selected ? Colors.white : Colors.black87,
        fontSize: 9,
        fontWeight: FontWeight.w600,
      ),
      selectedColor: kResidentBrand,
      backgroundColor: Colors.white,
      side: const BorderSide(color: Color(0xFFE0E1E2)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 0),
    );
  }

  List<Widget> _groupedCards(List<IncidentHistoryEntry> entries) {
    final groups = <String, List<IncidentHistoryEntry>>{};
    for (final entry in entries) {
      final date = entry.incident.reportedAt;
      final key = date == null ? 'Date unavailable' : _monthLabel(date);
      groups.putIfAbsent(key, () => []).add(entry);
    }
    return [
      for (final group in groups.entries) ...[
        Padding(
          padding: const EdgeInsets.fromLTRB(2, 12, 2, 8),
          child: Text(
            group.key.toUpperCase(),
            style: const TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w700,
              color: Colors.black45,
            ),
          ),
        ),
        for (final entry in group.value) _reportCard(entry),
      ],
    ];
  }

  Widget _reportCard(IncidentHistoryEntry entry) {
    final incident = entry.incident;
    final closed = entry.isClosed;
    final date = incident.reportedAt;
    final subtitle = [
      'Case #RS-${incident.incidentId}',
      if (date != null) _shortDate(date),
      _locationLabel(incident.barangay, incident.location),
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(5),
        child: InkWell(
          borderRadius: BorderRadius.circular(5),
          onTap: () => setState(() => _selected = entry),
          child: Container(
            constraints: const BoxConstraints(minHeight: 53),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFE2E2E2)),
              borderRadius: BorderRadius.circular(5),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.circle_outlined,
                  size: 19,
                  color: Color(0xFFE1E1E1),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${incident.type} — ${entry.patientLabel}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 8,
                          color: Colors.black38,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                if (closed)
                  _statusPill('Closed', closed: true)
                else
                  _severityLabel(incident.status),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _detailView(IncidentHistoryEntry entry) {
    final incident = entry.incident;
    final falseReport = entry.wasFalseReport;
    final arrived = entry.arrivedAtHospital;
    final closed = entry.isClosed;
    final subtitle = [
      'Case #RS-${incident.incidentId}',
      if (incident.reportedAt != null) _shortDate(incident.reportedAt!),
      _locationLabel(incident.barangay, incident.location),
    ].join(' · ');
    final responderTeam = [
      incident.responderName,
      incident.transportName,
    ].whereType<String>().where((part) => part.trim().isNotEmpty).join(' · ');
    final responseMinutes = _responseMinutes(entry);
    final falseTimeline = <(String, DateTime?, bool)>[
      ('Report sent', incident.reportedAt, incident.reportedAt != null),
      (
        'Responder on the way',
        incident.responderAcknowledgedAt ?? incident.responderAssignedAt,
        incident.responderAcknowledgedAt != null ||
            incident.responderAssignedAt != null,
      ),
      (
        'Responder checking on you',
        incident.responderArrivedAt,
        incident.responderArrivedAt != null
      ),
      (
        'Help confirmed',
        incident.patientPickedUpAt,
        incident.patientPickedUpAt != null
      ),
      (
        'On the way to the hospital',
        incident.hospitalSentAt,
        incident.hospitalSentAt != null
      ),
      (
        'Arrived',
        incident.hospitalArrivedAt,
        incident.hospitalArrivedAt != null
      ),
    ];
    final arrivalTimeline = <(String, DateTime?, bool)>[
      ('Reported', incident.reportedAt, incident.reportedAt != null),
      (
        'Dispatched',
        incident.responderAcknowledgedAt ?? incident.responderAssignedAt,
        incident.responderAcknowledgedAt != null ||
            incident.responderAssignedAt != null,
      ),
      (
        'Responder arrived on scene',
        incident.responderArrivedAt,
        incident.responderArrivedAt != null,
      ),
      (
        'Arrived at hospital',
        incident.hospitalArrivedAt,
        incident.hospitalArrivedAt != null,
      ),
    ];
    final timeline = falseReport
        ? falseTimeline
        : arrived
            ? arrivalTimeline
            : <(String, DateTime?, bool)>[
                (
                  'Report sent',
                  incident.reportedAt,
                  incident.reportedAt != null
                ),
                (
                  'Responder on the way',
                  incident.responderAcknowledgedAt ??
                      incident.responderAssignedAt,
                  incident.responderAcknowledgedAt != null ||
                      incident.responderAssignedAt != null,
                ),
                (
                  'Responder checking on you',
                  incident.responderArrivedAt,
                  incident.responderArrivedAt != null
                ),
                (
                  'Help confirmed',
                  incident.patientPickedUpAt,
                  incident.patientPickedUpAt != null
                ),
                (
                  'On the way to the hospital',
                  incident.hospitalSentAt,
                  incident.hospitalSentAt != null
                ),
                (
                  'Arrived',
                  incident.hospitalArrivedAt,
                  incident.hospitalArrivedAt != null
                ),
              ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 14, 24, 28),
      children: [
        Align(
          alignment: Alignment.centerLeft,
          child: IconButton(
            tooltip: 'Back to report history',
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 30, minHeight: 30),
            onPressed: () => setState(() => _selected = null),
            icon: const Icon(Icons.arrow_back, size: 20),
          ),
        ),
        const SizedBox(height: 14),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                '${incident.type} — ${entry.patientLabel}',
                style: const TextStyle(
                  fontSize: 17,
                  height: 1.1,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 10),
            if (closed)
              _statusPill('Closed', closed: true)
            else
              _severityLabel(incident.status),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: const TextStyle(fontSize: 9, color: Colors.black38),
        ),
        const SizedBox(height: 22),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 13),
          decoration: BoxDecoration(
            color:
                falseReport ? const Color(0xFF55595B) : const Color(0xFFADB5BA),
            borderRadius: BorderRadius.circular(7),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                falseReport
                    ? 'Report closed'
                    : arrived
                        ? 'Arrived at ${incident.hospital ?? 'the hospital'}'
                        : 'Emergency in progress',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (falseReport)
                const Text(
                  'A responder checked and found no emergency',
                  style: TextStyle(color: Colors.white70, fontSize: 8),
                )
              else if (arrived && incident.hospitalArrivedAt != null)
                Text(
                  _time(incident.hospitalArrivedAt),
                  style: const TextStyle(color: Colors.white70, fontSize: 8),
                )
              else if (!closed)
                const Text(
                  'Response updates from your case',
                  style: TextStyle(color: Colors.white70, fontSize: 8),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.fromLTRB(13, 14, 13, 12),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE2E2E2)),
            borderRadius: BorderRadius.circular(7),
          ),
          child: Column(
            children: [
              for (var i = 0; i < timeline.length; i++)
                _progressRow(
                  title: timeline[i].$1,
                  date: timeline[i].$2,
                  completed: timeline[i].$3,
                  isLast: i == timeline.length - 1,
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        if (falseReport)
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFDADADA),
              borderRadius: BorderRadius.circular(7),
            ),
            child: Text(
              [
                'False reports take help away from people who need it. '
                    'Repeated false reports can pause SOS.',
                if (entry.falseReportReason?.isNotEmpty == true)
                  'Reason: ${_falseReason(entry.falseReportReason!)}.',
              ].join(' '),
              style: const TextStyle(fontSize: 8, color: Colors.black54),
            ),
          )
        else
          _responseInfo(
            responderTeam:
                responderTeam.isEmpty ? 'Not assigned' : responderTeam,
            responseTime: responseMinutes == null
                ? closed
                    ? 'Not recorded'
                    : 'In progress'
                : '$responseMinutes min',
            hospital: incident.hospital ?? 'Not assigned',
          ),
        if (falseReport && entry.falseReportNotes?.isNotEmpty == true) ...[
          const SizedBox(height: 8),
          Text(
            entry.falseReportNotes!,
            style: const TextStyle(fontSize: 10, color: Colors.black54),
          ),
        ],
        if (!falseReport && entry.reportedBy == 'on_scene') ...[
          const SizedBox(height: 10),
          Text(
            'Reported for ${entry.patientLabel}',
            style: const TextStyle(fontSize: 9, color: Colors.black45),
          ),
        ],
      ],
    );
  }

  Widget _progressRow({
    required String title,
    required DateTime? date,
    required bool completed,
    required bool isLast,
  }) =>
      SizedBox(
        height: isLast ? 19 : 25,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 18,
              child: Column(
                children: [
                  Icon(
                    completed ? Icons.check_circle : Icons.circle,
                    size: 14,
                    color: completed
                        ? const Color(0xFF73B86A)
                        : const Color(0xFFD9D9D9),
                  ),
                  if (!isLast)
                    Container(
                      width: 1,
                      height: 11,
                      color: const Color(0xFFD8D8D8),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 1),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        title,
                        style: TextStyle(
                          fontSize: 9,
                          height: 1.1,
                          fontWeight:
                              completed ? FontWeight.w600 : FontWeight.normal,
                          color: completed ? Colors.black87 : Colors.black38,
                        ),
                      ),
                    ),
                    if (date != null)
                      Text(
                        _time(date),
                        style: const TextStyle(
                          fontSize: 8,
                          color: Colors.black45,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );

  Widget _responseInfo({
    required String responderTeam,
    required String responseTime,
    required String hospital,
  }) =>
      Container(
        decoration: BoxDecoration(
          border: Border.all(color: const Color(0xFFE2E2E2)),
          borderRadius: BorderRadius.circular(7),
        ),
        child: Column(
          children: [
            _responseInfoRow('Responder team', responderTeam),
            _responseInfoRow('Response time', responseTime),
            _responseInfoRow('Hospital', hospital, last: true),
          ],
        ),
      );

  Widget _responseInfoRow(String label, String value, {bool last = false}) =>
      Container(
        constraints: const BoxConstraints(minHeight: 27),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: Color(0xFFE9E9E9)),
                ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(fontSize: 8, color: Colors.black38),
              ),
            ),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.right,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 8,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      );

  int? _responseMinutes(IncidentHistoryEntry entry) {
    final reportedAt = entry.incident.reportedAt;
    final arrivedAt = entry.incident.responderArrivedAt;
    if (reportedAt == null || arrivedAt == null) return null;
    final minutes = arrivedAt.difference(reportedAt).inMinutes;
    return minutes >= 0 ? minutes : null;
  }

  String _time(DateTime? date) {
    if (date == null) return '';
    final local = date.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    return '$hour:${local.minute.toString().padLeft(2, '0')} '
        '${local.hour < 12 ? 'AM' : 'PM'}';
  }

  String _shortDate(DateTime date) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final local = date.toLocal();
    return '${months[local.month - 1]} ${local.day}, ${local.year}';
  }

  String _locationLabel(String? barangay, String location) {
    final name = barangay?.trim();
    final place = location.trim();
    if (name == null || name.isEmpty) return place;
    final normalizedName = name.toLowerCase();
    final normalizedPlace = place.toLowerCase();
    final barangayLabel = normalizedName.startsWith('brgy') ||
            normalizedName.startsWith('barangay')
        ? name
        : 'Brgy. $name';
    if (normalizedPlace == normalizedName ||
        normalizedPlace == 'brgy. $normalizedName' ||
        normalizedPlace == 'barangay $normalizedName') {
      return barangayLabel;
    }
    return '$barangayLabel · $place';
  }

  Widget _statusPill(String label, {required bool closed}) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 100),
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE0E0E0)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: closed ? const Color(0xFF545454) : const Color(0xFF397B43),
          fontSize: 8,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _severityLabel(String status) {
    final (label, color) = switch (status) {
      'critical' => ('Critical', const Color(0xFFC62828)),
      'urgent' => ('Urgent', const Color(0xFFEF6C00)),
      'stable' => ('Stable', const Color(0xFF2E7D32)),
      _ => (_statusLabel(status), const Color(0xFF565D63)),
    };
    return Text(
      label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: color,
        fontSize: 9,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  Widget _emptyResults() => Padding(
        padding: const EdgeInsets.symmetric(vertical: 35, horizontal: 16),
        child: Column(
          children: [
            const Icon(Icons.inbox_outlined, size: 36, color: Colors.black38),
            const SizedBox(height: 9),
            Text(
              _entries.isEmpty
                  ? 'No reports yet'
                  : 'No ${_filter.name} reports',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 4),
            Text(
              _entries.isEmpty
                  ? 'Reports you submit, including reports for someone else, will appear here.'
                  : 'Try another filter to view your reports.',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
          ],
        ),
      );

  Widget _messageView({
    required IconData icon,
    required String message,
    required String actionLabel,
    required VoidCallback onAction,
  }) =>
      Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 42, color: Colors.black38),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              TextButton(onPressed: onAction, child: Text(actionLabel)),
            ],
          ),
        ),
      );

  Widget _inlineError(String message) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Text(
          message,
          style: const TextStyle(color: Colors.red, fontSize: 12),
        ),
      );

  String _monthLabel(DateTime date) {
    const months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    return '${months[date.toLocal().month - 1]} ${date.toLocal().year}';
  }

  String _statusLabel(String status) => switch (status) {
        'critical' => 'Critical',
        'urgent' => 'Urgent',
        'stable' => 'Stable',
        'dispatched' => 'Responder dispatched',
        _ => _capitalize(status.replaceAll('_', ' ')),
      };

  String _falseReason(String reason) => switch (reason) {
        'prank_or_test' => 'Prank or test',
        'wrong_location' => 'Wrong location',
        'already_resolved' => 'Already resolved',
        'cannot_find_anyone' => 'Unable to find anyone',
        _ => _capitalize(reason.replaceAll('_', ' ')),
      };

  String _capitalize(String value) =>
      value.isEmpty ? value : '${value[0].toUpperCase()}${value.substring(1)}';
}
