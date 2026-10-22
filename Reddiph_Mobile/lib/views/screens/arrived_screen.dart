import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../widgets/common.dart';
import 'shell.dart';

class ArrivedScreen extends StatefulWidget {
  const ArrivedScreen({super.key});

  @override
  State<ArrivedScreen> createState() => _ArrivedScreenState();
}

class _ArrivedScreenState extends State<ArrivedScreen>
    with SingleTickerProviderStateMixin {
  final controller = SosController.I;

  late final AnimationController _enter;
  late final Animation<double> _checkScale;

  @override
  void initState() {
    super.initState();
    _enter = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..forward();
    _checkScale = CurveTween(
      curve: const Interval(0, .5, curve: Curves.easeOutBack),
    ).animate(_enter);
  }

  @override
  void dispose() {
    _enter.dispose();
    super.dispose();
  }

  void submit() {
    controller.completeCase();
    Nav.resetTo(context, const Shell(initialTab: 0));
  }

  void openTab(int tab) {
    Nav.resetTo(context, Shell(initialTab: tab));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, size: 16),
          onPressed: () => Navigator.maybePop(context),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        children: [
          const SizedBox(height: 20),
          Center(
            child: ScaleTransition(
              scale: _checkScale,
              child: const DecoratedBox(
                decoration: BoxDecoration(
                  color: kResidentBrand,
                  shape: BoxShape.circle,
                ),
                child: SizedBox(
                  width: 70,
                  height: 70,
                  child: Icon(Icons.check, color: Colors.white, size: 42),
                ),
              ),
            ),
          ),
          const SizedBox(height: 14),
          StaggerIn(
            animation: _enter,
            begin: .15,
            end: .5,
            child: const Column(
              children: [
                Text(
                  'Arrived safely',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                ),
                SizedBox(height: 4),
                Text(
                  'District Hospital · 11:26 AM',
                  style: TextStyle(fontSize: 9, color: Colors.black45),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          StaggerIn(
            animation: _enter,
            begin: .3,
            end: .65,
            child: const Column(
              children: [
                _TimelineRow('Reported', '11:14'),
                _TimelineRow('Responder arrived on scene', '11:18'),
                _TimelineRow('Arrived at hospital', '11:26'),
              ],
            ),
          ),
          const SizedBox(height: 14),
          StaggerIn(
            animation: _enter,
            begin: .45,
            end: .8,
            child: const _RatingCard(),
          ),
          const SizedBox(height: 10),
          StaggerIn(
            animation: _enter,
            begin: .6,
            end: 1,
            child: Pressable(
              child: SizedBox(
                height: 42,
                child: FilledButton(
                  onPressed: submit,
                  style: FilledButton.styleFrom(
                    backgroundColor: kResidentBrand,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(6),
                    ),
                  ),
                  child: const Text(
                    'SUBMIT & CLOSE REPORT',
                    style: TextStyle(fontSize: 10),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: ResidentBottomBar(
        onHome: () => openTab(0),
        onStatus: () => openTab(1),
        onProfile: () => openTab(2),
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  final String left, right;
  const _TimelineRow(this.left, this.right);

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: Colors.black12)),
        ),
        child: Row(
          children: [
            Expanded(child: Text(left, style: const TextStyle(fontSize: 9))),
            Text(
              right,
              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      );
}

class _RatingCard extends StatelessWidget {
  const _RatingCard();

  @override
  Widget build(BuildContext context) => const DecoratedBox(
        decoration: BoxDecoration(
          border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
          borderRadius: BorderRadius.all(Radius.circular(8)),
        ),
        child: Padding(
          padding: EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Rate your responder team',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
              ),
              SizedBox(height: 8),
              _RatingStars(),
              TextField(
                minLines: 2,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Leave a feedback (optional)',
                  hintStyle: TextStyle(fontSize: 9),
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
              ),
            ],
          ),
        ),
      );
}

/// Owns its own rating state, so tapping a star rebuilds five icons instead of
/// the whole screen.
class _RatingStars extends StatefulWidget {
  const _RatingStars();

  @override
  State<_RatingStars> createState() => _RatingStarsState();
}

class _RatingStarsState extends State<_RatingStars> {
  int rating = 4;

  @override
  Widget build(BuildContext context) => Row(
        children: List.generate(5, (index) {
          final value = index + 1;
          final on = value <= rating;
          return IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: () {
              if (value == rating) return;
              HapticFeedback.selectionClick();
              setState(() => rating = value);
            },
            icon: AnimatedSwitcher(
              duration: kFast,
              transitionBuilder: (child, anim) =>
                  ScaleTransition(scale: anim, child: child),
              child: Icon(
                on ? Icons.star : Icons.star_border,
                key: ValueKey<bool>(on),
                size: 20,
                color: on ? Colors.black : Colors.black45,
              ),
            ),
          );
        }),
      );
}
