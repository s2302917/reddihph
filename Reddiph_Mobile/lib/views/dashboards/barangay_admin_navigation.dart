import 'package:flutter/material.dart';

import '../../core/theme.dart';

class BarangayAdminNavigation extends StatelessWidget {
  const BarangayAdminNavigation({
    super.key,
    required this.selectedIndex,
    required this.onDestinationSelected,
    this.showApprovalBadge = false,
  });

  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final bool showApprovalBadge;

  static const _items = [
    (Icons.dashboard_outlined, 'Home'),
    (Icons.person_add_alt_1_outlined, 'Approval'),
    (Icons.history, 'Logs'),
    (Icons.account_circle_outlined, 'Profile'),
  ];

  @override
  Widget build(BuildContext context) => SafeArea(
        top: false,
        child: Container(
          height: 56,
          margin: const EdgeInsets.fromLTRB(16, 5, 16, 7),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE9E6E6)),
          ),
          child: Row(
            children: [
              for (var index = 0; index < _items.length; index++)
                Expanded(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(20),
                    onTap: () => onDestinationSelected(index),
                    child: Center(
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                _items[index].$1,
                                size: 19,
                                color: index == selectedIndex
                                    ? kBarangayAdminBrand
                                    : Colors.black54,
                              ),
                              const SizedBox(height: 3),
                              Text(
                                _items[index].$2,
                                style: TextStyle(
                                  fontSize: 10,
                                  height: 1,
                                  color: index == selectedIndex
                                      ? kBarangayAdminBrand
                                      : Colors.black54,
                                  fontWeight: index == selectedIndex
                                      ? FontWeight.w700
                                      : FontWeight.w400,
                                ),
                              ),
                            ],
                          ),
                          if (index == 1 && showApprovalBadge)
                            const Positioned(
                              right: -5,
                              top: -2,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: Color(0xFFBC413A),
                                  shape: BoxShape.circle,
                                ),
                                child: SizedBox(width: 5, height: 5),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      );
}
