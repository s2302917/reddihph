import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../screens/doctor_case_detail.dart';
import '../widgets/common.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/philippine_clock.dart';

class DoctorHome extends StatefulWidget {
  const DoctorHome({super.key});

  @override
  State<DoctorHome> createState() => _DoctorHomeState();
}

class _DoctorHomeState extends State<DoctorHome> {
  DoctorProfile? _profile;
  List<DoctorCaseRecord> _cases = const [];
  List<DoctorCaseRecord> _reviewCases = const [];
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
      final dashboard =
          await MobileApiClient.I.doctorDashboard(token: token);
      if (!mounted) return;
      setState(() {
        _profile = dashboard.profile;
        _cases = dashboard.cases;
        _reviewCases = dashboard.reviewCases;
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
  Widget build(BuildContext context) => PhilippineClock(
        builder: (context, time) => _buildContent(context, time),
      );

  Widget _buildContent(BuildContext context, DateTime time) {
    final profile = _profile;
    if (_loading && profile == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (profile == null) {
      return _loadError(_error ?? 'Doctor dashboard is unavailable.');
    }

    final incoming = _cases.where((item) => item.isIncoming).toList();
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
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
                      Text(PhilippineTime.greeting(time),
                          style: const TextStyle(fontSize: 11)),
                      Text(profile.greetingName,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          )),
                      Text(profile.hospitalName,
                          style: const TextStyle(
                              fontSize: 9, color: Colors.black54)),
                    ],
                  ),
                ),
                _profileAvatar(profile),
              ],
            ),
          ),
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 20),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              gradient: kNavyGradient,
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        profile.available ? 'Available for duty' : 'Off duty',
                        style: const TextStyle(
                            fontSize: 9, color: Colors.white70),
                      ),
                      const SizedBox(height: 14),
                      Text(time.day.toString().padLeft(2, '0'),
                          style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                              color: Colors.white)),
                      Text(PhilippineTime.monthYearDay(time),
                          style: const TextStyle(
                              fontSize: 9, color: Colors.white)),
                      Text(PhilippineTime.clock(time),
                          style: const TextStyle(
                              fontSize: 9, color: Colors.white70)),
                    ],
                  ),
                ),
                const Icon(Icons.medical_services_outlined,
                    size: 38, color: Colors.white70),
              ],
            ),
          ),
          const Section('Incoming patients'),
          if (_loading)
            const LinearProgressIndicator(minHeight: 2)
          else if (incoming.isEmpty)
            _empty('No incoming patients have been assigned.')
          else ...[
            for (final item in incoming.take(3))
              _incomingCard(context, item),
          ],
          const Section('Case review'),
          if (_reviewCases.isEmpty)
            _empty('There are no cases waiting for your review.')
          else
            Box(
              Column(
                children: [
                  for (int index = 0; index < _reviewCases.length; index++) ...[
                    if (index > 0) const Divider(height: 1),
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        _reviewCases[index].title,
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(
                        '${_reviewCases[index].patient} · '
                        '${_reviewCases[index].location}',
                        style: const TextStyle(fontSize: 9),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      trailing: const Icon(Icons.chevron_right, size: 16),
                      onTap: () => Nav.push(
                        context,
                        DoctorCaseDetail(
                          incidentId: _reviewCases[index].id,
                          initialItem: _reviewCases[index],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          if (_error != null) _errorMessage(_error!, _load),
        ],
      ),
    );
  }

  Widget _incomingCard(BuildContext context, DoctorCaseRecord item) {
    final severity = doctorSeverity(item.statusLabel);
    return GestureDetector(
      onTap: () => Nav.push(
        context,
        DoctorCaseDetail(incidentId: item.id, initialItem: item),
      ),
      child: Container(
        margin: const EdgeInsets.fromLTRB(20, 0, 20, 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: kSlate,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: SmallPill(item.statusLabel, severity),
            ),
            Text(
              '${item.title} · ${item.patient}',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Colors.white,
              ),
            ),
            Text(
              '${item.location} · ${item.etaLabel}',
              style: const TextStyle(fontSize: 9, color: Colors.white70),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 12),
            VitalsRow(
              pulse: item.heartRate?.toString() ?? '—',
              spo2: item.spo2 == null ? '—' : '${item.spo2}%',
              bp: item.bloodPressure ?? '—',
              blood: '—',
            ),
            const SizedBox(height: 5),
            Text(
              'Responder: ${item.responder}',
              style: const TextStyle(fontSize: 8, color: Colors.white70),
            ),
          ],
        ),
      ),
    );
  }

  Widget _profileAvatar(DoctorProfile profile) {
    final image = profile.profileImage;
    final uri = image == null ? null : Uri.tryParse(image);
    final remoteImage =
        uri != null && (uri.scheme == 'https' || uri.scheme == 'http')
            ? image
            : null;
    return CircleAvatar(
      radius: 19,
      backgroundColor: kNavy,
      foregroundImage: remoteImage == null ? null : NetworkImage(remoteImage),
      onForegroundImageError: remoteImage == null ? null : (_, __) {},
      child: Text(profile.initial,
          style: const TextStyle(color: Colors.white, fontSize: 12)),
    );
  }

  Widget _loadError(String message) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 11, color: kSos)),
              const SizedBox(height: 8),
              OutlinedButton(onPressed: _load, child: const Text('RETRY')),
            ],
          ),
        ),
      );

  Widget _errorMessage(String message, VoidCallback retry) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 10),
        child: Row(
          children: [
            Expanded(
              child: Text(message,
                  style: const TextStyle(fontSize: 9, color: kSos)),
            ),
            TextButton(onPressed: retry, child: const Text('RETRY')),
          ],
        ),
      );

  Widget _empty(String message) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
        child: Text(message,
            style: const TextStyle(fontSize: 10, color: Colors.black54)),
      );
}
