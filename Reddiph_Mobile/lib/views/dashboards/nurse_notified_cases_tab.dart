import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/nurse_case_detail.dart';
import '../widgets/common.dart' show Section;
import '../widgets/doctor_widgets.dart' show DCard, SmallPill, doctorSeverity;
import '../widgets/nurse_widgets.dart';

class NurseNotifiedCasesTab extends StatefulWidget {
  const NurseNotifiedCasesTab({super.key});

  @override
  State<NurseNotifiedCasesTab> createState() => _NurseNotifiedCasesTabState();
}

class _NurseNotifiedCasesTabState extends State<NurseNotifiedCasesTab> {
  static const _filters = ['All', 'Notified', 'En route', 'Arrived'];

  List<NurseCaseRecord> _cases = const [];
  String _filter = 'All';
  String _query = '';
  String? _error;
  bool _loading = true;

  String? get _token => AuthController.I.user?['token'] as String?;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null || token.isEmpty) {
      setState(() {
        _error = 'Your session expired. Sign in again.';
        _loading = false;
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final cases = await MobileApiClient.I.nurseCases(token: token);
      if (!mounted) return;
      NursePortalCache.saveCases(token, cases);
      setState(() {
        _cases = cases;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _cases = NursePortalCache.cases(token) ?? _cases;
        _error = error.message;
        _loading = false;
      });
    }
  }

  bool _matches(NurseCaseRecord item) {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return true;
    return '${item.id} ${item.patientName} ${item.title} ${item.location} '
            '${item.responder} ${item.unit} ${item.triageCategoryLabel} '
            '${item.status} ${item.incidentStatus} ${item.hospitalName}'
        .toLowerCase()
        .contains(query);
  }

  bool _matchesFilter(NurseCaseRecord item) => switch (_filter) {
        'Notified' => item.status == 'notified',
        'En route' => item.status == 'en_route',
        'Arrived' => item.status == 'arrived',
        _ => true,
      };

  @override
  Widget build(BuildContext context) {
    final visible =
        _cases.where((item) => _matchesFilter(item) && _matches(item));
    final filters = {
      'All': _cases.length,
      'Notified': _cases.where((item) => item.status == 'notified').length,
      'En route': _cases.where((item) => item.status == 'en_route').length,
      'Arrived': _cases.where((item) => item.status == 'arrived').length,
    };

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 110),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
            decoration: const BoxDecoration(
              gradient: kNurseGradient,
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(20)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 24),
                const Text(
                  'Notified Cases',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Text(
                  'Cases sent directly to your account',
                  style: TextStyle(color: Colors.white70, fontSize: 9),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final filter in _filters)
                      Semantics(
                        button: true,
                        selected: _filter == filter,
                        child: GestureDetector(
                          onTap: () => setState(() => _filter = filter),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 160),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: _filter == filter
                                  ? const Color(0xFF5E6A68)
                                  : Colors.white24,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: _filter == filter
                                    ? Colors.white54
                                    : Colors.transparent,
                              ),
                            ),
                            child: Text(
                              '$filter ${filters[filter]}',
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
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  onChanged: (value) => setState(() => _query = value),
                  style: const TextStyle(fontSize: 10),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: Colors.white,
                    isDense: true,
                    prefixIcon: const Icon(Icons.search, size: 16),
                    hintText: 'Search patient, incident or location',
                    hintStyle:
                        const TextStyle(fontSize: 9, color: Colors.black45),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
              child: _errorNotice(_error!),
            ),
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Section('Cases assigned to you'),
          ),
          if (_loading && _cases.isEmpty)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (visible.isEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: Text(
                _error == null
                    ? 'No cases match this filter.'
                    : 'No previously loaded cases are available.',
                style: const TextStyle(fontSize: 10, color: Colors.black54),
              ),
            )
          else
            for (final item in visible) _caseCard(context, item),
        ],
      ),
    );
  }

  Widget _errorNotice(String message) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3D7),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          children: [
            const Icon(Icons.cloud_off, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Showing last loaded records when available. $message',
                style: const TextStyle(fontSize: 9),
              ),
            ),
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh, size: 18),
              tooltip: 'Retry loading cases',
            ),
          ],
        ),
      );

  Widget _caseCard(BuildContext context, NurseCaseRecord item) {
    final severity = doctorSeverity(item.triageCategoryLabel);
    final status = switch (item.status) {
      'en_route' =>
        'En route${item.etaMinutes == null ? '' : ' · ETA ${item.etaMinutes} min'}',
      'arrived' => 'Arrived',
      _ => 'Awaiting transport',
    };

    return Semantics(
      button: true,
      label: 'View ${item.patientName}, ${item.title}, $status',
      child: GestureDetector(
        onTap: () async {
          await Navigator.of(context).push<void>(
            MaterialPageRoute<void>(
              builder: (_) => NurseCaseDetail(item: item),
            ),
          );
          if (mounted) _load();
        },
        child: DCard(
          Row(
            children: [
              Icon(Icons.circle, size: 8, color: severity.bg),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.patientName,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(
                      '${item.title} · ${item.location} · ${item.hospitalName}',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      '$status · ${item.incidentStatus} · Incident #${item.id}',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SmallPill(item.triageCategoryLabel, severity),
            ],
          ),
          margin: const EdgeInsets.fromLTRB(20, 0, 20, 8),
        ),
      ),
    );
  }
}
