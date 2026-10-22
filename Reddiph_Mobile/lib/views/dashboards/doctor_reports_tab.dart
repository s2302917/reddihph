import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/doctor_case_detail.dart';
import '../widgets/doctor_widgets.dart';

class DoctorReportsTab extends StatefulWidget {
  const DoctorReportsTab({super.key});

  @override
  State<DoctorReportsTab> createState() => _DoctorReportsTabState();
}

class _DoctorReportsTabState extends State<DoctorReportsTab> {
  static const _days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  String _period = 'week';
  int _selectedDay = 0;
  DoctorHistoryData? _history;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({String? period}) async {
    if (period != null) _period = period;
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() {
        _loading = false;
        _error = 'Your session has expired. Please sign in again.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final history = await MobileApiClient.I.doctorHistory(
        token: token,
        period: _period,
      );
      if (!mounted) return;
      setState(() {
        _history = history;
        _selectedDay = DateTime.now().weekday - 1;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final history = _history;
    if (_loading && history == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (history == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error ?? 'Case history is unavailable.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 10, color: kSos)),
              TextButton(onPressed: _load, child: const Text('RETRY')),
            ],
          ),
        ),
      );
    }
    final maxCount = history.weeklyCounts.fold<int>(
      1,
      (maximum, count) => count > maximum ? count : maximum,
    );

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Emergency logs',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.w900)),
                    SizedBox(height: 2),
                    Text('Cases sent to your account',
                        style: TextStyle(fontSize: 9, color: Colors.black54)),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                initialValue: _period,
                onSelected: (value) => _load(period: value),
                itemBuilder: (context) => const [
                  PopupMenuItem(value: 'week', child: Text('This week')),
                  PopupMenuItem(value: 'month', child: Text('This month')),
                  PopupMenuItem(value: 'all', child: Text('All time')),
                ],
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.black12),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_periodLabel,
                          style: const TextStyle(
                              fontSize: 9, fontWeight: FontWeight.w600)),
                      const SizedBox(width: 4),
                      const Icon(Icons.arrow_drop_down, size: 16),
                    ],
                  ),
                ),
              ),
            ],
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(_error!,
                        style: const TextStyle(fontSize: 9, color: kSos)),
                  ),
                  TextButton(onPressed: _load, child: const Text('RETRY')),
                ],
              ),
            ),
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              gradient: const LinearGradient(
                colors: [Color(0xFF55627F), kSlate],
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${history.total}',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 22,
                                  fontWeight: FontWeight.w900)),
                          Text(
                            'Cases ${_period == 'week' ? 'this week' : _period == 'month' ? 'this month' : 'all time'}',
                            style: const TextStyle(
                                color: Colors.white, fontSize: 9),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 18),
                      child: Text(
                        '${_days[_selectedDay]}: '
                        '${history.weeklyCounts[_selectedDay]}',
                        style:
                            const TextStyle(color: Colors.white, fontSize: 8),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 150,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      for (int index = 0; index < _days.length; index++)
                        Expanded(
                          child: GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: () => setState(() => _selectedDay = index),
                            child: Padding(
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 3),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  AnimatedContainer(
                                    duration:
                                        const Duration(milliseconds: 220),
                                    height: 20 +
                                        (history.weeklyCounts[index] /
                                                maxCount) *
                                            90,
                                    decoration: BoxDecoration(
                                      color: index == _selectedDay
                                          ? Colors.white
                                          : kSlateLight.withValues(alpha: .85),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(_days[index],
                                      style: const TextStyle(
                                          color: Colors.white, fontSize: 8)),
                                ],
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _stat('${history.critical}', 'Critical cases', kSos),
              const SizedBox(width: 10),
              _stat(
                history.averageMinutes == null
                    ? '—'
                    : '${history.averageMinutes}m',
                'Avg. arrival time',
                Colors.black,
              ),
            ],
          ),
          const SizedBox(height: 22),
          const Text('Case history',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          if (history.cases.isEmpty)
            const DCard(
              Text('No cases have been sent to your account.',
                  style: TextStyle(fontSize: 9, color: Colors.black54)),
            )
          else
            DCard(
              Column(
                children: [
                  for (int index = 0; index < history.cases.length; index++) ...[
                    if (index > 0) const Divider(height: 1),
                    InkWell(
                      onTap: () => Nav.push(
                        context,
                        DoctorCaseDetail(
                          incidentId: history.cases[index].id,
                        ),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${history.cases[index].title} · '
                                    '${history.cases[index].patient}',
                                    style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  Text(
                                    'Status: ${history.cases[index].status}',
                                    style: const TextStyle(
                                        fontSize: 8, color: Colors.black54),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              history.cases[index].reportedLabel,
                              style: const TextStyle(
                                  fontSize: 8, color: Colors.black54),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              padding: const EdgeInsets.symmetric(horizontal: 14),
              margin: EdgeInsets.zero,
            ),
        ],
      ),
    );
  }

  String get _periodLabel => switch (_period) {
        'month' => 'This month',
        'all' => 'All time',
        _ => 'This week',
      };

  Widget _stat(String value, String label, Color color) => Expanded(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.black12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(value,
                  style: TextStyle(
                      fontSize: 20, fontWeight: FontWeight.w900, color: color)),
              const SizedBox(height: 2),
              Text(label,
                  style:
                      const TextStyle(fontSize: 9, color: Colors.black54)),
            ],
          ),
        ),
      );
}
