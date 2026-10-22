import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/philippine_time.dart';

class PhilippineClock extends StatefulWidget {
  const PhilippineClock({super.key, required this.builder});

  final Widget Function(BuildContext context, DateTime time) builder;

  @override
  State<PhilippineClock> createState() => _PhilippineClockState();
}

class _PhilippineClockState extends State<PhilippineClock> {
  late DateTime _time = PhilippineTime.now();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      final now = PhilippineTime.now();
      if (now.minute != _time.minute) {
        setState(() => _time = now);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context, _time);
}
