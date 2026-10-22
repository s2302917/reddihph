import 'package:flutter/material.dart';
import '../../controllers/dashboard_controller.dart';
import '../../core/theme.dart';
import '../widgets/doctor_widgets.dart';

/// Doctor > Profile tab.
class DoctorProfileTab extends StatefulWidget {
  final VoidCallback onBack;
  final VoidCallback onLogout;
  const DoctorProfileTab({
    super.key,
    required this.onBack,
    required this.onLogout,
  });

  @override
  State<DoctorProfileTab> createState() => _DoctorProfileTabState();
}

class _DoctorProfileTabState extends State<DoctorProfileTab> {
  bool _onDuty = true;
  bool _notifications = true;
  String _language = 'English';

  @override
  Widget build(BuildContext context) {
    final p = DashboardController.I.profileFor('Doctor');

    return ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 110),
        children: [
          // Back arrow
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton(
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
              alignment: Alignment.centerLeft,
              icon: const Icon(Icons.arrow_back_ios_new, size: 16),
              onPressed: widget.onBack,
            ),
          ),
          const SizedBox(height: 6),

          // Title row
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Profile',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.w900)),
                    SizedBox(height: 2),
                    Text('Dr. Villanueva · Emergency Medicine',
                        style: TextStyle(fontSize: 9, color: Colors.black38)),
                  ]),
            ),
            CircleAvatar(
              radius: 14,
              backgroundColor: kNavy,
              child: Text(p.initial,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold)),
            ),
          ]),
          const SizedBox(height: 18),

          // Hospital card
          DCard(
            Row(children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: kNavy,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Icon(Icons.add_box_outlined,
                    size: 20, color: Colors.white),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('District Hospital',
                          style: TextStyle(
                              fontSize: 10, fontWeight: FontWeight.w800)),
                      SizedBox(height: 2),
                      Text('License PRC-0042931 · ER shift, Mon–Sat',
                          style: TextStyle(fontSize: 8, color: Colors.black87)),
                    ]),
              ),
            ]),
            padding: const EdgeInsets.all(14),
            margin: EdgeInsets.zero,
          ),
          const SizedBox(height: 22),

          // Duty status
          const Text('Duty Status',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          DCard(
            Column(children: [
              _row(
                'On Duty',
                trailing: GestureDetector(
                  onTap: () => setState(() => _onDuty = !_onDuty),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
                    decoration: BoxDecoration(
                      color: _onDuty ? kStableBg : const Color(0xFFD9D9D9),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(_onDuty ? 'On' : 'Off',
                        style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w600,
                            color: _onDuty ? Colors.black87 : Colors.black54)),
                  ),
                ),
              ),
              const Divider(height: 1),
              _row(
                'Next shift',
                bold: false,
                trailing: const Text('Tue 06:00',
                    style: TextStyle(fontSize: 8, color: Colors.black54)),
              ),
            ]),
            padding: const EdgeInsets.symmetric(horizontal: 14),
            margin: EdgeInsets.zero,
          ),
          const SizedBox(height: 22),

          // Settings
          const Text('Settings',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          DCard(
            Column(children: [
              InkWell(
                onTap: () => setState(() => _notifications = !_notifications),
                child: _row(
                  'Notification',
                  trailing: Text(_notifications ? 'On' : 'Off',
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w900)),
                ),
              ),
              const Divider(height: 1),
              InkWell(
                onTap: () => setState(() => _language =
                    _language == 'English' ? 'Filipino' : 'English'),
                child: _row(
                  'Language',
                  trailing: Text(_language,
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w900)),
                ),
              ),
              const Divider(height: 1),
              InkWell(
                onTap: widget.onLogout,
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 14),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text('Log out',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                            color: kSos)),
                  ),
                ),
              ),
            ]),
            padding: const EdgeInsets.symmetric(horizontal: 14),
            margin: EdgeInsets.zero,
          ),
        ]);
  }

  Widget _row(String label, {required Widget trailing, bool bold = true}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(children: [
          Expanded(
            child: Text(label,
                style: TextStyle(
                    fontSize: 10,
                    fontWeight: bold ? FontWeight.w700 : FontWeight.w600)),
          ),
          trailing,
        ]),
      );
}
