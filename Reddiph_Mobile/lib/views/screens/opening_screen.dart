import 'package:flutter/material.dart';

class OpeningScreen extends StatefulWidget {
  final VoidCallback onFinished;

  const OpeningScreen({super.key, required this.onFinished});

  @override
  State<OpeningScreen> createState() => _OpeningScreenState();
}

class _OpeningScreenState extends State<OpeningScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2100),
  );

  late final Animation<double> _heartbeat = TweenSequence<double>([
    TweenSequenceItem(
      tween: Tween<double>(begin: 1, end: 1.24)
          .chain(CurveTween(curve: Curves.easeOut)),
      weight: 8,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.24, end: 1)
          .chain(CurveTween(curve: Curves.easeIn)),
      weight: 8,
    ),
    TweenSequenceItem(tween: ConstantTween<double>(1), weight: 3),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1, end: 1.18)
          .chain(CurveTween(curve: Curves.easeOut)),
      weight: 8,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.18, end: 1)
          .chain(CurveTween(curve: Curves.easeIn)),
      weight: 8,
    ),
    TweenSequenceItem(tween: ConstantTween<double>(1), weight: 20),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1, end: 1.24)
          .chain(CurveTween(curve: Curves.easeOut)),
      weight: 8,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.24, end: 1)
          .chain(CurveTween(curve: Curves.easeIn)),
      weight: 8,
    ),
    TweenSequenceItem(tween: ConstantTween<double>(1), weight: 3),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1, end: 1.18)
          .chain(CurveTween(curve: Curves.easeOut)),
      weight: 8,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.18, end: 1)
          .chain(CurveTween(curve: Curves.easeIn)),
      weight: 8,
    ),
    TweenSequenceItem(tween: ConstantTween<double>(1), weight: 18),
  ]).animate(_controller);

  late final Animation<double> _opacity =
      Tween<double>(begin: 1, end: 0).animate(CurvedAnimation(
    parent: _controller,
    curve: const Interval(.92, 1, curve: Curves.easeOut),
  ));

  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (MediaQuery.of(context).disableAnimations) {
        widget.onFinished();
      } else {
        _controller.addStatusListener((status) {
          if (status == AnimationStatus.completed && mounted) {
            widget.onFinished();
          }
        });
        _controller.forward();
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.white,
        body: Center(
          child: FadeTransition(
            opacity: _opacity,
            child: ScaleTransition(
              key: const ValueKey('opening-heartbeat'),
              scale: _heartbeat,
              child: RepaintBoundary(
                child: Image.asset(
                  'lib/assests/logo.png',
                  width: 240,
                  height: 190,
                  fit: BoxFit.contain,
                  filterQuality: FilterQuality.high,
                  semanticLabel: 'Reddiph',
                ),
              ),
            ),
          ),
        ),
      );
}
