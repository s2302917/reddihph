import 'package:flutter/material.dart';

class ScheduleDraft {
  const ScheduleDraft({
    required this.dates,
    required this.type,
    required this.start,
    required this.end,
  });

  final List<DateTime> dates;
  final String type;
  final String start;
  final String end;
}

Future<ScheduleDraft?> showScheduleEditorDialog({
  required BuildContext context,
  required DateTime initialDate,
  required Map<String, String> typeOptions,
  required String initialType,
  String initialStart = '',
  String initialEnd = '',
  bool editing = false,
}) =>
    showDialog<ScheduleDraft>(
      context: context,
      builder: (context) => _ScheduleEditorDialog(
        initialDate: initialDate,
        typeOptions: typeOptions,
        initialType: initialType,
        initialStart: initialStart,
        initialEnd: initialEnd,
        editing: editing,
      ),
    );

class _ScheduleEditorDialog extends StatefulWidget {
  const _ScheduleEditorDialog({
    required this.initialDate,
    required this.typeOptions,
    required this.initialType,
    required this.initialStart,
    required this.initialEnd,
    required this.editing,
  });

  final DateTime initialDate;
  final Map<String, String> typeOptions;
  final String initialType;
  final String initialStart;
  final String initialEnd;
  final bool editing;

  @override
  State<_ScheduleEditorDialog> createState() => _ScheduleEditorDialogState();
}

class _ScheduleEditorDialogState extends State<_ScheduleEditorDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _date;
  late final TextEditingController _rangeStart;
  late final TextEditingController _rangeEnd;
  late final TextEditingController _repeatUntil;
  late String _type;
  late String _start;
  late String _end;
  String _mode = 'single';
  String _pattern = 'daily';
  late Set<int> _weekdays;
  String? _dateError;

  @override
  void initState() {
    super.initState();
    final date = _dateValue(widget.initialDate);
    _date = TextEditingController(text: date);
    _rangeStart = TextEditingController(text: date);
    _rangeEnd = TextEditingController(text: date);
    _repeatUntil = TextEditingController(text: date);
    _type = widget.typeOptions.containsKey(widget.initialType)
        ? widget.initialType
        : widget.typeOptions.keys.first;
    _start = widget.initialStart;
    _end = widget.initialEnd;
    _weekdays = {widget.initialDate.weekday};
  }

  @override
  void dispose() {
    _date.dispose();
    _rangeStart.dispose();
    _rangeEnd.dispose();
    _repeatUntil.dispose();
    super.dispose();
  }

  String _dateValue(DateTime date) => '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  DateTime? _parseDate(String value) {
    final parsed = DateTime.tryParse(value);
    return parsed != null && _dateValue(parsed) == value ? parsed : null;
  }

  String _timeValue(TimeOfDay value) =>
      '${value.hour.toString().padLeft(2, '0')}:'
      '${value.minute.toString().padLeft(2, '0')}';

  List<DateTime> get _selectedDates {
    final start = _parseDate(_mode == 'range' ? _rangeStart.text : _date.text);
    final end = switch (_mode) {
      'range' => _parseDate(_rangeEnd.text),
      'repeat' => _parseDate(_repeatUntil.text),
      _ => start,
    };
    if (start == null || end == null || end.isBefore(start)) return const [];
    final dates = <DateTime>[];
    for (var day = start;
        !day.isAfter(end);
        day = day.add(const Duration(days: 1))) {
      final include = switch (_mode) {
        'single' => true,
        'range' => true,
        'repeat' when _pattern == 'weekly' =>
          day.difference(start).inDays % 7 == 0,
        'repeat' when _pattern == 'weekdays' => _weekdays.contains(day.weekday),
        _ => true,
      };
      if (include) dates.add(day);
      if (dates.length > 366 || day.difference(start).inDays > 366) {
        return const [];
      }
    }
    return dates;
  }

  String get _preview {
    final start = _parseDate(_mode == 'range' ? _rangeStart.text : _date.text);
    final end = switch (_mode) {
      'range' => _parseDate(_rangeEnd.text),
      'repeat' => _parseDate(_repeatUntil.text),
      _ => start,
    };
    if (start == null || end == null) {
      return 'Enter valid dates to preview.';
    }
    if (end.isBefore(start)) {
      return 'The end date cannot be before the start date.';
    }
    if (_mode == 'repeat' && _pattern == 'weekdays' && _weekdays.isEmpty) {
      return 'Select at least one weekday.';
    }
    final dates = _selectedDates;
    if (dates.isEmpty) {
      return 'A schedule can include no more than 366 dates.';
    }
    final shown = dates.take(6).map(_dateValue).join(', ');
    return '${dates.length} date${dates.length == 1 ? '' : 's'}: $shown'
        '${dates.length > 6 ? ', …' : ''}';
  }

  Future<void> _pickDate(
    TextEditingController controller, {
    DateTime? minimum,
  }) async {
    final existing = _parseDate(controller.text) ?? widget.initialDate;
    final firstDate = minimum ?? DateTime(2000);
    final selected = await showDatePicker(
      context: context,
      initialDate: existing.isBefore(firstDate) ? firstDate : existing,
      firstDate: firstDate,
      lastDate: DateTime(2100, 12, 31),
      initialEntryMode: DatePickerEntryMode.calendar,
    );
    if (selected == null || !mounted) {
      return;
    }
    setState(() {
      controller.text = _dateValue(selected);
      _dateError = null;
    });
  }

  Future<void> _pickTime(bool startTime) async {
    final current = startTime ? _start : _end;
    final parts = current.split(':');
    final hour = parts.length == 2 ? int.tryParse(parts[0]) ?? 7 : 7;
    final minute = parts.length == 2 ? int.tryParse(parts[1]) ?? 0 : 0;
    final selected = await showTimePicker(
      context: context,
      initialTime: TimeOfDay(hour: hour, minute: minute),
    );
    if (selected == null || !mounted) {
      return;
    }
    setState(() {
      if (startTime) {
        _start = _timeValue(selected);
      } else {
        _end = _timeValue(selected);
      }
    });
  }

  void _save() {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    final dates = _selectedDates;
    if (dates.isEmpty) {
      setState(() => _dateError = _preview);
      return;
    }
    final noTime = _type == 'oncall' ||
        _type == 'leave' ||
        _type == 'on_call' ||
        _type == 'off';
    if (!noTime && (_start.isEmpty || _end.isEmpty || _start == _end)) {
      setState(() => _dateError = 'Enter different start and end times.');
      return;
    }
    Navigator.of(context).pop(ScheduleDraft(
      dates: dates,
      type: _type,
      start: noTime ? '' : _start,
      end: noTime ? '' : _end,
    ));
  }

  Widget _dateField(String label, TextEditingController controller) =>
      TextFormField(
        key: ValueKey(label),
        controller: controller,
        keyboardType: TextInputType.datetime,
        decoration: InputDecoration(
          labelText: label,
          hintText: 'YYYY-MM-DD',
          suffixIcon: IconButton(
            tooltip: 'Choose $label',
            onPressed: () => _pickDate(
              controller,
              minimum: identical(controller, _rangeEnd)
                  ? _parseDate(_rangeStart.text)
                  : identical(controller, _repeatUntil)
                      ? _parseDate(_date.text)
                      : null,
            ),
            icon: const Icon(Icons.calendar_month),
          ),
        ),
        onChanged: (_) => setState(() => _dateError = null),
        validator: (value) => _parseDate(value ?? '') == null
            ? 'Enter a valid date in YYYY-MM-DD format.'
            : null,
      );

  @override
  Widget build(BuildContext context) {
    final noTime = _type == 'oncall' ||
        _type == 'leave' ||
        _type == 'on_call' ||
        _type == 'off';
    final dialogWidth =
        (MediaQuery.sizeOf(context).width - 128).clamp(0.0, 440.0).toDouble();
    return AlertDialog(
      titlePadding: const EdgeInsets.fromLTRB(24, 18, 12, 0),
      title: Row(
        children: [
          Expanded(child: Text(widget.editing ? 'Edit schedule' : 'Type Date')),
          IconButton(
            tooltip: 'Close',
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.close),
          ),
        ],
      ),
      content: SizedBox(
        width: dialogWidth,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (!widget.editing) ...[
                  const Text('Apply schedule to'),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 4,
                    children: [
                      for (final entry in const [
                        ('single', 'Single date'),
                        ('range', 'Date range'),
                        ('repeat', 'Repeat'),
                      ])
                        ChoiceChip(
                          label: Text(entry.$2),
                          selected: _mode == entry.$1,
                          onSelected: (_) => setState(() {
                            _mode = entry.$1;
                            _dateError = null;
                          }),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                ],
                if (_mode == 'single' || _mode == 'repeat')
                  _dateField(
                    _mode == 'repeat' ? 'First schedule date' : 'Schedule date',
                    _date,
                  ),
                if (_mode == 'range') ...[
                  _dateField('Start date', _rangeStart),
                  const SizedBox(height: 8),
                  _dateField('End date', _rangeEnd),
                ],
                if (_mode == 'repeat') ...[
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    initialValue: _pattern,
                    decoration: const InputDecoration(labelText: 'Repeat'),
                    items: const [
                      DropdownMenuItem(
                          value: 'daily', child: Text('Every day')),
                      DropdownMenuItem(
                          value: 'weekly', child: Text('Every week')),
                      DropdownMenuItem(
                        value: 'weekdays',
                        child: Text('Selected days of the week'),
                      ),
                      DropdownMenuItem(
                        value: 'custom',
                        child: Text('Custom date range'),
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) setState(() => _pattern = value);
                    },
                  ),
                  if (_pattern == 'weekdays')
                    Wrap(
                      spacing: 2,
                      children: [
                        for (var day = 1; day <= 7; day++)
                          FilterChip(
                            label: Text(
                              const [
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                                'Sun'
                              ][day - 1],
                            ),
                            selected: _weekdays.contains(day),
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _weekdays.add(day);
                              } else {
                                _weekdays.remove(day);
                              }
                            }),
                          ),
                      ],
                    ),
                  _dateField('Repeat until', _repeatUntil),
                ],
                const SizedBox(height: 8),
                Text(_preview, style: Theme.of(context).textTheme.bodySmall),
                if (_dateError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      _dateError!,
                      style:
                          TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _type,
                  decoration: const InputDecoration(labelText: 'Schedule type'),
                  items: widget.typeOptions.entries
                      .map((entry) => DropdownMenuItem(
                            value: entry.key,
                            child: Text(entry.value),
                          ))
                      .toList(growable: false),
                  onChanged: (value) {
                    if (value != null) setState(() => _type = value);
                  },
                ),
                if (!noTime) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _pickTime(true),
                          child: Text(
                              'Starts  ${_start.isEmpty ? 'Select' : _start}'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _pickTime(false),
                          child:
                              Text('Ends  ${_end.isEmpty ? 'Select' : _end}'),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(onPressed: _save, child: const Text('Save')),
      ],
    );
  }
}
