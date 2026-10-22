import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/volunteer_widgets.dart';

class VolunteerRecognitionScreen extends StatefulWidget {
  const VolunteerRecognitionScreen({
    super.key,
    required this.volunteerId,
    required this.name,
  });

  final int volunteerId;
  final String name;

  @override
  State<VolunteerRecognitionScreen> createState() =>
      _VolunteerRecognitionScreenState();
}

class _VolunteerRecognitionScreenState
    extends State<VolunteerRecognitionScreen> {
  VolunteerDetails? _details;
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
        _error = 'Sign in again to load this volunteer profile.';
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final details = await MobileApiClient.I.volunteerDetails(
        token: token,
        volunteerId: widget.volunteerId,
      );
      if (mounted) {
        setState(() {
          _details = details;
          _loading = false;
        });
      }
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
    final details = _details;
    final name = details?.name ?? widget.name;
    final initial = name.isEmpty ? '?' : name[0].toUpperCase();

    return Scaffold(
      appBar: AppBar(title: const Text('Volunteer profile')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 10, 24, 30),
          children: [
            const SizedBox(height: 10),
            Center(
              child: CircleAvatar(
                radius: 34,
                backgroundColor: kVol,
                child: Text(
                  initial,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 25,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Center(
              child: Text(
                name,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            Center(
              child: Text(
                [
                  'Approved volunteer',
                  if (details?.vehicleType?.isNotEmpty == true)
                    details!.vehicleType!,
                ].join(' · '),
                style: const TextStyle(fontSize: 8, color: Colors.black54),
              ),
            ),
            const SizedBox(height: 20),
            if (_loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_error != null)
              _DetailsError(message: _error!, onRetry: _load)
            else if (details != null) ...[
              Row(
                children: [
                  _stat(details.rating?.toStringAsFixed(1) ?? '—', 'Avg. rating'),
                  const SizedBox(width: 8),
                  _stat('${details.assistedIncidents}', 'Assists'),
                  const SizedBox(width: 8),
                  _stat('${details.thanksThisMonth}', 'Reviews this month'),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                'Resident feedback (${details.thanksCount})',
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              if (details.reviews.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 10),
                  child: Text(
                    'No resident reviews yet.',
                    style: TextStyle(fontSize: 9, color: Colors.black54),
                  ),
                )
              else
                for (final review in details.reviews)
                  DCard(
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                review.reviewerName,
                                style: const TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            Stars(review.rating, size: 10),
                          ],
                        ),
                        if (review.review.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            review.review,
                            style: const TextStyle(fontSize: 9),
                          ),
                        ],
                        const SizedBox(height: 5),
                        Text(
                          '${review.createdAt.day}/${review.createdAt.month}/${review.createdAt.year} · ${PhilippineTime.clock(review.createdAt)}',
                          style: const TextStyle(
                            fontSize: 7,
                            color: Colors.black54,
                          ),
                        ),
                      ],
                    ),
                    margin: const EdgeInsets.only(bottom: 8),
                  ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _stat(String value, String label) => Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.black26),
            borderRadius: BorderRadius.circular(6),
          ),
          child: Column(
            children: [
              Text(
                value,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 7, color: Colors.black54),
              ),
            ],
          ),
        ),
      );
}

class _DetailsError extends StatelessWidget {
  const _DetailsError({required this.message, required this.onRetry});

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
