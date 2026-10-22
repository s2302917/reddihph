import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../core/theme.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/volunteer_widgets.dart';

class VolunteerProfileTab extends StatefulWidget {
  final VoidCallback onLogout;
  const VolunteerProfileTab({super.key, required this.onLogout});

  @override
  State<VolunteerProfileTab> createState() => _VolunteerProfileTabState();
}

class _VolunteerProfileTabState extends State<VolunteerProfileTab> {
  bool _notifications = true;
  bool _available = AuthController.I.user?['is_available'] as bool? ?? true;
  bool _savingProfile = false;

  Future<void> _toggleAvailability() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null || _savingProfile) return;
    final next = !_available;
    setState(() => _savingProfile = true);
    try {
      await MobileApiClient.I.setVolunteerAvailability(
        token: token,
        available: next,
      );
      if (!mounted) return;
      final user = AuthController.I.user;
      if (user != null) user['is_available'] = next;
      setState(() => _available = next);
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _editVehicle(String currentVehicle) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) return;
    final controller = TextEditingController(
      text: currentVehicle == 'Not provided' ? '' : currentVehicle,
    );
    final vehicleType = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Vehicle information'),
        content: TextField(
          controller: controller,
          maxLength: 80,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            hintText: 'e.g. motorcycle, car or on foot',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('CANCEL'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('SAVE'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (vehicleType == null) return;

    setState(() => _savingProfile = true);
    try {
      final savedVehicle = await MobileApiClient.I.updateVolunteerVehicle(
        token: token,
        vehicleType: vehicleType,
      );
      if (!mounted) return;
      final user = AuthController.I.user;
      if (user != null) user['vehicle_type'] = savedVehicle;
      setState(() {});
    } on MobileApiException catch (error) {
      if (mounted) _message(error.message);
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  void _message(String value) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    final p = DashboardController.I.profileFor('Volunteer');
    final user = AuthController.I.user ?? const <String, dynamic>{};
    final name = user['full_name'] as String? ?? p.title;
    final vehicle = user['vehicle_type'] as String? ?? 'Not provided';
    final rating = user['volunteer_rating'] as num?;
    final assisted = user['volunteer_assisted_incidents'] as int? ?? 0;

    return ListView(
        padding: const EdgeInsets.fromLTRB(20, 24, 20, 110),
        children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Profile',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 2),
                    Text(
                      rating == null
                          ? 'No ratings yet'
                          : '${rating.toStringAsFixed(1)} ★ · $assisted assisted',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                    ),
                  ]),
            ),
            CircleAvatar(
              radius: 12,
              backgroundColor: kVol,
              child: Text(p.initial,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.bold)),
            ),
          ]),
          const SizedBox(height: 20),
          DCard(
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(name,
                  style: const TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w800)),
              const SizedBox(height: 2),
              Text('Vehicle: $vehicle',
                  style: const TextStyle(fontSize: 8, color: Colors.black87)),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed:
                      _savingProfile ? null : () => _editVehicle(vehicle),
                  child: const Text('UPDATE VEHICLE'),
                ),
              ),
            ]),
            padding: const EdgeInsets.all(14),
            margin: EdgeInsets.zero,
          ),
          const SizedBox(height: 22),
          const Text('Settings',
              style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          DCard(
            Column(children: [
              InkWell(
                onTap: () => setState(() => _notifications = !_notifications),
                child: _row('Notification', _notifications ? 'On' : 'Off'),
              ),
              const Divider(height: 1),
              InkWell(
                onTap: _savingProfile ? null : _toggleAvailability,
                child: _row(
                    'Availability',
                    _savingProfile
                        ? 'Updating…'
                        : _available
                            ? 'Available now'
                            : 'Unavailable'),
              ),
              const Padding(
                padding: EdgeInsets.only(bottom: 12),
                child: Text(
                  'When available, your location is shared with residents only while this app is open. Allow location access to show your distance.',
                  style: TextStyle(fontSize: 8, color: Colors.black54),
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
                            fontSize: 9,
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

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(children: [
          Expanded(
              child: Text(label,
                  style: const TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w700))),
          Text(value,
              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900)),
        ]),
      );
}
