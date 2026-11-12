import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseCaseDetail extends StatefulWidget {
  const NurseCaseDetail({
    super.key,
    required this.item,
  });

  final NurseCaseRecord item;

  @override
  State<NurseCaseDetail> createState() => _NurseCaseDetailState();
}

class _NurseCaseDetailState extends State<NurseCaseDetail> {
  final _note = TextEditingController();
  NurseCaseRecord? _case;
  String? _error;
  bool _loading = true;
  bool _saving = false;

  String? get _token => AuthController.I.user?['token'] as String?;
  NurseCaseRecord get _record => _case ?? widget.item;
  bool get _readOnly => _error != null || _loading;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
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
      final record = await MobileApiClient.I.nurseCaseDetails(
        token: token,
        incidentId: widget.item.id,
      );
      if (!mounted) return;
      NursePortalCache.saveCase(token, record);
      setState(() {
        _case = record;
        _note.text = record.note;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      NurseCaseRecord? cachedCase;
      for (final item in NursePortalCache.cases(token) ?? const []) {
        if (item.id == widget.item.id) {
          cachedCase = item;
          break;
        }
      }
      setState(() {
        if (cachedCase != null) {
          _case = cachedCase;
          _note.text = cachedCase.note;
        }
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _run(
      Future<NurseCaseRecord> Function(String token) action) async {
    final token = _token;
    if (_readOnly || _saving || token == null) return;
    setState(() => _saving = true);
    try {
      final updated = await action(token);
      if (!mounted) return;
      NursePortalCache.saveCase(token, updated);
      setState(() {
        _case = updated;
        _note.text = updated.note;
        _saving = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _saving = false;
      });
      _message(error.message);
    }
  }

  Future<void> _saveNote() async {
    await _run((token) => MobileApiClient.I.saveNurseNote(
          token: token,
          incidentId: _record.id,
          note: _note.text,
        ));
  }

  void _message(String value) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(value)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final item = _record;
    return Scaffold(
      appBar: AppBar(title: const Text('Patient case')),
      body: _loading && _case == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
                children: [
                  if (_error != null) _statusMessage(_error!),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: kNurse,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${item.title} · ${item.patientName}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          '${item.triageCategoryLabel} · ${item.patientAge ?? 'Age unknown'} · ${item.patientGender}',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 10,
                          ),
                        ),
                        Text(
                          item.location,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 10,
                          ),
                        ),
                        Text(
                          '${item.hospitalName} · ${item.incidentStatus}',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 10,
                          ),
                        ),
                        const Divider(color: Colors.white24, height: 20),
                        Text(
                          'Transport: ${item.unit} · ${item.responder}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                          ),
                        ),
                        Text(
                          item.status == 'en_route'
                              ? 'En route · ETA ${item.etaMinutes ?? 'unknown'} min'
                              : 'Status: ${item.status.replaceAll('_', ' ')}',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 9,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Section('Patient assessment'),
                  Box(
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Heart rate: ${item.heartRate == null ? 'N/A' : '${item.heartRate} bpm'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        Text(
                          'SpO2: ${item.spo2 == null ? 'N/A' : '${item.spo2}%'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        Text(
                          'Blood pressure: ${item.bloodPressure ?? 'N/A'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        Text(
                          'Consciousness (GCS): ${item.gcs ?? 'N/A'}',
                          style: const TextStyle(fontSize: 10),
                        ),
                        if (item.responderNotes.trim().isNotEmpty) ...[
                          const SizedBox(height: 8),
                          const Text(
                            'Incident notes',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            item.responderNotes,
                            style: const TextStyle(fontSize: 10),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const Section('Clinical note'),
                  TextField(
                    controller: _note,
                    enabled: !_readOnly && !_saving,
                    minLines: 3,
                    maxLines: 6,
                    maxLength: 2000,
                    style: const TextStyle(fontSize: 11),
                    decoration: const InputDecoration(
                      hintText:
                          'Record relevant patient findings and instructions.',
                      hintStyle: TextStyle(fontSize: 10),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  NurseButton(
                    _saving ? 'SAVING...' : 'SAVE CLINICAL NOTE',
                    onTap: _readOnly || _saving ? null : _saveNote,
                  ),
                ],
              ),
            ),
    );
  }

  Widget _statusMessage(String text) => Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3D7),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          _readOnly
              ? 'Connection unavailable. This screen is read-only. $text'
              : text,
          style: const TextStyle(fontSize: 9),
        ),
      );
}
