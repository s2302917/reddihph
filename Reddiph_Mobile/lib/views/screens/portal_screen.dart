import 'package:flutter/material.dart';
import '../../core/nav.dart';
import '../../models/role.dart';
import '../widgets/common.dart';
import 'login_screen.dart';

class PortalScreen extends StatelessWidget {
  const PortalScreen({super.key});

  static const _radius = BorderRadius.all(Radius.circular(14));

  /// The coloured Material (instead of a coloured Container) is what lets the
  /// InkWell ripple actually show - before, the Container painted over it.
  Widget tile(BuildContext c, Role r, {double h = 120}) => Expanded(
        child: Pressable(
          scale: .97,
          child: Material(
            color: r.color,
            borderRadius: _radius,
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => Nav.push(c, LoginScreen(role: r)),
              child: SizedBox(
                height: h,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Icon(r.icon, color: Colors.white, size: 22),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(r.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold,
                                        fontSize: 15)),
                                const SizedBox(height: 2),
                                Text(r.desc,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        color: Colors.white70, fontSize: 8)),
                              ],
                            ),
                          ),
                          const Icon(Icons.chevron_right,
                              color: Colors.white, size: 18),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final tileHeight = constraints.maxWidth < 360 ? 104.0 : 120.0;
          return Scaffold(
            body: SafeArea(
              child: SingleChildScrollView(
                padding:
                    const EdgeInsets.symmetric(horizontal: 28, vertical: 24),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight,
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Logo(),
                      const SizedBox(height: 20),
                      const Text('Emergency Coordination Portal',
                          style: TextStyle(
                              fontWeight: FontWeight.bold, fontSize: 16)),
                      const SizedBox(height: 40),
                      Row(children: [
                        tile(context, roles[0], h: tileHeight),
                        const SizedBox(width: 14),
                        tile(context, roles[1], h: tileHeight),
                      ]),
                      const SizedBox(height: 14),
                      Row(children: [
                        tile(context, roles[2], h: tileHeight),
                        const SizedBox(width: 14),
                        tile(context, roles[3], h: tileHeight),
                      ]),
                      const SizedBox(height: 14),
                      Row(children: [
                        tile(context, roles[4], h: tileHeight),
                        const SizedBox(width: 14),
                        tile(context, roles[5], h: tileHeight),
                      ]),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      );
}
