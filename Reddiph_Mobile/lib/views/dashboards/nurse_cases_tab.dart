import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseCasesTab extends StatefulWidget {
  const NurseCasesTab({super.key});

  @override
  State<NurseCasesTab> createState() => _NurseCasesTabState();
}

class _NurseCasesTabState extends State<NurseCasesTab> {
  List<NurseBed> _beds = const [];
  List<NurseCaseRecord> _cases = const [];
  final Map<int, int?> _selectedCases = {};
  String? _error;
  bool _loading = true;
  int? _workingBed;

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
      final values = await Future.wait<Object>([
        MobileApiClient.I.nurseBeds(token: token),
        MobileApiClient.I.nurseCases(token: token),
      ]);
      if (!mounted) return;
      final beds = values[0] as List<NurseBed>;
      final cases = values[1] as List<NurseCaseRecord>;
      NursePortalCache.saveBeds(token, beds);
      NursePortalCache.saveCases(token, cases);
      setState(() {
        _beds = beds;
        _cases = cases;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      final cachedBeds = NursePortalCache.beds(token);
      final cachedCases = NursePortalCache.cases(token);
      setState(() {
        _beds = cachedBeds ?? _beds;
        _cases = cachedCases ?? _cases;
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _action(NurseBed bed, String action, {int? incidentId}) async {
    final token = _token;
    if (token == null || _error != null || _workingBed != null) return;
    setState(() => _workingBed = bed.id);
    try {
      final beds = await MobileApiClient.I.nurseBedAction(
        token: token,
        bedId: bed.id,
        action: action,
        incidentId: incidentId,
      );
      if (!mounted) return;
      NursePortalCache.saveBeds(token, beds);
      setState(() {
        _beds = beds;
        _workingBed = null;
      });
      await _load();
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _workingBed = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _beds.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_beds.isEmpty && _error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off, size: 36),
              const SizedBox(height: 10),
              Text(_error!, textAlign: TextAlign.center),
              OutlinedButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }
    final ready = _beds.where((bed) => bed.status == 'ready').length;
    final preparing = _beds.where((bed) => bed.status == 'preparing').length;
    final occupied = _beds.where((bed) => bed.status == 'occupied').length;
    final cleaning = _beds.where((bed) => bed.status == 'cleaning').length;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          const Text(
            'Bed management',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const Text(
            'Live hospital bed inventory',
            style: TextStyle(fontSize: 9, color: Colors.black54),
          ),
          if (_error != null) _offlineNotice(),
          const SizedBox(height: 14),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              _count('Ready', ready, const Color(0xFF3D8A64)),
              _count('Preparing', preparing, kNurse),
              _count('Occupied', occupied, kSos),
              _count('Cleaning', cleaning, Colors.black54),
            ],
          ),
          for (final type in ['er', 'pediatric']) ...[
            Section(type == 'er' ? 'Emergency beds' : 'Pediatric beds'),
            for (final bed in _beds.where((item) => item.type == type))
              _bedCard(bed),
          ],
          if (_beds.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 20),
              child: Text(
                'No beds are configured for this hospital.',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            ),
        ],
      ),
    );
  }

  Widget _offlineNotice() => Container(
        margin: const EdgeInsets.only(top: 12),
        padding: const EdgeInsets.all(10),
        color: const Color(0xFFFFF3D7),
        child: Row(
          children: [
            const Icon(Icons.cloud_off, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Disconnected: showing last loaded data in read-only mode. $_error',
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

  Widget _count(String label, int count, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .1),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Text(
          '$label $count',
          style: TextStyle(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            color: color,
          ),
        ),
      );

  Widget _bedCard(NurseBed bed) {
    final canReserve = bed.status == 'ready' && _cases.isNotEmpty;
    final action = switch (bed.status) {
      'ready' => 'reserve',
      'preparing' => 'release',
      'occupied' => 'discharge',
      'cleaning' => 'ready',
      _ => '',
    };
    final buttonText = switch (action) {
      'reserve' => 'RESERVE',
      'release' => 'RELEASE',
      'discharge' => 'DISCHARGE',
      'ready' => 'MARK READY',
      _ => '',
    };
    final enabled = _error == null &&
        _workingBed == null &&
        action.isNotEmpty &&
        (bed.status != 'ready' || canReserve);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    bed.label,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Text(
                  bed.status.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    color: kNurse,
                  ),
                ),
              ],
            ),
            if (bed.patientName != null && bed.patientName!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  bed.patientName!,
                  style: const TextStyle(fontSize: 9, color: Colors.black54),
                ),
              ),
            if (bed.status == 'ready') ...[
              const SizedBox(height: 8),
              DropdownButtonFormField<int>(
                initialValue: _selectedCases[bed.id],
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Assign incoming case',
                  labelStyle: TextStyle(fontSize: 9),
                ),
                items: [
                  for (final item in _cases.where((item) => item.bedId == null))
                    DropdownMenuItem(
                      value: item.id,
                      child: Text(
                        '${item.title} · ${item.patientName}',
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 9),
                      ),
                    ),
                ],
                onChanged: enabled
                    ? (id) => setState(() => _selectedCases[bed.id] = id)
                    : null,
              ),
            ],
            if (buttonText.isNotEmpty) ...[
              const SizedBox(height: 9),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: enabled &&
                          (action != 'reserve' ||
                              _selectedCases[bed.id] != null)
                      ? () => _action(
                            bed,
                            action,
                            incidentId: _selectedCases[bed.id],
                          )
                      : null,
                  child: Text(
                    _workingBed == bed.id ? 'UPDATING...' : buttonText,
                    style: const TextStyle(fontSize: 9),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
