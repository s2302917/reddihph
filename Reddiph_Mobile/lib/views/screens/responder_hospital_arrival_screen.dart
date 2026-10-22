import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../models/hospital_arrival_summary.dart';
import '../../services/mobile_api_client.dart';
import 'shell.dart';

class ResponderHospitalArrivalScreen extends StatefulWidget {
  const ResponderHospitalArrivalScreen({
    super.key,
    required this.incidentId,
    required this.summary,
    this.apiClient,
  });

  final int incidentId;
  final HospitalArrivalSummary summary;
  final MobileApiClient? apiClient;

  @override
  State<ResponderHospitalArrivalScreen> createState() =>
      _ResponderHospitalArrivalScreenState();
}

class _ResponderHospitalArrivalScreenState
    extends State<ResponderHospitalArrivalScreen> {
  static const _brand = Color(0xFF9D3E1D);
  static const _success = Color(0xFF76B968);
  static const _muted = Color(0xFF777777);
  static const _line = Color(0xFFD8D6D4);
  late final MobileApiClient _apiClient = widget.apiClient ?? MobileApiClient.I;
  late int _rating = widget.summary.volunteerReviewRating ?? 4;
  bool _sendingThanks = false;
  bool _thanksSent = false;
  String? _thanksError;

  static String _time(DateTime value) {
    final hour = value.hour % 12 == 0 ? 12 : value.hour % 12;
    final minute = value.minute.toString().padLeft(2, '0');
    return '$hour:$minute ${value.hour >= 12 ? 'PM' : 'AM'}';
  }

  void _openTab(BuildContext context, int tab) {
    Navigator.of(context).pushAndRemoveUntil<void>(
      MaterialPageRoute<void>(builder: (_) => Shell(initialTab: tab)),
      (_) => false,
    );
  }

  Future<void> _sendThanks() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || token.isEmpty) {
      setState(() => _thanksError = 'Please sign in again to send thanks.');
      return;
    }

    setState(() {
      _sendingThanks = true;
      _thanksError = null;
    });
    try {
      await _apiClient.responderIncidentAction(
        token: token,
        incidentId: widget.incidentId,
        action: 'thank_volunteer',
        rating: _rating,
      );
      if (mounted) setState(() => _thanksSent = true);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _thanksError = error.message);
    } finally {
      if (mounted) setState(() => _sendingThanks = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = widget.summary;
    final entries = <(String, String)>[
      ('Dispatched', _time(summary.dispatchedAt)),
      (
        'Patient picked up',
        summary.patientPickedUpAt == null
            ? 'Not recorded'
            : _time(summary.patientPickedUpAt!),
      ),
      ('Arrived at hospital', _time(summary.arrivedAt)),
      ('Response time', '${summary.responseTimeMinutes} min'),
    ];

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: IntrinsicHeight(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(22, 58, 22, 18),
                  child: Column(
                    children: [
                      Container(
                        width: 78,
                        height: 78,
                        decoration: const BoxDecoration(
                          color: _success,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.check,
                          color: Colors.white,
                          size: 42,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Arrived at the hospital',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.black,
                          fontSize: 16,
                          height: 1.1,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${summary.hospitalName} · ${_time(summary.arrivedAt)} · Arrival confirmed',
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _muted,
                          fontSize: 8,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: _line),
                          borderRadius: BorderRadius.circular(9),
                        ),
                        padding: const EdgeInsets.fromLTRB(20, 8, 12, 8),
                        child: Column(
                          children: [
                            for (var index = 0;
                                index < entries.length;
                                index++) ...[
                              if (index > 0)
                                const Divider(height: 1, color: _line),
                              Padding(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 6),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        entries[index].$1,
                                        style: const TextStyle(
                                          color: Colors.black,
                                          fontSize: 9,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                    Text(
                                      entries[index].$2,
                                      style: const TextStyle(
                                        color: _muted,
                                        fontSize: 8,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      if (summary.hasVolunteer) ...[
                        const SizedBox(height: 8),
                        _volunteerThanksCard(summary),
                        const SizedBox(height: 6),
                        SizedBox(
                          width: double.infinity,
                          height: 34,
                          child: OutlinedButton(
                            onPressed: () => _openTab(context, 0),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.black,
                              side: const BorderSide(color: _line),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(18),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            child: const Text('DONE'),
                          ),
                        ),
                      ] else ...[
                        const SizedBox(height: 8),
                        SizedBox(
                          width: double.infinity,
                          height: 40,
                          child: FilledButton(
                            onPressed: () => _openTab(context, 0),
                            style: FilledButton.styleFrom(
                              backgroundColor: _brand,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            child: const Text('DONE'),
                          ),
                        ),
                      ],
                      const Spacer(),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          margin: const EdgeInsets.fromLTRB(28, 4, 28, 8),
          padding: const EdgeInsets.symmetric(vertical: 3),
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: const Color(0xFFE1DFDD)),
            borderRadius: BorderRadius.circular(24),
          ),
          child: Row(
            children: [
              _NavItem(
                icon: Icons.home,
                label: 'Home',
                selected: true,
                onTap: () => _openTab(context, 0),
              ),
              _NavItem(
                icon: Icons.update,
                label: 'Incoming',
                onTap: () => _openTab(context, 1),
              ),
              _NavItem(
                icon: Icons.map,
                label: 'Map',
                onTap: () => _openTab(context, 2),
              ),
              _NavItem(
                icon: Icons.account_circle,
                label: 'Profile',
                onTap: () => _openTab(context, 3),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _volunteerThanksCard(HospitalArrivalSummary summary) {
    final name = summary.volunteerName!;
    final vehicleType = summary.volunteerVehicleType?.trim();
    final helperDescription = vehicleType == null || vehicleType.isEmpty
        ? 'Volunteer who helped with transport'
        : 'Volunteer who brought ${_articleFor(vehicleType)} $vehicleType';
    final alreadyRated = summary.volunteerReviewRating != null;
    final canSend =
        summary.canThankVolunteer && !_thanksSent && !_sendingThanks;
    final sent = _thanksSent || alreadyRated;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: _line),
        borderRadius: BorderRadius.circular(10),
      ),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Thank $name',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            helperDescription,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, fontSize: 7),
          ),
          const SizedBox(height: 3),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var star = 1; star <= 5; star++)
                Semantics(
                  button: true,
                  label: '$star ${star == 1 ? 'star' : 'stars'}',
                  child: IconButton(
                    key: ValueKey('volunteer-rating-$star'),
                    onPressed: canSend
                        ? () => setState(() {
                              _rating = star;
                              _thanksError = null;
                            })
                        : null,
                    constraints:
                        const BoxConstraints.tightFor(width: 25, height: 24),
                    padding: EdgeInsets.zero,
                    visualDensity: VisualDensity.compact,
                    icon: Icon(
                      Icons.star,
                      size: 16,
                      color: star <= _rating
                          ? const Color(0xFFFFA500)
                          : Colors.black,
                    ),
                  ),
                ),
            ],
          ),
          if (_thanksError != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text(
                _thanksError!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red, fontSize: 8),
              ),
            ),
          SizedBox(
            width: double.infinity,
            height: 30,
            child: FilledButton(
              onPressed: canSend ? _sendThanks : null,
              style: FilledButton.styleFrom(
                backgroundColor: _brand,
                disabledBackgroundColor: sent ? _brand : _line,
                foregroundColor: Colors.white,
                disabledForegroundColor: sent ? Colors.white : _muted,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                textStyle: const TextStyle(
                  fontSize: 7,
                  fontWeight: FontWeight.w700,
                ),
              ),
              child: _sendingThanks
                  ? const SizedBox(
                      width: 12,
                      height: 12,
                      child: CircularProgressIndicator(
                        strokeWidth: 1.5,
                        color: Colors.white,
                      ),
                    )
                  : Text(sent ? 'THANKS SENT' : 'SEND THANKS'),
            ),
          ),
        ],
      ),
    );
  }

  String _articleFor(String vehicleType) {
    final firstLetter = vehicleType.trimLeft().toLowerCase();
    return firstLetter.startsWith('a') ||
            firstLetter.startsWith('e') ||
            firstLetter.startsWith('i') ||
            firstLetter.startsWith('o') ||
            firstLetter.startsWith('u')
        ? 'an'
        : 'a';
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.selected = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool selected;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Semantics(
          button: true,
          selected: selected,
          label: label,
          child: InkWell(
            borderRadius: BorderRadius.circular(20),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    icon,
                    size: 13,
                    color: selected ? Colors.black : const Color(0xFF9A9A9A),
                  ),
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 7,
                      height: 1.1,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      color: selected ? Colors.black : const Color(0xFF777777),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
}
