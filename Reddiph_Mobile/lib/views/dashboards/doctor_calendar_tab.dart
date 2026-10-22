import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/doctor_widgets.dart';

class DoctorCalendarTab extends StatefulWidget {
  const DoctorCalendarTab({super.key});

  @override
  State<DoctorCalendarTab> createState() => _DoctorCalendarTabState();
}

class _DoctorCalendarTabState extends State<DoctorCalendarTab> {
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
  static const _weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  DateTime _month = DateTime.now();
  late DateTime _selectedDate =
      DateTime(_month.year, _month.month, DateTime.now().day);
  List<DoctorScheduleEntry> _shifts = const [];
  String? _error;
  bool _loading = true;
  int _requestVersion = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  String get _monthKey =>
      '${_month.year.toString().padLeft(4, '0')}-'
      '${_month.month.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    final version = ++_requestVersion;
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
      final shifts = await MobileApiClient.I.doctorSchedule(
        token: token,
        month: _monthKey,
      );
      if (!mounted || version != _requestVersion) return;
      setState(() {
        _shifts = shifts;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (mounted && version == _requestVersion) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  void _shiftMonth(int delta) {
    setState(() {
      _month = DateTime(_month.year, _month.month + delta);
      final lastDay = DateTime(_month.year, _month.month + 1, 0).day;
      _selectedDate = DateTime(
        _month.year,
        _month.month,
        _selectedDate.day.clamp(1, lastDay),
      );
    });
    _load();
  }

  Color _shiftColor(String type) => switch (type) {
        'morning' => kNavy,
        'evening' => kSlate,
        'oncall' => kSos,
        'leave' => const Color(0xFFD9D9D9),
        _ => Colors.transparent,
      };

  @override
  Widget build(BuildContext context) {
    final daysInMonth = DateTime(_month.year, _month.month + 1, 0).day;
    final leading = DateTime(_month.year, _month.month, 1).weekday - 1;
    final rows = ((leading + daysInMonth) / 7).ceil();
    final selected = _shifts.where((shift) =>
        shift.date.year == _selectedDate.year &&
        shift.date.month == _selectedDate.month &&
        shift.date.day == _selectedDate.day);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          const Text('Calendar',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const Text('Your duty schedule',
              style: TextStyle(fontSize: 9, color: Colors.black54)),
          const SizedBox(height: 16),
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
                        fontSize: 11, fontWeight: FontWeight.w600),
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
              for (final day in _weekdays)
                Expanded(
                  child: Center(
                    child: Text(day.substring(0, 2),
                        style: const TextStyle(
                            fontSize: 8, color: Colors.black54)),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: LinearProgressIndicator(minHeight: 2),
            ),
          if (_error != null)
            Row(
              children: [
                Expanded(
                  child: Text(_error!,
                      style: const TextStyle(fontSize: 9, color: kSos)),
                ),
                TextButton(onPressed: _load, child: const Text('RETRY')),
              ],
            ),
          for (int row = 0; row < rows; row++)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  for (int column = 0; column < 7; column++)
                    _dayCell(row * 7 + column - leading + 1, daysInMonth),
                ],
              ),
            ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 12,
            runSpacing: 6,
            children: [
              _legend('Morning', kNavy),
              _legend('Evening', kSlate),
              _legend('On-call', kSos),
              _legend('Leave', const Color(0xFFD9D9D9)),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${_weekdays[_selectedDate.weekday - 1]} '
            '${_selectedDate.day} ${_months[_selectedDate.month - 1]}',
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 8),
          if (selected.isEmpty)
            const DCard(
              Text('No shift scheduled for this day.',
                  style: TextStyle(fontSize: 9, color: Colors.black54)),
            )
          else
            for (final shift in selected) _shiftCard(shift),
          OutlinedButton.icon(
            onPressed: () => _editShift(),
            icon: const Icon(Icons.add, size: 16),
            label: const Text('ADD SHIFT',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );
  }

  Widget _dayCell(int day, int daysInMonth) {
    if (day < 1 || day > daysInMonth) {
      return const Expanded(child: SizedBox(height: 36));
    }
    final date = DateTime(_month.year, _month.month, day);
    final shifts = _shifts.where((entry) =>
        entry.date.year == date.year &&
        entry.date.month == date.month &&
        entry.date.day == date.day);
    final firstShift = shifts.isEmpty ? null : shifts.first;
    final selected = date.year == _selectedDate.year &&
        date.month == _selectedDate.month &&
        date.day == _selectedDate.day;
    return Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => setState(() => _selectedDate = date),
        child: SizedBox(
          height: 36,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 22,
                height: 22,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected ? kNavy : Colors.transparent,
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
                color: _shiftColor(firstShift?.type ?? ''),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _shiftCard(DoctorScheduleEntry shift) => DCard(
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(shift.typeLabel,
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w600)),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: () => _editShift(shift),
                  icon: const Icon(Icons.edit_outlined, size: 16),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: () => _deleteShift(shift),
                  icon: const Icon(Icons.delete_outline, size: 16),
                ),
              ],
            ),
            Text(
              shift.start.isEmpty
                  ? shift.status
                  : '${shift.start} – ${shift.end} · ${shift.status}',
              style: const TextStyle(fontSize: 8, color: Colors.black54),
            ),
          ],
        ),
      );

  Widget _legend(String label, Color color) =>
      Row(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 14, height: 3, color: color),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 8)),
      ]);

  Future<void> _editShift([DoctorScheduleEntry? shift]) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() => _error = 'Your session has expired. Please sign in again.');
      return;
    }
    final type = ValueNotifier<String>(shift?.type ?? 'morning');
    var start = _parseTime(shift?.start) ?? const TimeOfDay(hour: 6, minute: 0);
    var end = _parseTime(shift?.end) ?? const TimeOfDay(hour: 14, minute: 0);

    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, updateDialog) => AlertDialog(
          title: Text(shift == null ? 'Add shift' : 'Edit shift'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ValueListenableBuilder<String>(
                valueListenable: type,
                builder: (context, value, _) => DropdownButtonFormField<String>(
                  initialValue: value,
                  decoration: const InputDecoration(labelText: 'Shift type'),
                  items: const [
                    DropdownMenuItem(value: 'morning', child: Text('Morning')),
                    DropdownMenuItem(value: 'evening', child: Text('Evening')),
                    DropdownMenuItem(value: 'oncall', child: Text('On-call')),
                    DropdownMenuItem(value: 'leave', child: Text('Leave')),
                  ],
                  onChanged: (value) {
                    if (value != null) type.value = value;
                  },
                ),
              ),
              ValueListenableBuilder<String>(
                valueListenable: type,
                builder: (context, value, _) {
                  if (value == 'leave' || value == 'oncall') {
                    return const SizedBox.shrink();
                  }
                  return Column(
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Starts', style: TextStyle(fontSize: 13)),
                        trailing: Text(start.format(context)),
                        onTap: () async {
                          final value = await showTimePicker(
                            context: dialogContext,
                            initialTime: start,
                          );
                          if (value != null) {
                            updateDialog(() => start = value);
                          }
                        },
                      ),
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Ends', style: TextStyle(fontSize: 13)),
                        trailing: Text(end.format(context)),
                        onTap: () async {
                          final value = await showTimePicker(
                            context: dialogContext,
                            initialTime: end,
                          );
                          if (value != null) {
                            updateDialog(() => end = value);
                          }
                        },
                      ),
                    ],
                  );
                },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
    final selectedType = type.value;
    type.dispose();
    if (saved != true || !mounted) return;

    try {
      await MobileApiClient.I.saveDoctorSchedule(
        token: token,
        scheduleId: shift?.id,
        date: _selectedDate,
        type: selectedType,
        start: selectedType == 'leave' || selectedType == 'oncall'
            ? ''
            : _formatTime(start),
        end: selectedType == 'leave' || selectedType == 'oncall'
            ? ''
            : _formatTime(end),
      );
      await _load();
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    }
  }

  Future<void> _deleteShift(DoctorScheduleEntry shift) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Remove shift?'),
        content: const Text('This shift will be removed from your schedule.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() => _error = 'Your session has expired. Please sign in again.');
      return;
    }
    try {
      await MobileApiClient.I.deleteDoctorSchedule(
        token: token,
        scheduleId: shift.id,
      );
      await _load();
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    }
  }

  TimeOfDay? _parseTime(String? value) {
    if (value == null || value.isEmpty) return null;
    final parts = value.split(':');
    if (parts.length < 2) return null;
    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null || minute == null) return null;
    return TimeOfDay(hour: hour, minute: minute);
  }

  String _formatTime(TimeOfDay time) =>
      '${time.hour.toString().padLeft(2, '0')}:'
      '${time.minute.toString().padLeft(2, '0')}';
}
