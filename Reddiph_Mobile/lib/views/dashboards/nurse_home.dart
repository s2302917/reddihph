import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/nurse_case_detail.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseHome extends StatefulWidget {
  const NurseHome({super.key});

  @override
  State<NurseHome> createState() => _NurseHomeState();
}

class _NurseHomeState extends State<NurseHome> {
  NurseDashboardData? _dashboard;
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
        _error = 'Your session expired. Sign in again to load nurse data.';
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await MobileApiClient.I.nurseDashboard(token: token);
      if (!mounted) return;
      NursePortalCache.saveDashboard(token, data);
      setState(() {
        _dashboard = data;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      final cached = NursePortalCache.dashboard(token);
      setState(() {
        _dashboard = cached;
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _openCase(NurseCaseRecord item) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => NurseCaseDetail(item: item),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final data = _dashboard;
    if (_loading && data == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (data == null) {
      return _loadError();
    }
    final profile = data.profile;
    final now = DateTime.now();
    final greeting = now.hour < 12
        ? 'Good morning,'
        : now.hour < 18
            ? 'Good afternoon,'
            : 'Good evening,';
    final incoming = data.cases
        .where((item) => item.status == 'en_route' || item.status == 'notified')
        .toList(growable: false);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 110),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(greeting, style: const TextStyle(fontSize: 11)),
                      Text(
                        profile.firstName,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        '${profile.department} · ${profile.hospitalName}',
                        style: const TextStyle(
                          fontSize: 9,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                CircleAvatar(
                  radius: 16,
                  backgroundColor: kNurse,
                  child: Text(
                    profile.firstName.isEmpty
                        ? 'N'
                        : profile.firstName.substring(0, 1).toUpperCase(),
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
                ),
              ],
            ),
          ),
          if (_error != null) _readOnlyNotice(_error!),
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 20),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              gradient: kNurseGradient,
            ),
            child: Row(
              children: [
                Icon(
                  profile.isAvailable ? Icons.check_circle : Icons.schedule,
                  color: Colors.white,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        profile.isAvailable ? 'On duty' : 'Off duty',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${profile.hospitalName} · ${TimeOfDay.fromDateTime(now).format(context)}',
                        style: const TextStyle(
                          fontSize: 9,
                          color: Colors.white70,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  '${data.summary['incoming'] ?? incoming.length} incoming',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const Section('Preparation overview'),
          Box(
            Row(
              children: [
                _summaryValue(
                  '${data.summary['prep_complete'] ?? 0}/${data.summary['prep_total'] ?? 0}',
                  'Cases prepared',
                ),
                _summaryValue(
                  '${data.summary['free_er_beds'] ?? 0}',
                  'ER beds ready',
                ),
                _summaryValue(
                  '${data.summary['free_pediatric_beds'] ?? 0}',
                  'Pediatric beds',
                ),
              ],
            ),
          ),
          const Section('Incoming patients'),
          if (data.cases.isEmpty)
            const Box(
              Text(
                'No cases have been assigned to your account.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            )
          else
            for (final item in data.cases.take(5))
              _caseCard(item, onTap: () => _openCase(item)),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(12),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }

  Widget _loadError() => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off, size: 38, color: Colors.black45),
              const SizedBox(height: 10),
              Text(
                _error ?? 'Nurse data is unavailable.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12),
              ),
              const SizedBox(height: 12),
              OutlinedButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );

  Widget _readOnlyNotice(String message) => Container(
        margin: const EdgeInsets.fromLTRB(20, 0, 20, 10),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3D7),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          children: [
            const Icon(Icons.cloud_off, size: 16, color: Colors.black54),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Connection unavailable. Showing last loaded data when available; updates are disabled. $message',
                style: const TextStyle(fontSize: 9),
              ),
            ),
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh, size: 17),
              tooltip: 'Retry connection',
            ),
          ],
        ),
      );

  Widget _summaryValue(String value, String label) => Expanded(
        child: Column(
          children: [
            Text(
              value,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: kNurse,
              ),
            ),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 8, color: Colors.black54),
            ),
          ],
        ),
      );

  Widget _caseCard(NurseCaseRecord item, {required VoidCallback onTap}) {
    final statusText = switch (item.status) {
      'en_route' => 'En route · ${item.etaMinutes ?? '—'} min',
      'arrived' => 'Arrived',
      'handed_off' => 'Handed off',
      _ => 'Awaiting transport',
    };
    return Card(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${item.title} · ${item.patientName}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Text(
                    item.priority,
                    style: const TextStyle(
                      color: kSos,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${item.location} · $statusText',
                style: const TextStyle(fontSize: 9, color: Colors.black54),
              ),
              const SizedBox(height: 7),
              LinearProgressIndicator(
                value: item.prepTotal == 0 ? 0 : item.prepDone / item.prepTotal,
                color: kNurse,
                backgroundColor: Colors.black12,
              ),
              const SizedBox(height: 4),
              Text(
                '${item.prepDone} of ${item.prepTotal} preparation steps complete',
                style: const TextStyle(fontSize: 8, color: Colors.black54),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
