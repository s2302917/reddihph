import 'dart:async';

import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/role.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/volunteer_widgets.dart';
import '../widgets/philippine_clock.dart';

class VolunteerHome extends StatefulWidget {
  final Role role;
  final VoidCallback? onOpenMap;

  const VolunteerHome({super.key, required this.role, this.onOpenMap});

  @override
  State<VolunteerHome> createState() => _VolunteerHomeState();
}

class _VolunteerHomeState extends State<VolunteerHome>
    with WidgetsBindingObserver {
  List<VolunteerAssistanceRequest> _requests = [];
  Timer? _refreshTimer;
  String? _error;
  int? _actingOnRequest;
  bool _loading = true;
  bool _requestRefreshInFlight = false;
  bool _profileLoading = true;
  String? _profileError;
  Map<String, dynamic> _profile = const {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadRequests();
    _loadProfile();
    _refreshTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      if (WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed) {
        _loadRequests();
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _loadRequests();
      _loadProfile();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadRequests() async {
    if (_requestRefreshInFlight) return;
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      if (mounted) {
        setState(() {
          _error = 'Sign in again to load volunteer requests.';
          _loading = false;
        });
      }
      return;
    }
    _requestRefreshInFlight = true;
    try {
      final requests = await MobileApiClient.I.volunteerRequests(token: token);
      if (!mounted) return;
      setState(() {
        _requests = requests;
        _error = null;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } finally {
      _requestRefreshInFlight = false;
    }
  }

  Future<void> _respond(
    VolunteerAssistanceRequest request,
    String action,
  ) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || _actingOnRequest != null) return;
    setState(() => _actingOnRequest = request.assistanceId);
    try {
      await MobileApiClient.I.respondToVolunteerRequest(
        token: token,
        assistanceId: request.assistanceId,
        action: action,
      );
      final user = AuthController.I.user;
      if (user != null && action == 'accept') {
        user['is_available'] = false;
      } else if (user != null && action == 'complete') {
        user['is_available'] = true;
      }
      await _loadRequests();
      await _loadProfile();
      if (action == 'accept' && mounted) widget.onOpenMap?.call();
      if (mounted) {
        _message(switch (action) {
          'accept' => 'Volunteer request accepted.',
          'decline' => 'Volunteer request declined.',
          _ => 'Assistance marked complete.',
        });
      }
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
      await _loadRequests();
    } finally {
      if (mounted) setState(() => _actingOnRequest = null);
    }
  }

  Future<void> _loadProfile() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      if (mounted) {
        setState(() {
          _profileError = 'Sign in again to load your volunteer profile.';
          _profileLoading = false;
        });
      }
      return;
    }
    try {
      final profile = await MobileApiClient.I.volunteerDashboard(token: token);
      if (!mounted) return;
      AuthController.I.user?.addAll({
        'full_name': profile['full_name'],
        'vehicle_type': profile['vehicle_type'],
        'is_available': profile['is_available'],
        'volunteer_rating': profile['rating'],
        'volunteer_assisted_incidents': profile['assisted_incidents'],
      });
      setState(() {
        _profile = profile;
        _profileError = null;
        _profileLoading = false;
      });
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _profileError = error.message;
          _profileLoading = false;
        });
      }
    }
  }

  void _message(String text) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  Widget build(BuildContext context) {
    final profile = DashboardController.I.profileFor('Volunteer');
    final user = AuthController.I.user ?? const <String, dynamic>{};
    final fullName = (_profile['full_name'] as String? ??
            user['full_name'] as String? ??
            profile.title.replaceFirst('Hi, ', ''))
        .trim();
    final name = fullName.split(RegExp(r'\s+')).firstOrNull ?? fullName;
    final initial = name.isEmpty ? profile.initial : name[0].toUpperCase();
    final rawRating = _profile['rating'] ?? user['volunteer_rating'];
    final rating = rawRating is num ? rawRating : null;
    final assisted = (_profile['assisted_incidents'] as num? ??
            user['volunteer_assisted_incidents'] as num? ??
            0)
        .toInt();
    final now = PhilippineTime.now();
    return ListView(
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
                    PhilippineClock(
                      builder: (_, time) => Text(
                        PhilippineTime.greeting(time),
                        style: const TextStyle(fontSize: 11),
                      ),
                    ),
                    Text(name,
                        style: const TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
                    Text(
                      '${rating?.toStringAsFixed(1) ?? '—'} ★  $assisted assists',
                      style: const TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
              CircleAvatar(
                radius: 14,
                backgroundColor: kVol,
                child: Text(initial,
                    style: const TextStyle(color: Colors.white, fontSize: 12)),
              ),
            ],
          ),
        ),
        Container(
          margin: const EdgeInsets.symmetric(horizontal: 20),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            gradient: kVolGradient,
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('On Duty',
                        style: TextStyle(fontSize: 9, color: Colors.white70)),
                    const SizedBox(height: 10),
                    Text(now.day.toString().padLeft(2, '0'),
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white)),
                    Text(
                      PhilippineTime.monthYearDay(now),
                      style: const TextStyle(fontSize: 9, color: Colors.white),
                    ),
                    Text(
                      PhilippineTime.clock(now),
                      style:
                          const TextStyle(fontSize: 9, color: Colors.white70),
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
                      Text(
                        rating?.toStringAsFixed(1) ?? '—',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 4),
                      const Icon(Icons.star, size: 22, color: Colors.white),
                    ],
                  ),
                  Text(
                    '$assisted assists',
                    style: const TextStyle(fontSize: 8, color: Colors.white70),
                  ),
                ],
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 12, 8),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'Available requests near you',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
                ),
              ),
              IconButton(
                tooltip: 'Refresh requests',
                onPressed: _loading ? null : _loadRequests,
                icon: const Icon(Icons.refresh, size: 19),
              ),
            ],
          ),
        ),
        if (_profileError != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: _errorCard(_profileError!),
          )
        else if (_profileLoading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: _errorCard(_error!),
          )
        else if (_loading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 22),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_requests.isEmpty)
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 4, 20, 14),
            child: Text(
              'No pending requests. New resident requests will appear here while you are online.',
              style: TextStyle(fontSize: 10, color: Colors.black54),
            ),
          )
        else
          for (final request in _requests)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 9),
              child: _requestCard(request),
            ),
        const Padding(
          padding: EdgeInsets.fromLTRB(20, 16, 20, 8),
          child: Text(
            'Recent feedback',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
          ),
        ),
        if (assisted > 0 && rating != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border.all(color: Colors.black12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.star, color: Color(0xFF17144D), size: 15),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      '${rating.toStringAsFixed(1)} · Based on $assisted completed assists',
                      style: const TextStyle(fontSize: 9),
                    ),
                  ),
                ],
              ),
            ),
          )
        else
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 0, 20, 12),
            child: Text(
              'Feedback from residents will appear here after an assistance is completed.',
              style: TextStyle(fontSize: 9, color: Colors.black54),
            ),
          ),
      ],
    );
  }

  Widget _requestCard(VolunteerAssistanceRequest request) {
    final accepted = request.status == 'accepted';
    final busy = _actingOnRequest == request.assistanceId;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: Colors.black12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${request.incidentType} · ${request.severity.toUpperCase()}',
                  style: const TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w800),
                ),
              ),
              Text(
                accepted ? 'ACCEPTED' : 'NEW REQUEST',
                style: TextStyle(
                  color: accepted ? kVol : kSos,
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            '${request.patientName}'
            '${request.patientAge == null ? '' : ', ${request.patientAge}'}'
            ' · ${request.location}',
            style: const TextStyle(fontSize: 10, color: Colors.black54),
          ),
          if (request.notes.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(request.notes,
                style: const TextStyle(fontSize: 10, color: Colors.black87)),
          ],
          const SizedBox(height: 10),
          if (accepted)
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: busy ? null : () => _respond(request, 'complete'),
                style: FilledButton.styleFrom(backgroundColor: kVol),
                child: busy
                    ? const SizedBox(
                        width: 17,
                        height: 17,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('MARK ASSISTANCE COMPLETE'),
              ),
            )
          else
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: busy ? null : () => _respond(request, 'decline'),
                    child: const Text('DECLINE'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: FilledButton(
                    onPressed: busy ? null : () => _respond(request, 'accept'),
                    style: FilledButton.styleFrom(backgroundColor: kVol),
                    child: busy
                        ? const SizedBox(
                            width: 17,
                            height: 17,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : Text(request.needsVehicle
                            ? 'OFFER MY VEHICLE'
                            : 'ASSIST NOW'),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _errorCard(String message) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF0EF),
          borderRadius: BorderRadius.circular(9),
        ),
        child: Row(
          children: [
            Expanded(
                child: Text(message, style: const TextStyle(fontSize: 11))),
            TextButton(
              onPressed: _loading ? null : _loadRequests,
              child: const Text('RETRY'),
            ),
          ],
        ),
      );
}
