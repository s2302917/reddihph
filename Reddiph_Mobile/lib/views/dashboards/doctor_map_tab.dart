import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/doctor_portal.dart';
import '../../services/mobile_api_client.dart';
import '../../services/open_street_map_api.dart';
import '../screens/doctor_case_detail.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/open_street_map.dart';
import '../widgets/responder_widgets.dart';

class DoctorMapTab extends StatefulWidget {
  const DoctorMapTab({super.key});

  @override
  State<DoctorMapTab> createState() => _DoctorMapTabState();
}

class _DoctorMapTabState extends State<DoctorMapTab> {
  DoctorProfile? _profile;
  List<DoctorCaseRecord> _cases = const [];
  int _selected = 0;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() {
        _loading = false;
        _error = 'Your session has expired. Please sign in again.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final dashboard = await MobileApiClient.I.doctorDashboard(token: token);
      if (!mounted) return;
      setState(() {
        _profile = dashboard.profile;
        _cases = dashboard.cases;
        _selected = 0;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final selected = _cases.isEmpty ? null : _cases[_selected];
    final severity =
        selected == null ? null : doctorSeverity(selected.statusLabel);
    final hospitalLocation = _profile?.hospitalLatitude == null ||
            _profile?.hospitalLongitude == null
        ? null
        : LatLng(
            _profile!.hospitalLatitude!,
            _profile!.hospitalLongitude!,
          );
    final incidentLocation = selected?.latitude == null ||
            selected?.longitude == null
        ? null
        : LatLng(selected!.latitude!, selected.longitude!);
    final markers = <OpenStreetMapMarker>[
      if (hospitalLocation != null)
        OpenStreetMapMarker(
          point: hospitalLocation,
          label: _profile?.hospitalName ?? 'Hospital',
          color: kNavy,
          icon: Icons.local_hospital,
          showLabel: true,
        ),
      for (int index = 0; index < _cases.length; index++)
        if (_cases[index].latitude != null &&
            _cases[index].longitude != null)
          OpenStreetMapMarker(
            point: LatLng(_cases[index].latitude!, _cases[index].longitude!),
            label: '${_cases[index].patient} · ${_cases[index].title}',
            color: index == _selected ? kSos : kResponderBrown,
            icon: Icons.emergency,
            onTap: () => setState(() => _selected = index),
            showLabel: index == _selected,
          ),
    ];

    return Stack(
      children: [
        Positioned.fill(
          child: OpenStreetMapView(
            radius: 0,
            center: incidentLocation ??
                hospitalLocation ??
                OpenStreetMapApi.hospital,
            markers: markers,
          ),
        ),
        Positioned(
          right: 16,
          top: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: const [
                BoxShadow(color: Colors.black12, blurRadius: 4)
              ],
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.circle, size: 7),
                SizedBox(width: 5),
                Text('Cases', style: TextStyle(fontSize: 9)),
              ],
            ),
          ),
        ),
        if (_loading)
          const Positioned(
            top: 70,
            left: 20,
            right: 20,
            child: LinearProgressIndicator(minHeight: 2),
          ),
        if (_error != null)
          Positioned(
            top: 68,
            left: 16,
            right: 16,
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(_error!,
                          style: const TextStyle(fontSize: 9, color: kSos)),
                    ),
                    TextButton(onPressed: _load, child: const Text('RETRY')),
                  ],
                ),
              ),
            ),
          ),
        Positioned(
          left: 0,
          right: 0,
          bottom: 0,
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 100),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 12)],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 56,
                  height: 5,
                  decoration: BoxDecoration(
                      color: Colors.black26,
                      borderRadius: BorderRadius.circular(3)),
                ),
                const SizedBox(height: 10),
                if (selected == null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    child: Text(
                      _loading
                          ? 'Loading assigned cases…'
                          : 'No incoming cases are assigned.',
                      style:
                          const TextStyle(fontSize: 10, color: Colors.black54),
                    ),
                  )
                else ...[
                  Align(
                    alignment: Alignment.centerRight,
                    child: SmallPill(selected.statusLabel, severity!),
                  ),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      '${selected.patient} · ${selected.title}',
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.bold),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      '${selected.location} · ${selected.etaLabel}',
                      style:
                          const TextStyle(fontSize: 8, color: Colors.black54),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (incidentLocation == null)
                    const Text(
                      'Incident coordinates are not available for this case.',
                      style: TextStyle(fontSize: 8, color: Colors.black45),
                    ),
                  const SizedBox(height: 8),
                  if (_cases.length > 1)
                    DropdownButtonFormField<int>(
                      initialValue: _selected,
                      isDense: true,
                      decoration: const InputDecoration(
                        labelText: 'Incoming case',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        for (int index = 0; index < _cases.length; index++)
                          DropdownMenuItem(
                            value: index,
                            child: Text(
                              '${_cases[index].patient} · ${_cases[index].title}',
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 10),
                            ),
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) setState(() => _selected = value);
                      },
                    ),
                  const SizedBox(height: 8),
                  NavyButton(
                    'OPEN PATIENT RECORD',
                    onTap: () => Nav.push(
                      context,
                      DoctorCaseDetail(
                        incidentId: selected.id,
                        initialItem: selected,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
