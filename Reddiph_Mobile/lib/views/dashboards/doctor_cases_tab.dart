import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/doctor_case_detail.dart';
import '../widgets/doctor_widgets.dart';

class DoctorCasesTab extends StatefulWidget {
  const DoctorCasesTab({super.key});

  @override
  State<DoctorCasesTab> createState() => _DoctorCasesTabState();
}

class _DoctorCasesTabState extends State<DoctorCasesTab> {
  String _filter = 'Incoming';
  String _query = '';
  List<DoctorCaseRecord> _cases = const [];
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
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
      final cases = await MobileApiClient.I.doctorCases(token: token);
      if (!mounted) return;
      setState(() {
        _cases = cases;
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

  bool _matches(DoctorCaseRecord item) {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return true;
    return '${item.patient} ${item.title} ${item.location} ${item.responder}'
        .toLowerCase()
        .contains(query);
  }

  bool _belongs(DoctorCaseRecord item) => switch (_filter) {
        'Incoming' => item.isIncoming,
        'In ER' => item.isInEr,
        'Done' => item.status == 'resolved',
        _ => true,
      };

  @override
  Widget build(BuildContext context) {
    final filters = ['Incoming', 'In ER', 'Done'];
    final visible = _cases.where((item) => _belongs(item) && _matches(item));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 110),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [kNavy, kNavy2],
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
              ),
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(20)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 28),
                const Text(
                  'Cases',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Text(
                  'Assigned to your hospital',
                  style: TextStyle(color: Colors.white60, fontSize: 9),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  children: [
                    for (final filter in filters)
                      GestureDetector(
                        onTap: () => setState(() => _filter = filter),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 160),
                          padding: const EdgeInsets.symmetric(
                              horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: _filter == filter ? kSlate : Colors.white24,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: _filter == filter
                                  ? Colors.white54
                                  : Colors.transparent,
                            ),
                          ),
                          child: Text(
                            '$filter ${_cases.where((item) {
                              if (filter == 'Incoming') return item.isIncoming;
                              if (filter == 'In ER') return item.isInEr;
                              return item.status == 'resolved';
                            }).length}',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w600,
                              color: _filter == filter
                                  ? Colors.white
                                  : Colors.white70,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                SizedBox(
                  height: 36,
                  child: TextField(
                    onChanged: (value) => setState(() => _query = value),
                    style: const TextStyle(fontSize: 10),
                    decoration: InputDecoration(
                      filled: true,
                      fillColor: Colors.white,
                      isDense: true,
                      prefixIcon: const Icon(Icons.search, size: 16),
                      hintText: 'Search patient, complaint or location',
                      hintStyle:
                          const TextStyle(fontSize: 9, color: Colors.black45),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Text(
              _filter == 'Incoming'
                  ? 'Sorted by urgency'
                  : _filter == 'In ER'
                      ? 'In the emergency room'
                      : 'Completed cases',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
            ),
          ),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_error != null)
            _errorState(_error!)
          else if (visible.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: Text(
                'No cases match this filter.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            )
          else
            for (final item in visible) _caseRow(context, item),
        ],
      ),
    );
  }

  Widget _caseRow(BuildContext context, DoctorCaseRecord item) {
    final severity = doctorSeverity(item.statusLabel);
    final dot = item.status == 'critical'
        ? const Color(0xFF7A1410)
        : item.isInEr
            ? const Color(0xFFD9A93B)
            : Colors.black87;
    return GestureDetector(
      onTap: () => Nav.push(
        context,
        DoctorCaseDetail(incidentId: item.id, initialItem: item),
      ),
      child: DCard(
        Row(
          children: [
            Icon(Icons.circle, size: 8, color: dot),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(item.patient,
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w600)),
                  Text(
                    '${item.summary} · ${item.location}',
                    style: const TextStyle(fontSize: 8, color: Colors.black54),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            SmallPill(item.statusLabel, severity),
          ],
        ),
        margin: const EdgeInsets.fromLTRB(20, 0, 20, 8),
      ),
    );
  }

  Widget _errorState(String message) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Column(
          children: [
            Text(message,
                style: const TextStyle(fontSize: 10, color: kSos)),
            TextButton(onPressed: _load, child: const Text('RETRY')),
          ],
        ),
      );
}
