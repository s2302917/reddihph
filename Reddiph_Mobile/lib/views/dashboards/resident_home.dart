import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/emergency.dart';
import '../../models/role.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../screens/tracking_screen.dart';

class ResidentHome extends StatelessWidget {
  final Role role;
  final VoidCallback onSos;
  final VoidCallback onReportIncident;
  final VoidCallback? onViewLiveStatus;
  const ResidentHome({
    super.key,
    required this.role,
    required this.onSos,
    required this.onReportIncident,
    this.onViewLiveStatus,
  });

  @override
  Widget build(BuildContext context) {
    final user = AuthController.I.user ?? const <String, dynamic>{};
    final fullName = (user['full_name'] as String? ?? '').trim();
    final firstName =
        fullName.isEmpty ? 'Resident' : fullName.split(RegExp(r'\s+')).first;
    final initial =
        fullName.isEmpty ? 'R' : fullName.characters.first.toUpperCase();
    final barangay = (user['barangay_name'] as String? ?? '').trim();
    final barangayLabel = barangay.isEmpty
        ? 'Barangay not set'
        : RegExp(r'^(brgy\.?|barangay)(?:\s|$)', caseSensitive: false)
                .hasMatch(barangay)
            ? barangay
            : 'Brgy. $barangay';

    // Only ReportSection listens to the SOS controller, so the header and the
    // SOS button are not rebuilt every time the emergency stage changes.
    return ListView(
      children: [
        Header(
          'Hi, $firstName',
          '$barangayLabel\nReport an emergency or check your case status',
          role.color,
          initial,
        ),
        const SizedBox(height: 10),
        SosButton(onSos: onSos),
        const Padding(
          padding: EdgeInsets.all(14),
          child: Text(
            'Sends your location to your barangay team, '
            'then the nearest available responder.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 9, color: Colors.black38),
          ),
        ),
        ListenableBuilder(
          listenable: SosController.I,
          builder: (context, _) {
            final sos = SosController.I;
            final hasActiveIncident = sos.stage != EmergencyStage.idle &&
                sos.stage != EmergencyStage.completed;
            if (hasActiveIncident) return const SizedBox.shrink();
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: OutlinedButton(
                onPressed: onReportIncident,
                style: OutlinedButton.styleFrom(
                  foregroundColor: role.color,
                  side: BorderSide(color: role.color.withAlpha(150)),
                  minimumSize: const Size.fromHeight(44),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: const Text(
                  'Report for someone else',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            );
          },
        ),
        const SizedBox(height: 14),
        ReportSection(
          role: role,
          onViewLiveStatus: onViewLiveStatus,
        ),
      ],
    );
  }
}

/// Hold-to-send button: the ring fills while the button is held and the alert
/// is only sent once the full [holdDuration] has passed.
class SosButton extends StatefulWidget {
  static const holdDuration = Duration(seconds: 3);

  final VoidCallback onSos;
  const SosButton({super.key, required this.onSos});

  @override
  State<SosButton> createState() => _SosButtonState();
}

class _SosButtonState extends State<SosButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _hold =
      AnimationController(vsync: this, duration: SosButton.holdDuration)
        ..addStatusListener(_onHoldStatus);

  void _onHoldStatus(AnimationStatus status) {
    if (status != AnimationStatus.completed) return;
    HapticFeedback.heavyImpact();
    _hold.value = 0;
    widget.onSos();
  }

  void _start() => _hold.forward();

  void _cancel() {
    if (_hold.isAnimating) _hold.reverse();
  }

  @override
  void dispose() {
    _hold.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Center(
        child: PulseRing(
          color: kSos,
          size: 220,
          child: Semantics(
            button: true,
            label:
                'SOS. Press and hold for 3 seconds to send an emergency alert',
            child: Listener(
              onPointerDown: (_) => _start(),
              onPointerUp: (_) => _cancel(),
              onPointerCancel: (_) => _cancel(),
              child: AnimatedBuilder(
                animation: _hold,
                builder: (context, _) => Transform.scale(
                  scale: 1 - .07 * _hold.value,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      const _SosFace(),
                      SizedBox(
                        width: 200,
                        height: 200,
                        child: CircularProgressIndicator(
                          value: _hold.value,
                          strokeWidth: 8,
                          color: Colors.white,
                          backgroundColor: Colors.transparent,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
}

class _SosFace extends StatelessWidget {
  const _SosFace();

  @override
  Widget build(BuildContext context) => const DecoratedBox(
        decoration: BoxDecoration(color: kSos, shape: BoxShape.circle),
        child: SizedBox(
          width: 220,
          height: 220,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('SOS',
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 30,
                      fontWeight: FontWeight.bold)),
              Text('HOLD 3 SECONDS TO SEND',
                  style: TextStyle(color: Colors.white70, fontSize: 9)),
            ],
          ),
        ),
      );
}

class ReportSection extends StatelessWidget {
  final Role role;
  final VoidCallback? onViewLiveStatus;
  const ReportSection({
    super.key,
    required this.role,
    this.onViewLiveStatus,
  });

  @override
  Widget build(BuildContext context) {
    final sos = SosController.I;

    return ListenableBuilder(
      listenable: sos,
      builder: (context, _) {
        final incident = sos.details;
        final active = incident != null &&
            sos.stage != EmergencyStage.idle &&
            sos.stage != EmergencyStage.completed;
        final showVolunteerFollowup = sos.stage == EmergencyStage.completed &&
            incident?.volunteerName != null &&
            incident?.volunteerAssistanceStatus != 'declined';
        if (incident == null || (!active && !showVolunteerFollowup)) {
          return const SizedBox.shrink();
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Section(
              active ? 'Your active report' : 'Completed emergency report',
            ),
            Box(
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          incident.type,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      Pill(sos.status, role.color.withAlpha(77), role.color),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      for (int i = 0; i < 4; i++) ...[
                        _StepDot(
                          done:
                              i < _statusStep(sos.stage, showVolunteerFollowup),
                          color:
                              i < _statusStep(sos.stage, showVolunteerFollowup)
                                  ? const Color(0xFF323C3C)
                                  : i ==
                                          _statusStep(
                                              sos.stage, showVolunteerFollowup)
                                      ? kSos
                                      : Colors.grey,
                        ),
                        if (i < 3)
                          const Expanded(
                            child: SizedBox(
                              height: 2,
                              child: ColoredBox(color: Colors.black26),
                            ),
                          ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            Box(
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${incident.responderName ?? 'Responder not assigned'}\n'
                      '${incident.etaMinutes == null ? 'ETA pending' : 'ETA ${incident.etaMinutes} min'}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  if (active && role.name == 'Resident')
                    SizedBox(
                      height: 34,
                      child: FilledButton(
                        onPressed: onViewLiveStatus ??
                            () => Nav.push(context, const TrackingScreen()),
                        style: FilledButton.styleFrom(
                          backgroundColor: kResidentBrand,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                        ),
                        child: const Text('VIEW LIVE STATUS'),
                      ),
                    ),
                ],
              ),
            ),
            if (incident.volunteerName != null) ...[
              Box(
                Row(
                  children: [
                    const Icon(Icons.support_agent,
                        color: kResidentBrand, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            incident.volunteerName!,
                            style: const TextStyle(
                                fontSize: 11, fontWeight: FontWeight.w700),
                          ),
                          Text(
                            _assistanceStatus(
                                incident.volunteerAssistanceStatus),
                            style: const TextStyle(
                                fontSize: 10, color: Colors.black54),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              if (incident.volunteerAssistanceStatus == 'completed' &&
                  incident.volunteerReviewRating == null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 14),
                  child: OutlinedButton.icon(
                    onPressed: () => _rateVolunteer(context, incident),
                    icon: const Icon(Icons.star_outline),
                    label: const Text('RATE VOLUNTEER'),
                  ),
                ),
              if (incident.volunteerReviewRating != null)
                Box(
                  Row(
                    children: [
                      const Icon(Icons.star, color: Colors.amber, size: 18),
                      const SizedBox(width: 6),
                      Text(
                        'Your rating: ${incident.volunteerReviewRating}/5',
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
            ],
            const SizedBox(height: 12),
          ],
        );
      },
    );
  }

  int _statusStep(EmergencyStage stage, bool completedFollowup) =>
      completedFollowup
          ? 4
          : switch (stage) {
              EmergencyStage.sending => 0,
              EmergencyStage.confirmed => 1,
              EmergencyStage.assigned => 2,
              EmergencyStage.tracking => 2,
              EmergencyStage.arrived => 3,
              EmergencyStage.idle || EmergencyStage.completed => 0,
            };

  String _assistanceStatus(String? status) => switch (status) {
        'requested' => 'Volunteer request sent',
        'accepted' => 'Volunteer accepted your request',
        'declined' => 'Volunteer was unable to assist',
        'completed' => 'Volunteer assistance complete',
        _ => 'Volunteer assistance requested',
      };

  Future<void> _rateVolunteer(
    BuildContext context,
    IncidentDetails incident,
  ) async {
    final reviewController = TextEditingController();
    var rating = 5;
    final result = await showDialog<(int, String)>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('Rate ${incident.volunteerName}'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var value = 1; value <= 5; value++)
                    IconButton(
                      tooltip: '$value stars',
                      onPressed: () => setDialogState(() => rating = value),
                      icon: Icon(
                        value <= rating ? Icons.star : Icons.star_outline,
                        color: Colors.amber[800],
                      ),
                    ),
                ],
              ),
              TextField(
                controller: reviewController,
                maxLength: 1000,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'Share optional feedback',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('CANCEL'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(
                dialogContext,
                (rating, reviewController.text.trim()),
              ),
              child: const Text('SUBMIT'),
            ),
          ],
        ),
      ),
    );
    reviewController.dispose();
    if (result == null) return;

    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      if (context.mounted) _showMessage(context, 'Sign in again to rate.');
      return;
    }
    try {
      await MobileApiClient.I.rateVolunteer(
        token: token,
        incidentId: incident.incidentId,
        rating: result.$1,
        review: result.$2,
      );
      await SosController.I.refreshDetails();
      if (context.mounted) _showMessage(context, 'Thank you for your rating.');
    } on MobileApiException catch (error) {
      if (context.mounted) _showMessage(context, error.message);
    }
  }

  void _showMessage(BuildContext context, String text) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }
}

/// Progress dot that animates its colour and pops the check mark in.
class _StepDot extends StatelessWidget {
  final bool done;
  final Color color;
  const _StepDot({required this.done, required this.color});

  @override
  Widget build(BuildContext context) => AnimatedContainer(
        duration: kMedium,
        curve: Curves.easeOut,
        width: 16,
        height: 16,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        child: AnimatedSwitcher(
          duration: kFast,
          transitionBuilder: (child, anim) =>
              ScaleTransition(scale: anim, child: child),
          child: done
              ? const Icon(Icons.check,
                  key: ValueKey('done'), size: 10, color: Colors.white)
              : const SizedBox.shrink(key: ValueKey('todo')),
        ),
      );
}
