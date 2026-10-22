import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../models/volunteer.dart';
import '../../services/mobile_api_client.dart';
import 'sos_screen.dart';

const _volunteerBrown = Color(0xFF9F3D1F);
const _volunteerMuted = Color(0xFFB46B52);
const _volunteerHint = Color(0xFFD8ADA0);

class VolunteerPickerScreen extends StatefulWidget {
  const VolunteerPickerScreen({
    required this.token,
    required this.latitude,
    required this.longitude,
    this.apiClient,
    super.key,
  });

  final String token;
  final double latitude;
  final double longitude;
  final MobileApiClient? apiClient;

  @override
  State<VolunteerPickerScreen> createState() => _VolunteerPickerScreenState();
}

class _VolunteerPickerScreenState extends State<VolunteerPickerScreen> {
  List<VolunteerProfile> _volunteers = [];
  VolunteerProfile? _selected;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadRoster();
  }

  Future<void> _loadRoster() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final volunteers =
          await (widget.apiClient ?? MobileApiClient.I).volunteersForIncident(
        token: widget.token,
        latitude: widget.latitude,
        longitude: widget.longitude,
      );
      if (!mounted) return;
      setState(() {
        _volunteers = volunteers;
        _selected =
            volunteers.where((volunteer) => volunteer.available).firstOrNull;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    }
  }

  void _continue() {
    final selected = _selected;
    if (selected != null) Navigator.pop(context, selected);
  }

  void _openSos() {
    final role = AuthController.I.role;
    if (role != null) Nav.push(context, SosScreen(role: role));
  }

  @override
  Widget build(BuildContext context) {
    final screenHeight = MediaQuery.sizeOf(context).height;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Stack(
          children: [
            Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(30, 12, 30, 20),
                    children: [
                      Align(
                        alignment: Alignment.centerLeft,
                        child: IconButton(
                          onPressed: () => Navigator.maybePop(context),
                          visualDensity: VisualDensity.compact,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints.tightFor(
                              width: 34, height: 34),
                          icon: const Icon(Icons.arrow_back_ios_new, size: 18),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Ask a volunteer for help',
                        style: TextStyle(
                          fontSize: 19,
                          height: 1.1,
                          fontWeight: FontWeight.w800,
                          color: Colors.black,
                        ),
                      ),
                      const SizedBox(height: 3),
                      const Text(
                        'Optional - for transport to the hospital',
                        style: TextStyle(
                          fontSize: 9,
                          color: Colors.black45,
                        ),
                      ),
                      const SizedBox(height: 16),
                      const _ReportProgress(),
                      const SizedBox(height: 12),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: _volunteerBrown,
                            borderRadius: BorderRadius.circular(7),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            child: Text(
                              'Volunteers ${_volunteers.length}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (_loading)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 30),
                          child: Center(
                            child: CircularProgressIndicator(
                              color: _volunteerBrown,
                            ),
                          ),
                        )
                      else if (_error != null)
                        _RosterError(message: _error!, onRetry: _loadRoster)
                      else if (_volunteers.isEmpty)
                        const _NoVolunteers()
                      else
                        for (final volunteer in _volunteers) ...[
                          _VolunteerCard(
                            volunteer: volunteer,
                            selected: volunteer.id == _selected?.id,
                            onTap: volunteer.available
                                ? () => setState(() => _selected = volunteer)
                                : null,
                          ),
                          const SizedBox(height: 6),
                        ],
                        const SizedBox(height: 2),
                        const _ApprovalHint(),
                        const SizedBox(height: 8),
                        SizedBox(
                          height: 42,
                          width: double.infinity,
                          child: FilledButton(
                            onPressed:
                                _loading || _error != null || _selected == null
                                    ? null
                                    : _continue,
                            style: FilledButton.styleFrom(
                              backgroundColor: _volunteerBrown,
                              disabledBackgroundColor: _volunteerBrown,
                              disabledForegroundColor: Colors.white54,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(11),
                              ),
                            ),
                            child: const Text(
                              'NEXT: REVIEW AND SEND',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
            Positioned(
              right: 14,
              bottom: screenHeight * .22,
              child: SizedBox(
                width: 58,
                height: 58,
                child: FloatingActionButton(
                  heroTag: 'volunteer-picker-sos',
                  onPressed: _openSos,
                  backgroundColor: const Color(0xFF8D0000),
                  shape: const CircleBorder(),
                  elevation: 5,
                  child: const Text(
                    'SOS',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReportProgress extends StatelessWidget {
  const _ReportProgress();

  static const _steps = ['Incident', 'Patient', 'Help', 'Send'];

  @override
  Widget build(BuildContext context) => Row(
        children: [
          for (var index = 0; index < _steps.length; index++) ...[
            Expanded(
              child: Column(
                children: [
                  Container(
                    height: 7,
                    decoration: BoxDecoration(
                      color:
                          index < 3 ? _volunteerBrown : const Color(0xFFE4E1DF),
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    _steps[index],
                    style: const TextStyle(
                      color: _volunteerMuted,
                      fontSize: 8,
                    ),
                  ),
                ],
              ),
            ),
            if (index < _steps.length - 1) const SizedBox(width: 4),
          ],
        ],
      );
}

class _VolunteerCard extends StatelessWidget {
  const _VolunteerCard({
    required this.volunteer,
    required this.selected,
    required this.onTap,
  });

  final VolunteerProfile volunteer;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final distance = volunteer.distanceKm;
    final meta = [
      volunteer.available ? 'Volunteer with vehicle' : 'Currently unavailable',
      if (volunteer.rating != null) '${volunteer.rating!.toStringAsFixed(1)} ★',
      '${volunteer.assistedIncidents} assists',
    ].join(' · ');

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(7),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(7),
        child: Container(
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
          decoration: BoxDecoration(
            border: Border.all(
              color: selected ? _volunteerHint : const Color(0xFFE3E3E3),
              width: selected ? 2 : 1,
            ),
            borderRadius: BorderRadius.circular(7),
          ),
          child: Row(
            children: [
              SizedBox(
                width: 32,
                child: Center(
                  child: Container(
                    width: 17,
                    height: 17,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color:
                            selected ? const Color(0xFF8D0000) : Colors.black45,
                        width: 1.6,
                      ),
                      color: selected
                          ? const Color(0xFF8D0000)
                          : Colors.transparent,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      volunteer.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        color: Colors.black,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$meta${volunteer.vehicleType == 'Not provided' ? '' : ' · ${volunteer.vehicleType}'}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 7,
                        height: 1.1,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                distance == null ? '—' : '${distance.toStringAsFixed(1)} km',
                style: const TextStyle(
                  fontSize: 8,
                  fontWeight: FontWeight.w700,
                  color: Colors.black,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ApprovalHint extends StatelessWidget {
  const _ApprovalHint();

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: _volunteerHint,
          borderRadius: BorderRadius.circular(7),
        ),
        child: const Text(
          'Only registered and approved volunteers appear here.\n'
          'They can say yes or no.',
          style: TextStyle(
            color: _volunteerMuted,
            fontSize: 7,
            height: 1.25,
          ),
        ),
      );
}

class _RosterError extends StatelessWidget {
  const _RosterError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF0ED),
          borderRadius: BorderRadius.circular(7),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(message, style: const TextStyle(fontSize: 10)),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: onRetry,
                child: const Text('RETRY'),
              ),
            ),
          ],
        ),
      );
}

class _NoVolunteers extends StatelessWidget {
  const _NoVolunteers();

  @override
  Widget build(BuildContext context) => const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Text(
          'No registered volunteers are available right now.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 10, color: Colors.black54),
        ),
      );
}
