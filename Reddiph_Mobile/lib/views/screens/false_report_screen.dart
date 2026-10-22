import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../services/mobile_api_client.dart';

class FalseReportScreen extends StatefulWidget {
  final int incidentId;
  final String location;
  final MobileApiClient? apiClient;

  const FalseReportScreen({
    super.key,
    required this.incidentId,
    required this.location,
    this.apiClient,
  });

  @override
  State<FalseReportScreen> createState() => _FalseReportScreenState();
}

class _FalseReportScreenState extends State<FalseReportScreen> {
  static const _reasons = <(String, String)>[
    ('prank_or_test', 'Prank or test'),
    ('wrong_location', 'Wrong location'),
    ('already_resolved', 'Already resolved'),
    ('cannot_find_anyone', 'Cannot find anyone'),
  ];

  final _notes = TextEditingController();
  String? _reason;
  bool _submitting = false;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final reason = _reason;
    if (reason == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a reason to continue.')),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      await (widget.apiClient ?? MobileApiClient.I).submitFalseReport(
        token: AuthController.I.user?['token'] as String? ?? '',
        incidentId: widget.incidentId,
        reason: reason,
        notes: _notes.text,
      );
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pop(true);
      messenger.showSnackBar(
        const SnackBar(
          content:
              Text('Case closed and sent to your Barangay Admin for review.'),
          duration: Duration(seconds: 5),
        ),
      );
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: 'Go back',
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(Icons.arrow_back),
          ),
        ),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
            children: [
              const Text(
                'Close as false report?',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 4),
              Text(
                'Case #RS-${widget.incidentId} · ${widget.location}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12, color: Colors.black54),
              ),
              const SizedBox(height: 24),
              const Text('Reason',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final (value, label) in _reasons)
                    ChoiceChip(
                      label: Text(label),
                      selected: _reason == value,
                      onSelected: _submitting
                          ? null
                          : (_) => setState(() => _reason = value),
                      labelStyle: TextStyle(
                        fontSize: 12,
                        color: _reason == value ? Colors.white : Colors.black87,
                      ),
                      selectedColor: kResponderBrand,
                      backgroundColor: Colors.white,
                      side: BorderSide(
                        color:
                            _reason == value ? kResponderBrand : Colors.black12,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              const Text('Notes (optional)',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              TextField(
                controller: _notes,
                enabled: !_submitting,
                minLines: 3,
                maxLines: 5,
                maxLength: 2000,
                decoration: InputDecoration(
                  hintText: 'Add a brief explanation.',
                  hintStyle:
                      const TextStyle(fontSize: 12, color: Colors.black38),
                  contentPadding: const EdgeInsets.all(12),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: Colors.black12),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: Colors.black12),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _submitting ? null : _submit,
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF870000),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                  child: _submitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('CLOSE AS FALSE REPORT'),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                height: 44,
                child: OutlinedButton(
                  onPressed:
                      _submitting ? null : () => Navigator.of(context).pop(),
                  child: const Text('Go back'),
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF5E3E3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'The case is sent to your Barangay Admin for review. A warning or account ban is applied only if the admin confirms the report was false.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF6C2222)),
                ),
              ),
            ],
          ),
        ),
      );
}
