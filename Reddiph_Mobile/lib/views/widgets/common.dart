import 'package:flutter/material.dart';
import 'package:reddiph/core/theme.dart';

class Logo extends StatelessWidget {
  const Logo({super.key});

  /// Shared element: the logo glides between the portal and login screens.
  /// The transparent Material keeps the mark clean while it glides.
  @override
  Widget build(BuildContext context) => const Hero(
        tag: 'reddiph-logo',
        child: Material(
          type: MaterialType.transparency,
          child: Image(
            image: AssetImage('lib/assests/logo.png'),
            width: 120,
            height: 120,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.high,
            semanticLabel: 'Reddiph',
          ),
        ),
      );
}

class RoleColorButton extends StatelessWidget {
  const RoleColorButton({
    super.key,
    required this.color,
    required this.onPressed,
    required this.child,
    this.height = 44,
  });

  final Color color;
  final VoidCallback? onPressed;
  final Widget child;
  final double height;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(6);
    return Semantics(
      button: true,
      enabled: onPressed != null,
      child: Material(
        color: Colors.transparent,
        child: Ink(
          decoration: BoxDecoration(
            color: onPressed == null ? roleButtonShade(color) : null,
            gradient: onPressed == null ? null : roleButtonGradient(color),
            borderRadius: radius,
          ),
          child: InkWell(
            onTap: onPressed,
            borderRadius: radius,
            child: SizedBox(
              width: double.infinity,
              height: height,
              child: Center(
                child: IconTheme(
                  data: const IconThemeData(color: Colors.white),
                  child: DefaultTextStyle(
                    style: const TextStyle(color: Colors.white),
                    child: child,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class Header extends StatelessWidget {
  final String title, sub;
  final Color color;
  final String initial;
  final Widget? subWidget;
  const Header(this.title, this.sub, this.color, this.initial,
      {super.key, this.subWidget});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
        child: Row(children: [
          Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(title,
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.bold)),
                subWidget ?? Text(sub, style: const TextStyle(fontSize: 11)),
              ])),
          CircleAvatar(
              radius: 14,
              backgroundColor: color,
              child: Text(initial,
                  style: const TextStyle(color: Colors.white, fontSize: 12))),
        ]),
      );
}

class Section extends StatelessWidget {
  final String text;
  const Section(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
      child: Text(text,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)));
}

const _boxDecoration = BoxDecoration(
  color: Colors.white,
  borderRadius: BorderRadius.all(Radius.circular(10)),
  border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
);

class Box extends StatelessWidget {
  final Widget child;
  final EdgeInsets margin;
  const Box(this.child,
      {super.key, this.margin = const EdgeInsets.fromLTRB(20, 0, 20, 10)});
  @override
  Widget build(BuildContext context) => Container(
        margin: margin,
        padding: const EdgeInsets.all(12),
        decoration: _boxDecoration,
        child: child,
      );
}

class Pill extends StatelessWidget {
  final String text;
  final Color bg, fg;
  const Pill(this.text, this.bg, this.fg, {super.key});
  @override
  Widget build(BuildContext context) => AnimatedContainer(
        duration: kMedium,
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
        decoration: BoxDecoration(
            color: bg,
            borderRadius: const BorderRadius.all(Radius.circular(20))),
        child: Text(text,
            style: TextStyle(
                fontSize: 10, color: fg, fontWeight: FontWeight.w600)),
      );
}

/// Press feedback for any tappable widget: it shrinks slightly while a finger
/// is down and springs back on release. Uses a raw [Listener], so it never
/// competes with the child's own tap / long-press / scroll gestures.
class Pressable extends StatefulWidget {
  final Widget child;
  final double scale;
  final bool enabled;
  const Pressable(
      {super.key, required this.child, this.scale = 0.96, this.enabled = true});

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  static const _slop = 18.0; // cancel the squish if the finger starts a scroll
  bool _down = false;
  Offset? _start;

  void _set(bool v) {
    if (_down != v) setState(() => _down = v);
  }

  @override
  Widget build(BuildContext context) {
    final pressed = _down && widget.enabled;
    return Listener(
      onPointerDown: (e) {
        _start = e.position;
        _set(true);
      },
      onPointerMove: (e) {
        final s = _start;
        if (_down && s != null && (e.position - s).distance > _slop) {
          _set(false);
        }
      },
      onPointerUp: (_) => _set(false),
      onPointerCancel: (_) => _set(false),
      child: AnimatedScale(
        scale: pressed ? widget.scale : 1,
        duration: Duration(milliseconds: pressed ? 80 : 180),
        curve: pressed ? Curves.easeOut : Curves.easeOutBack,
        child: widget.child,
      ),
    );
  }
}

class Btn extends StatelessWidget {
  final String text;
  final Color color;
  final VoidCallback? onTap;
  final double fontSize;
  final bool disabled;
  const Btn(this.text, this.color,
      {super.key, this.onTap, this.fontSize = 12, this.disabled = false});
  @override
  Widget build(BuildContext context) => Pressable(
        enabled: !disabled,
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(
            style: FilledButton.styleFrom(
                backgroundColor: color,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(6))),
            onPressed: disabled ? null : (onTap ?? () {}),
            child: Text(text, style: TextStyle(fontSize: fontSize)),
          ),
        ),
      );
}

/// Fades + slides a child in during part ([begin]..[end], 0-1) of a parent
/// animation, so a screen's content can appear one piece at a time from a
/// single AnimationController.
class StaggerIn extends StatelessWidget {
  final Animation<double> animation;
  final double begin, end;
  final Widget child;
  const StaggerIn({
    super.key,
    required this.animation,
    required this.begin,
    required this.end,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    final a = CurveTween(
      curve: Interval(begin, end, curve: Curves.easeOutCubic),
    ).animate(animation);
    return FadeTransition(
      opacity: a,
      child: SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, .12), end: Offset.zero)
            .animate(a),
        child: child,
      ),
    );
  }
}

/// A soft ring that expands and fades behind [child] on a loop. Driven by one
/// controller through Fade/Scale transitions inside a RepaintBoundary, so the
/// rest of the screen is never repainted. Stops if the user has turned off
/// animations in system settings.
class PulseRing extends StatefulWidget {
  final Color color;
  final double size;
  final Widget child;
  const PulseRing(
      {super.key,
      required this.color,
      required this.size,
      required this.child});

  @override
  State<PulseRing> createState() => _PulseRingState();
}

class _PulseRingState extends State<PulseRing>
    with SingleTickerProviderStateMixin {
  static const _maxScale = 1.2;
  late final AnimationController _c = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 1800));
  late final Animation<double> _t =
      CurveTween(curve: Curves.easeOut).animate(_c);
  late final Animation<double> _opacity =
      Tween<double>(begin: .30, end: 0).animate(_t);
  late final Animation<double> _scale =
      Tween<double>(begin: 1, end: _maxScale).animate(_t);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final box = widget.size * _maxScale; // room for the ring: no overlap
    return SizedBox(
      width: box,
      height: box,
      child: Stack(
        alignment: Alignment.center,
        children: [
          IgnorePointer(
            child: RepaintBoundary(
              child: FadeTransition(
                opacity: _opacity,
                child: ScaleTransition(
                  scale: _scale,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                        color: widget.color, shape: BoxShape.circle),
                    child: SizedBox(width: widget.size, height: widget.size),
                  ),
                ),
              ),
            ),
          ),
          widget.child,
        ],
      ),
    );
  }
}

/// Stack layout for AnimatedSwitcher that fills the parent, so cross-fading
/// screens/tabs never jump in size.
Widget expandLayout(Widget? current, List<Widget> previous) => Stack(
      fit: StackFit.expand,
      children: [...previous, if (current != null) current],
    );

/// Same, but left-aligned (for swapping text inside a column).
Widget leftLayout(Widget? current, List<Widget> previous) => Stack(
      alignment: Alignment.centerLeft,
      children: [...previous, if (current != null) current],
    );

/// The resident Home / Status / Profile pill bar. Previously copy-pasted into
/// four screens; now one widget with press feedback and safe-area handling.
class ResidentBottomBar extends StatelessWidget {
  final VoidCallback onHome, onStatus, onProfile;
  const ResidentBottomBar({
    super.key,
    required this.onHome,
    required this.onStatus,
    required this.onProfile,
  });

  @override
  Widget build(BuildContext context) => SafeArea(
        top: false,
        minimum: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: SizedBox(
            height: 54,
            child: Material(
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              shape: const RoundedRectangleBorder(
                borderRadius: BorderRadius.all(Radius.circular(28)),
                side: BorderSide(color: Colors.black12),
              ),
              child: Row(children: [
                Expanded(child: _NavItem(Icons.home, 'Home', onHome)),
                Expanded(child: _NavItem(Icons.history, 'Status', onStatus)),
                Expanded(
                    child:
                        _NavItem(Icons.account_circle, 'Profile', onProfile)),
              ]),
            ),
          ),
        ),
      );
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _NavItem(this.icon, this.label, this.onTap);

  @override
  Widget build(BuildContext context) => Pressable(
        scale: .9,
        child: InkWell(
          onTap: onTap,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 18, color: Colors.black),
              Text(label,
                  style: const TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      );
}
