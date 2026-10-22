import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/doctor_widgets.dart';

class VolunteerHistoryTab extends StatefulWidget {
  const VolunteerHistoryTab({super.key, required this.onBack});

  final VoidCallback onBack;

  @override
  State<VolunteerHistoryTab> createState() => _VolunteerHistoryTabState();
}

class _VolunteerHistoryTabState extends State<VolunteerHistoryTab> {
  List<VolunteerHistoryItem> _items = const [];
  bool _loading = true;
  String? _error;

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
        _error = 'Sign in again to load your assistance history.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await MobileApiClient.I.volunteerHistory(token: token);
      if (!mounted) return;
      setState(() {
        _items = items;
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
    final profile = AuthController.I.user ?? const <String, dynamic>{};
    final assisted = (profile['volunteer_assisted_incidents'] as num? ?? 0)
        .toInt();
    final rating = profile['volunteer_rating'] as num?;
    final groups = <String, List<VolunteerHistoryItem>>{};
    for (final item in _items) {
      final day = DateUtils.isSameDay(item.completedAt, PhilippineTime.now())
          ? 'Today'
          : DateUtils.isSameDay(
                  item.completedAt, PhilippineTime.now().subtract(const Duration(days: 1)))
              ? 'Yesterday'
              : '${item.completedAt.day}/${item.completedAt.month}/${item.completedAt.year}';
      groups.putIfAbsent(day, () => []).add(item);
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 110),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton(
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
              alignment: Alignment.centerLeft,
              icon: const Icon(Icons.arrow_back_ios_new, size: 16),
              onPressed: widget.onBack,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'My assistance history',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
          ),
          Text(
            '$assisted completed · ${rating?.toStringAsFixed(1) ?? '—'} average rating',
            style: const TextStyle(fontSize: 9, color: Colors.black54),
          ),
          const SizedBox(height: 22),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_error != null)
            _HistoryMessage(message: _error!, onRetry: _load)
          else if (_items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),
              child: Text(
                'Completed assistance records will appear here.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            )
          else
            for (final group in groups.entries) ...[
              Padding(
                padding: const EdgeInsets.only(bottom: 8, top: 4),
                child: Text(
                  group.key,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              for (final item in group.value)
                DCard(
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${item.incidentType} — ${item.patientName}',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              '${item.location} · ${PhilippineTime.clock(item.completedAt)}',
                              style: const TextStyle(
                                fontSize: 8,
                                color: Colors.black54,
                              ),
                            ),
                            if (item.review?.isNotEmpty == true)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  item.review!,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 8),
                                ),
                              ),
                          ],
                        ),
                      ),
                      if (item.rating != null) ...[
                        Text(
                          item.rating!.toStringAsFixed(1),
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 3),
                        const Icon(Icons.star, size: 10, color: kSos),
                      ],
                    ],
                  ),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
                  margin: const EdgeInsets.only(bottom: 8),
                ),
            ],
        ],
      ),
    );
  }
}

class _HistoryMessage extends StatelessWidget {
  const _HistoryMessage({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Column(
        children: [
          Text(message, style: const TextStyle(fontSize: 10, color: kSos)),
          TextButton(onPressed: onRetry, child: const Text('RETRY')),
        ],
      );
}
