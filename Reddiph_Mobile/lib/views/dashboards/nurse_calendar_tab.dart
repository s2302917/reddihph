import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseCalendarTab extends StatefulWidget {
  const NurseCalendarTab({super.key});

  @override
  State<NurseCalendarTab> createState() => _NurseCalendarTabState();
}

class _NurseCalendarTabState extends State<NurseCalendarTab> {
  static const _months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];
  static const _dow = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
  static const _weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];

  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  int _selected = DateTime.now().day;
  List<NurseShift> _shifts = const [];
  String? _error;
  bool _loading = true;
  bool _requesting = false;

  String? get _token => AuthController.I.user?['token'] as String?;
  String get _monthKey =>
      '${_month.year.toString().padLeft(4, '0')}-${_month.month.toString().padLeft(2, '0')}';

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
      final shifts = await MobileApiClient.I.nurseSchedule(
        token: token,
        month: _monthKey,
      );
      if (!mounted) return;
      NursePortalCache.saveSchedule(token, _monthKey, shifts);
      setState(() {
        _shifts = shifts;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      final cached = NursePortalCache.schedule(token, _monthKey);
      setState(() {
        _shifts = cached ?? _shifts;
        _error = error.message;
        _loading = false;
      });
    }
  }

  void _shiftMonth(int amount) {
    setState(() {
      _month = DateTime(_month.year, _month.month + amount);
      _selected = 1;
    });
    _load();
  }

  NurseShift? get _selectedShift {
    final date = _dateKey(_selected);
    for (final shift in _shifts) {
      if (shift.date == date) return shift;
    }
    return null;
  }

  String _dateKey(int day) =>
      '${_month.year.toString().padLeft(4, '0')}-${_month.month.toString().padLeft(2, '0')}-${day.toString().padLeft(2, '0')}';

  Color _shiftColor(String type) => switch (type) {
        'day' => kNurse,
        'night' => kNurseLight,
        'on_call' => kSos,
        'off' => const Color(0xFFD9D9D9),
        _ => Colors.transparent,
      };

  String _shiftTitle(NurseShift? shift) {
    if (shift == null) return 'No shift scheduled';
    return switch (shift.type) {
      'day' => 'Day shift',
      'night' => 'Night shift',
      'on_call' => 'On call',
      'off' => 'Off duty',
      _ => 'Scheduled shift',
    };
  }

  Future<void> _requestSwap() async {
    final token = _token;
    if (token == null || _error != null || _requesting) return;
    final message = TextEditingController();
    final requested = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request shift change'),
        content: TextField(
          controller: message,
          maxLength: 500,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Reason or preferred arrangement',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Submit request'),
          ),
        ],
      ),
    );
    if (requested != true) {
      message.dispose();
      return;
    }
    setState(() => _requesting = true);
    try {
      await MobileApiClient.I.requestNurseShiftSwap(
        token: token,
        date: _dateKey(_selected),
        shiftId: _selectedShift?.id,
        message: message.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Shift change request submitted.')),
      );
      setState(() => _requesting = false);
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _requesting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    } finally {
      message.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    final first = DateTime(_month.year, _month.month, 1);
    final daysIn = DateTime(_month.year, _month.month + 1, 0).day;
    final lead = first.weekday - 1;
    final rows = ((lead + daysIn) / 7).ceil();
    final selectedDay = _selected > daysIn ? daysIn : _selected;
    final selectedDate = DateTime(_month.year, _month.month, selectedDay);
    final shift = _selectedShift;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          const Text(
            'Duty schedule',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const Text(
            'Your assigned hospital shifts',
            style: TextStyle(fontSize: 9, color: Colors.black54),
          ),
          if (_error != null) _offlineNotice(),
          Row(
            children: [
              IconButton(
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.chevron_left),
                onPressed: () => _shiftMonth(-1),
              ),
              Expanded(
                child: Center(
                  child: Text(
                    '${_months[_month.month - 1]} ${_month.year}',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
              IconButton(
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.chevron_right),
                onPressed: () => _shiftMonth(1),
              ),
            ],
          ),
          Row(
            children: [
              for (final day in _dow)
                Expanded(
                  child: Center(
                    child: Text(
                      day,
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          for (var row = 0; row < rows; row++)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  for (var col = 0; col < 7; col++)
                    _dayCell(row * 7 + col - lead + 1, daysIn),
                ],
              ),
            ),
          Wrap(
            spacing: 12,
            runSpacing: 6,
            children: [
              _legend('Day', kNurse),
              _legend('Night', kNurseLight),
              _legend('On call', kSos),
              _legend('Off', const Color(0xFFD9D9D9)),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${_weekdays[selectedDate.weekday - 1]} ${selectedDate.day} ${_months[selectedDate.month - 1]}',
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 8),
          Box(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _shiftTitle(shift),
                  style: const TextStyle(
                      fontSize: 10, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  shift == null
                      ? 'No duty hours recorded.'
                      : '${shift.start.isEmpty ? 'Time not set' : shift.start}–${shift.end.isEmpty ? 'Time not set' : shift.end} · ${shift.unit}',
                  style: const TextStyle(fontSize: 9, color: Colors.black54),
                ),
                if (shift != null)
                  Text(
                    shift.status.replaceAll('_', ' '),
                    style: const TextStyle(fontSize: 8, color: Colors.black54),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed:
                _error != null || _loading || _requesting ? null : _requestSwap,
            child: Text(
              _requesting ? 'SUBMITTING...' : 'REQUEST SHIFT CHANGE',
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800),
            ),
          ),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(10),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }

  Widget _dayCell(int day, int daysIn) {
    if (day < 1 || day > daysIn) {
      return const Expanded(child: SizedBox(height: 34));
    }
    NurseShift? shift;
    for (final entry in _shifts) {
      if (entry.date == _dateKey(day)) {
        shift = entry;
        break;
      }
    }
    final selected = day == _selected;
    return Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => setState(() => _selected = day),
        child: SizedBox(
          height: 34,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 20,
                height: 20,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected ? kNurse : Colors.transparent,
                  borderRadius: BorderRadius.circular(5),
                ),
                child: Text(
                  '$day',
                  style: TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                    color: selected ? Colors.white : Colors.black,
                  ),
                ),
              ),
              const SizedBox(height: 3),
              Container(
                width: 16,
                height: 3,
                color: shift == null
                    ? Colors.transparent
                    : _shiftColor(shift.type),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _legend(String text, Color color) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 14, height: 3, color: color),
          const SizedBox(width: 4),
          Text(text, style: const TextStyle(fontSize: 8)),
        ],
      );

  Widget _offlineNotice() => Container(
        margin: const EdgeInsets.symmetric(vertical: 10),
        padding: const EdgeInsets.all(9),
        color: const Color(0xFFFFF3D7),
        child: Row(
          children: [
            const Icon(Icons.cloud_off, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Connection unavailable; schedule is read-only. $_error',
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
}
