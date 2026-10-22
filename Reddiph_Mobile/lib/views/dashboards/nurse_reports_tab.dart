import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseReportsTab extends StatefulWidget {
  const NurseReportsTab({super.key});

  @override
  State<NurseReportsTab> createState() => _NurseReportsTabState();
}

class _NurseReportsTabState extends State<NurseReportsTab> {
  List<NurseCaseRecord> _cases = const [];
  List<NurseHandoff> _handoffs = const [];
  String? _error;
  bool _loading = true;
  int? _workingIncident;

  String? get _token => AuthController.I.user?['token'] as String?;

  @override
  void initState() {
    super.initState();
    _load();
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
      final results = await Future.wait<Object>([
        MobileApiClient.I.nurseCases(token: token),
        MobileApiClient.I.nurseHandoffs(token: token),
      ]);
      if (!mounted) return;
      final cases = results[0] as List<NurseCaseRecord>;
      final handoffs = results[1] as List<NurseHandoff>;
      NursePortalCache.saveCases(token, cases);
      NursePortalCache.saveHandoffs(token, handoffs);
      setState(() {
        _cases = cases;
        _handoffs = handoffs;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      final cachedCases = NursePortalCache.cases(token);
      final cachedHandoffs = NursePortalCache.handoffs(token);
      setState(() {
        _cases = cachedCases ?? _cases;
        _handoffs = cachedHandoffs ?? _handoffs;
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _confirmArrival(NurseCaseRecord item) async {
    final token = _token;
    if (token == null || _error != null || _workingIncident != null) return;
    setState(() => _workingIncident = item.id);
    try {
      await MobileApiClient.I.confirmNurseArrival(
        token: token,
        incidentId: item.id,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Patient arrival confirmed.')),
      );
      setState(() => _workingIncident = null);
      await _load();
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _workingIncident = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    }
  }

  Future<void> _recordHandoff(NurseCaseRecord item) async {
    final token = _token;
    if (token == null || _error != null || _workingIncident != null) return;
    final formKey = GlobalKey<FormState>();
    final pulse = TextEditingController();
    final spo2 = TextEditingController();
    final bloodPressure = TextEditingController();
    final notes = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Record patient handoff'),
        content: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '${item.patientName} · ${item.title}',
                  style: const TextStyle(fontSize: 11),
                ),
                TextFormField(
                  controller: pulse,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Pulse (bpm)'),
                ),
                TextFormField(
                  controller: spo2,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'SpO2 (%)'),
                ),
                TextFormField(
                  controller: bloodPressure,
                  decoration: const InputDecoration(
                    labelText: 'Blood pressure (e.g. 120/80)',
                  ),
                ),
                TextFormField(
                  controller: notes,
                  maxLength: 2000,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(labelText: 'Handoff notes'),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (formKey.currentState!.validate()) {
                Navigator.pop(dialogContext, true);
              }
            },
            child: const Text('Save handoff'),
          ),
        ],
      ),
    );
    if (submitted != true) {
      pulse.dispose();
      spo2.dispose();
      bloodPressure.dispose();
      notes.dispose();
      return;
    }
    setState(() => _workingIncident = item.id);
    try {
      final handoffs = await MobileApiClient.I.createNurseHandoff(
        token: token,
        incidentId: item.id,
        pulse: pulse.text,
        spo2: spo2.text,
        bloodPressure: bloodPressure.text,
        notes: notes.text,
      );
      if (!mounted) return;
      setState(() {
        _handoffs = handoffs;
        _workingIncident = null;
      });
      NursePortalCache.saveHandoffs(token, handoffs);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Handoff recorded.')),
      );
      await _load();
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _workingIncident = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    } finally {
      pulse.dispose();
      spo2.dispose();
      bloodPressure.dispose();
      notes.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _cases.isEmpty && _handoffs.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          const Text(
            'Patient handoff',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const Text(
            'Confirm arrivals and record the transfer of care',
            style: TextStyle(fontSize: 9, color: Colors.black54),
          ),
          if (_error != null) _offlineMessage(),
          const Section('Cases'),
          if (_cases.isEmpty)
            const Box(
              Text(
                'No assigned cases are currently available.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            ),
          for (final item in _cases) _caseCard(item),
          const Section('Recorded handoffs'),
          if (_handoffs.isEmpty)
            const Box(
              Text(
                'No patient handoffs have been recorded yet.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            )
          else
            for (final handoff in _handoffs) _handoffCard(handoff),
        ],
      ),
    );
  }

  Widget _offlineMessage() => Container(
        margin: const EdgeInsets.only(top: 12),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3D7),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          children: [
            const Icon(Icons.cloud_off, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Offline/read-only. Last loaded records are shown. $_error',
                style: const TextStyle(fontSize: 9),
              ),
            ),
            IconButton(
              onPressed: _load,
              icon: const Icon(Icons.refresh, size: 18),
            ),
          ],
        ),
      );

  Widget _caseCard(NurseCaseRecord item) {
    final canHandoff = item.arrivalConfirmed && item.status != 'handed_off';
    final needsArrival = !item.arrivalConfirmed && item.status != 'handed_off';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${item.patientName} · ${item.title}',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 3),
            Text(
              'Bed: ${item.bedLabel ?? 'Not assigned'} · ${item.status.replaceAll('_', ' ')}',
              style: const TextStyle(fontSize: 9, color: Colors.black54),
            ),
            if (needsArrival || canHandoff) ...[
              const SizedBox(height: 8),
              NurseButton(
                _workingIncident == item.id
                    ? 'SAVING...'
                    : needsArrival
                        ? 'CONFIRM PATIENT ARRIVAL'
                        : 'RECORD HANDOFF',
                onTap: _error != null ||
                        _workingIncident != null ||
                        (needsArrival &&
                            (item.bedId == null || item.status != 'arrived'))
                    ? null
                    : needsArrival
                        ? () => _confirmArrival(item)
                        : () => _recordHandoff(item),
              ),
              if (needsArrival && item.bedId == null)
                const Padding(
                  padding: EdgeInsets.only(top: 5),
                  child: Text(
                    'Reserve a bed before confirming arrival.',
                    style: TextStyle(fontSize: 8, color: Colors.black54),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _handoffCard(NurseHandoff handoff) => Card(
        child: ListTile(
          leading: const CircleAvatar(
            backgroundColor: kNurse,
            child: Icon(Icons.swap_horiz, color: Colors.white, size: 18),
          ),
          title: Text(
            '${handoff.patientName} · ${handoff.caseTitle}',
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
          ),
          subtitle: Text(
            '${handoff.bedLabel.isEmpty ? 'Bed not recorded' : handoff.bedLabel} · Pulse ${handoff.pulse.isEmpty ? '—' : handoff.pulse} · SpO2 ${handoff.spo2.isEmpty ? '—' : handoff.spo2} · BP ${handoff.bloodPressure.isEmpty ? '—' : handoff.bloodPressure}\n${handoff.notes}',
            style: const TextStyle(fontSize: 8),
          ),
          trailing: Text(
            handoff.createdAt,
            style: const TextStyle(fontSize: 8, color: Colors.black54),
          ),
          isThreeLine: true,
        ),
      );
}
