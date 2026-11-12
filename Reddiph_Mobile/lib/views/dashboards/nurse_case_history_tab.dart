import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/nurse_case_history_detail.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseCaseHistoryTab extends StatefulWidget {
  const NurseCaseHistoryTab({super.key});

  @override
  State<NurseCaseHistoryTab> createState() => _NurseCaseHistoryTabState();
}

class _NurseCaseHistoryTabState extends State<NurseCaseHistoryTab> {
  static const _filters = ['All', 'Active', 'Resolved'];

  List<NurseCaseHistoryRecord> _history = const [];
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
      final history = await MobileApiClient.I.nurseCaseHistory(token: token);
      if (!mounted) return;
      NursePortalCache.saveHistory(token, history);
      setState(() {
        _history = history;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _history = NursePortalCache.history(token) ?? _history;
        _error = error.message;
        _loading = false;
      });
    }
  }

  bool _matches(NurseCaseHistoryRecord item) {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return true;
    return '${item.id} ${item.patientName} ${item.title} ${item.location} '
            '${item.responder}'
        .toLowerCase()
        .contains(query);
  }

  bool _matchesFilter(NurseCaseHistoryRecord item) => switch (_filter) {
        'Active' => item.status != 'resolved',
        'Resolved' => item.status == 'resolved',
        _ => true,
      };

  @override
  Widget build(BuildContext context) {
    final visible =
        _history.where((item) => _matchesFilter(item) && _matches(item));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 110),
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 18),
            decoration: const BoxDecoration(
              gradient: kNurseGradient,
              borderRadius: BorderRadius.vertical(bottom: Radius.circular(20)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 24),
                const Text(
                  'Case History',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Text(
                  'Cases previously notified to your account',
                  style: TextStyle(color: Colors.white70, fontSize: 9),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final filter in _filters)
                      ChoiceChip(
                        label: Text(filter),
                        selected: _filter == filter,
                        onSelected: (_) => setState(() => _filter = filter),
                        selectedColor: Colors.white,
                        labelStyle: TextStyle(
                          color: _filter == filter ? kNurse : Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                        ),
                        backgroundColor: Colors.white24,
                        side: BorderSide.none,
                        visualDensity: VisualDensity.compact,
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
            child: Section('Your notified case records'),
          ),
          if (_loading && _history.isEmpty)
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
                    ? 'No historical cases match this filter.'
                    : 'No previously loaded history is available.',
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
              tooltip: 'Retry loading history',
            ),
          ],
        ),
      );

  Widget _caseCard(BuildContext context, NurseCaseHistoryRecord item) {
    final statusColor =
        item.status == 'resolved' ? const Color(0xFF3D8A64) : kNurse;

    return Card(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.of(context).push<void>(
          MaterialPageRoute<void>(
            builder: (_) => NurseCaseHistoryDetail(item: item),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              const Icon(Icons.history, size: 18, color: kNurse),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.patientName,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '${item.title} · ${item.location}',
                      style:
                          const TextStyle(fontSize: 9, color: Colors.black54),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      'Incident #${item.id} · ${item.triageCategoryLabel} · '
                      '${item.notifiedAt}',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                item.status.replaceAll('_', ' ').toUpperCase(),
                style: TextStyle(
                  color: statusColor,
                  fontSize: 8,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
