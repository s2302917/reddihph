import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../models/nurse_portal.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/nurse_widgets.dart';

class NurseProfileTab extends StatefulWidget {
  const NurseProfileTab({
    super.key,
    required this.onBack,
    required this.onLogout,
  });

  final VoidCallback onBack;
  final VoidCallback onLogout;

  @override
  State<NurseProfileTab> createState() => _NurseProfileTabState();
}

class _NurseProfileTabState extends State<NurseProfileTab> {
  NurseProfile? _profile;
  String? _error;
  bool _loading = true;
  bool _saving = false;

  String? get _token => AuthController.I.user?['token'] as String?;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = _token;
    if (token == null || token.isEmpty) {
      setState(() {
        _error = 'Your session expired. Sign in again.';
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final profile = await MobileApiClient.I.nurseProfile(token: token);
      if (!mounted) return;
      NursePortalCache.saveProfile(token, profile);
      setState(() {
        _profile = profile;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      final cached = NursePortalCache.profile(token);
      setState(() {
        _profile = cached;
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _save({
    bool? isAvailable,
    bool? notificationsEnabled,
    String? language,
  }) async {
    final token = _token;
    final current = _profile;
    if (token == null || current == null || _error != null || _saving) return;
    setState(() => _saving = true);
    try {
      final updated = await MobileApiClient.I.updateNurseSettings(
        token: token,
        isAvailable: isAvailable ?? current.isAvailable,
        notificationsEnabled:
            notificationsEnabled ?? current.notificationsEnabled,
        language: language ?? current.language,
      );
      if (!mounted) return;
      NursePortalCache.saveProfile(token, updated);
      setState(() {
        _profile = updated;
        _saving = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final profile = _profile;
    if (_loading && profile == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (profile == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error ?? 'Nurse profile is unavailable.'),
              OutlinedButton(onPressed: _load, child: const Text('Retry')),
              TextButton(
                  onPressed: widget.onLogout, child: const Text('Log out')),
            ],
          ),
        ),
      );
    }
    final readOnly = _error != null || _saving;
    final initial = profile.fullName.trim().isEmpty
        ? 'N'
        : profile.fullName.trim().substring(0, 1).toUpperCase();
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
      children: [
        Row(
          children: [
            IconButton(
              visualDensity: VisualDensity.compact,
              icon: const Icon(Icons.arrow_back_ios_new, size: 16),
              onPressed: widget.onBack,
            ),
            const Expanded(
              child: Text(
                'Settings',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
            ),
            CircleAvatar(
              radius: 16,
              backgroundColor: kNurse,
              child: Text(
                initial,
                style: const TextStyle(color: Colors.white, fontSize: 12),
              ),
            ),
          ],
        ),
        if (_error != null)
          Container(
            margin: const EdgeInsets.symmetric(vertical: 10),
            padding: const EdgeInsets.all(10),
            color: const Color(0xFFFFF3D7),
            child: Row(
              children: [
                const Icon(Icons.cloud_off, size: 16),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Offline/read-only. Last loaded settings shown. $_error',
                    style: const TextStyle(fontSize: 9),
                  ),
                ),
                IconButton(
                  onPressed: _load,
                  icon: const Icon(Icons.refresh, size: 18),
                ),
              ],
            ),
          ),
        const SizedBox(height: 8),
        DCard(
          Row(
            children: [
              const CircleAvatar(
                radius: 23,
                backgroundColor: kNurse,
                child: Icon(Icons.person, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      profile.fullName,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text(
                      '${profile.department} · ${profile.hospitalName}',
                      style:
                          const TextStyle(fontSize: 9, color: Colors.black54),
                    ),
                    if (profile.licenseNumber?.isNotEmpty == true)
                      Text(
                        'License ${profile.licenseNumber}',
                        style: const TextStyle(
                          fontSize: 8,
                          color: Colors.black54,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          padding: const EdgeInsets.all(14),
          margin: EdgeInsets.zero,
        ),
        const SizedBox(height: 20),
        const Text(
          'Duty status',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 6),
        DCard(
          Column(
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Available for incoming cases',
                    style:
                        TextStyle(fontSize: 10, fontWeight: FontWeight.w700)),
                value: profile.isAvailable,
                onChanged:
                    readOnly ? null : (value) => _save(isAvailable: value),
              ),
              const Divider(height: 1),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Next shift', style: TextStyle(fontSize: 10)),
                trailing: Text(
                  profile.nextShift ?? 'Not scheduled',
                  style: const TextStyle(fontSize: 9, color: Colors.black54),
                ),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          margin: EdgeInsets.zero,
        ),
        const SizedBox(height: 18),
        const Text(
          'Preferences',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 6),
        DCard(
          Column(
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Notifications',
                    style:
                        TextStyle(fontSize: 10, fontWeight: FontWeight.w700)),
                value: profile.notificationsEnabled,
                onChanged: readOnly
                    ? null
                    : (value) => _save(notificationsEnabled: value),
              ),
              const Divider(height: 1),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Language', style: TextStyle(fontSize: 10)),
                trailing: DropdownButton<String>(
                  value: profile.language,
                  underline: const SizedBox.shrink(),
                  items: const [
                    DropdownMenuItem(value: 'English', child: Text('English')),
                    DropdownMenuItem(
                        value: 'Filipino', child: Text('Filipino')),
                    DropdownMenuItem(
                        value: 'Hiligaynon', child: Text('Hiligaynon')),
                  ],
                  onChanged: readOnly || _saving
                      ? null
                      : (value) {
                          if (value != null) _save(language: value);
                        },
                ),
              ),
              const Divider(height: 1),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text(
                  'Log out',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: kSos,
                  ),
                ),
                onTap: widget.onLogout,
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          margin: EdgeInsets.zero,
        ),
      ],
    );
  }
}
