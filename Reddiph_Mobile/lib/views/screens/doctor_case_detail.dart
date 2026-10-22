import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/doctor_widgets.dart';

class DoctorCaseDetail extends StatefulWidget {
  const DoctorCaseDetail({
    super.key,
    required this.incidentId,
    this.initialItem,
  });

  final int incidentId;
  final DoctorCaseRecord? initialItem;

  @override
  State<DoctorCaseDetail> createState() => _DoctorCaseDetailState();
}

class _DoctorCaseDetailState extends State<DoctorCaseDetail> {
  final _note = TextEditingController();
  DoctorCaseRecord? _item;
  String? _error;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _item = widget.initialItem;
    _load();
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() {
        _loading = false;
        _error = 'Sign in again to view this case.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final item = await MobileApiClient.I.doctorCaseDetails(
        token: token,
        incidentId: widget.incidentId,
      );
      if (!mounted) return;
      _note.text = item.clinicalNote;
      setState(() {
        _item = item;
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

  Future<void> _save({required bool confirmArrival}) async {
    final token = AuthController.I.user?['token'] as String?;
    if (_saving) return;
    if (token == null) {
      setState(
          () => _error = 'Your session has expired. Please sign in again.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final item = await MobileApiClient.I.saveDoctorCaseReview(
        token: token,
        incidentId: widget.incidentId,
        clinicalNote: _note.text,
        confirmArrival: confirmArrival,
      );
      if (!mounted) return;
      setState(() {
        _item = item;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            confirmArrival ? 'Arrival confirmed.' : 'Clinical note saved.',
          ),
        ),
      );
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _saving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = _item;
    if (item == null) {
      return Scaffold(
        appBar: AppBar(),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _error ?? 'Case details are unavailable.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 11, color: kSos),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton(
                          onPressed: _load, child: const Text('RETRY')),
                    ],
                  ),
                ),
              ),
      );
    }
    final severity = doctorSeverity(item.statusLabel);
    return Scaffold(
      appBar: AppBar(),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 30),
                children: [
                  if (_error != null) _errorMessage(_error!, _load),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: kSlate,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${item.patient} · ${item.title}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            SmallPill(item.statusLabel, severity),
                          ],
                        ),
                        Text(
                          [
                            if (item.patientAge != null) '${item.patientAge}',
                            item.patientGender,
                            item.location,
                          ].join(' · '),
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 9,
                          ),
                        ),
                        const SizedBox(height: 10),
                        VitalsRow(
                          pulse: item.heartRate?.toString() ?? '—',
                          spo2: item.spo2 == null ? '—' : '${item.spo2}%',
                          bp: item.bloodPressure ?? '—',
                          blood: '—',
                        ),
                      ],
                    ),
                  ),
                  if (item.patients.length > 1) ...[
                    const Section('Patients'),
                    for (final patient in item.patients.skip(1))
                      DCard(
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${patient.name} · ${patient.age} · ${patient.gender}',
                                style: const TextStyle(fontSize: 9),
                              ),
                            ),
                            Text(
                              'HR ${patient.heartRate ?? '—'} · SpO₂ ${patient.spo2 ?? '—'}',
                              style: const TextStyle(
                                fontSize: 8,
                                color: Colors.black54,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                  const Section('Case timeline'),
                  DCard(
                    Column(
                      children: [
                        for (int index = 0;
                            index < item.timeline.length;
                            index++) ...[
                          if (index > 0) const Divider(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.timeline[index].title,
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    if (item.timeline[index].detail.isNotEmpty)
                                      Text(
                                        item.timeline[index].detail,
                                        style: const TextStyle(
                                          fontSize: 8,
                                          color: Colors.black54,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              Text(
                                item.timeline[index].timeLabel,
                                style: const TextStyle(
                                  fontSize: 8,
                                  color: Colors.black45,
                                ),
                              ),
                            ],
                          ),
                        ],
                        if (item.timeline.isEmpty)
                          const Text(
                            'No timeline events recorded.',
                            style:
                                TextStyle(fontSize: 10, color: Colors.black54),
                          ),
                      ],
                    ),
                  ),
                  const Section('Clinical note'),
                  TextField(
                    controller: _note,
                    minLines: 3,
                    maxLines: 6,
                    maxLength: 2000,
                    style: const TextStyle(fontSize: 11),
                    decoration: InputDecoration(
                      hintText:
                          'Add findings, treatment given, instructions for the ER team.',
                      hintStyle: const TextStyle(
                        fontSize: 10,
                        color: Colors.black45,
                      ),
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
                  const SizedBox(height: 6),
                  OutlinedButton(
                    onPressed:
                        _saving ? null : () => _save(confirmArrival: false),
                    child: Text(_saving ? 'SAVING…' : 'SAVE CLINICAL NOTE'),
                  ),
                  const SizedBox(height: 8),
                  Pressable(
                    child: NavyButton(
                      item.doctorArrivedAt != null
                          ? 'ARRIVAL CONFIRMED'
                          : 'CONFIRM ARRIVAL',
                      onTap: _saving || item.doctorArrivedAt != null
                          ? null
                          : () => _save(confirmArrival: true),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _errorMessage(String message, VoidCallback retry) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          children: [
            Expanded(
              child: Text(
                message,
                style: const TextStyle(fontSize: 9, color: kSos),
              ),
            ),
            TextButton(onPressed: retry, child: const Text('RETRY')),
          ],
        ),
      );
}
