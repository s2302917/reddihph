import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../screens/volunteer_recognition_screen.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/volunteer_widgets.dart';

class VolunteerCommunityTab extends StatefulWidget {
  const VolunteerCommunityTab({super.key});

  @override
  State<VolunteerCommunityTab> createState() => _VolunteerCommunityTabState();
}

class _VolunteerCommunityTabState extends State<VolunteerCommunityTab> {
  static const _filters = ['All', 'Today', 'Earlier'];

  String _filter = 'All';
  Map<String, dynamic> _community = const {};
  bool _loading = true;
  String? _error;
  bool _showAllPeople = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() {
        _error = 'Sign in again to load community feedback.';
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await MobileApiClient.I.volunteerCommunity(token: token);
      if (!mounted) return;
      setState(() {
        _community = data;
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
    final people =
        (_community['people'] as List<VolunteerCommunityPerson>?) ?? const [];
    final entries =
        (_community['entries'] as List<VolunteerCommunityEntry>?) ?? const [];
    final profile = _community['profile'] as Map<String, dynamic>? ?? const {};
    final filteredEntries = entries.where((entry) {
      final today = DateUtils.isSameDay(
        entry.createdAt,
        PhilippineTime.now(),
      );
      return switch (_filter) {
        'Today' => today,
        'Earlier' => !today,
        _ => true,
      };
    }).toList(growable: false);
    final visiblePeople = _showAllPeople ? people : people.take(3).toList();
    final myThanks = (profile['thanks_count'] as num?)?.toInt() ?? 0;
    final myRating = (profile['rating'] as num?)?.toDouble();
    final communityRating = _community['community_rating'] as double?;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          const Text(
            'Appreciation wall',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 2),
          const Text(
            'Resident ratings and messages for volunteer assistance',
            style: TextStyle(fontSize: 8, color: Colors.black54),
          ),
          const SizedBox(height: 16),
          if (_loading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_error != null)
            _CommunityError(message: _error!, onRetry: _load)
          else ...[
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: kVolGradient,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '$myThanks',
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                          ),
                        ),
                        const Text(
                          'Reviews received',
                          style: TextStyle(fontSize: 8, color: Colors.white70),
                        ),
                        if ((profile['thanks_this_month'] as num? ?? 0) > 0)
                          Text(
                            '${profile['thanks_this_month']} this month',
                            style: const TextStyle(
                              fontSize: 7,
                              color: Colors.white70,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.star, size: 14, color: kStar),
                          const SizedBox(width: 3),
                          Text(
                            myRating?.toStringAsFixed(1) ?? '—',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                      const Text(
                        'your average rating',
                        style: TextStyle(fontSize: 8, color: Colors.white70),
                      ),
                      if (communityRating != null)
                        Text(
                          'Community average ${communityRating.toStringAsFixed(1)}',
                          style: const TextStyle(
                            fontSize: 7,
                            color: Colors.white70,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Most appreciated',
                    style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800),
                  ),
                ),
                if (people.length > 3)
                  TextButton(
                    onPressed: () =>
                        setState(() => _showAllPeople = !_showAllPeople),
                    child: Text(
                      _showAllPeople ? 'Show top 3' : 'See all',
                      style: const TextStyle(fontSize: 8),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            if (people.isEmpty)
              const Text(
                'Volunteer rankings will appear when feedback is received.',
                style: TextStyle(fontSize: 9, color: Colors.black54),
              )
            else
              for (final person in visiblePeople)
                _PersonTile(
                  person: person,
                  onTap: () => Nav.push(
                    context,
                    VolunteerRecognitionScreen(
                      volunteerId: person.id,
                      name: person.name,
                    ),
                  ),
                ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              children: [
                for (final filter in _filters)
                  ChoiceChip(
                    label: Text(filter),
                    selected: _filter == filter,
                    onSelected: (_) => setState(() => _filter = filter),
                    labelStyle: TextStyle(
                      fontSize: 8,
                      fontWeight: FontWeight.w700,
                      color: _filter == filter ? Colors.white : Colors.black87,
                    ),
                    backgroundColor: Colors.white,
                    selectedColor: kNavy,
                    visualDensity: VisualDensity.compact,
                    side: BorderSide(
                      color: _filter == filter ? kNavy : Colors.black26,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            for (final entry in filteredEntries)
              _ReviewCard(
                entry: entry,
                onTap: () => Nav.push(
                  context,
                  VolunteerRecognitionScreen(
                    volunteerId: entry.volunteerId,
                    name: entry.volunteerName,
                  ),
                ),
              ),
            if (filteredEntries.isEmpty)
              const Padding(
                padding: EdgeInsets.all(12),
                child: Text(
                  'No resident feedback for this period.',
                  style: TextStyle(fontSize: 9, color: Colors.black54),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _PersonTile extends StatelessWidget {
  const _PersonTile({required this.person, required this.onTap});

  final VolunteerCommunityPerson person;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => DCard(
        InkWell(
          onTap: onTap,
          child: Row(
            children: [
              CircleAvatar(
                radius: 15,
                backgroundColor: kNavy,
                child: Text(
                  person.name.isEmpty ? '?' : person.name[0].toUpperCase(),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      person.name,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      [
                        if (person.vehicleType?.isNotEmpty == true)
                          person.vehicleType!,
                        '${person.assistedIncidents} assists',
                        '${person.thanksCount} reviews',
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 8,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.star, size: 11, color: kStar),
              const SizedBox(width: 3),
              Text(
                person.rating?.toStringAsFixed(1) ?? '—',
                style: const TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const Icon(Icons.chevron_right, size: 16),
            ],
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
        margin: const EdgeInsets.only(bottom: 6),
      );
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.entry, required this.onTap});

  final VolunteerCommunityEntry entry;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => DCard(
        InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const CircleAvatar(
                    radius: 11,
                    backgroundColor: Color(0xFFD9D9D9),
                    child: Icon(Icons.person, size: 13, color: Colors.white),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          entry.volunteerName,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          entry.vehicleType ?? 'Volunteer assistance',
                          style: const TextStyle(
                            fontSize: 7,
                            color: Colors.black54,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Stars(entry.rating, size: 9),
                ],
              ),
              if (entry.review.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  '"${entry.review}"',
                  style: const TextStyle(fontSize: 9),
                ),
              ],
              const SizedBox(height: 6),
              Text(
                'From ${entry.reviewerName} · ${PhilippineTime.clock(entry.createdAt)}',
                style: const TextStyle(fontSize: 7, color: Colors.black54),
              ),
            ],
          ),
        ),
        margin: const EdgeInsets.only(bottom: 8),
      );
}

class _CommunityError extends StatelessWidget {
  const _CommunityError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Column(
        children: [
          Text(
            message,
            style: const TextStyle(fontSize: 9, color: kSos),
          ),
          TextButton(onPressed: onRetry, child: const Text('RETRY')),
        ],
      );
}
