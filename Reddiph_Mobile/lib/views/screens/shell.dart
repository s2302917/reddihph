import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';

import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../controllers/sos_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/responder_dashboard.dart';
import '../../services/location_service.dart';
import '../../services/mobile_api_client.dart';
import '../dashboards/doctor_home.dart';
import '../dashboards/nurse_home.dart';
import '../dashboards/resident_home.dart';
import '../dashboards/responder_home.dart';
import '../dashboards/volunteer_home.dart';
import '../dashboards/barangay_admin_home.dart';
import '../widgets/common.dart';
import '../widgets/open_street_map.dart';
import '../widgets/responder_avatar.dart';
import '../../services/open_street_map_api.dart';
import 'portal_screen.dart';
import 'sos_screen.dart';
import 'sos_sending_screen.dart';
import 'tracking_screen.dart';
import 'resident_incident_report_screen.dart';
import 'resident_report_history_screen.dart';
import '../dashboards/responder_map.dart';
import '../dashboards/responder_queue.dart';

import '../screens/doctor_shell.dart';
import 'nurse_shell.dart';
import 'volunteer_shell.dart';

const _residentColor = kResidentBrand;

class Shell extends StatefulWidget {
  final int initialTab;

  const Shell({super.key, this.initialTab = 0});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  final role = AuthController.I.role!;
  late int tab = widget.initialTab;
  late final bool _isResident = role.name == 'Resident';
  Timer? _responderAlertTimer;
  bool _checkingResponderAlerts = false;
  bool _responderAlertDialogOpen = false;

  @override
  void initState() {
    super.initState();
    unawaited(SosController.I.loadActiveIncident());
    if (role.name == 'Responder') {
      unawaited(_loadResponderProfileImage());
      unawaited(_pollResponderAlerts());
      _responderAlertTimer = Timer.periodic(
        const Duration(seconds: 20),
        (_) => _pollResponderAlerts(),
      );
    }
  }

  @override
  void dispose() {
    _responderAlertTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadResponderProfileImage() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) return;
    try {
      final settings = await MobileApiClient.I.responderProfile(token: token);
      if (AuthController.I.user?['token'] != token) return;
      AuthController.I.updateResponderProfileImage(
        MobileApiClient.I.resolveResponderProfileImageUrl(
          settings.profileImageUrl,
        ),
      );
    } on MobileApiException catch (error) {
      debugPrint(
          'Responder profile image could not be loaded: ${error.message}');
    }
  }

  Future<void> _pollResponderAlerts() async {
    final token = AuthController.I.user?['token'] as String?;
    if (!mounted ||
        token == null ||
        _checkingResponderAlerts ||
        _responderAlertDialogOpen) {
      return;
    }
    _checkingResponderAlerts = true;
    try {
      double? latitude;
      double? longitude;
      try {
        final position = await LocationService.currentPosition();
        latitude = position.latitude;
        longitude = position.longitude;
      } on LocationUnavailableException {
        // Continue checking queued alerts even when GPS is temporarily unavailable.
      }
      final alerts = await MobileApiClient.I.responderAlerts(
        token: token,
        latitude: latitude,
        longitude: longitude,
      );
      if (mounted && alerts.isNotEmpty && !_responderAlertDialogOpen) {
        await _showResponderAlert(token, alerts.first);
      }
    } on MobileApiException catch (error) {
      debugPrint('Responder alert polling failed: ${error.message}');
    } finally {
      _checkingResponderAlerts = false;
    }
  }

  Future<void> _showResponderAlert(String token, ResponderAlert alert) async {
    _responderAlertDialogOpen = true;
    unawaited(SystemSound.play(SystemSoundType.alert));
    HapticFeedback.vibrate();
    final soundTimer = Timer.periodic(const Duration(seconds: 2), (_) {
      unawaited(SystemSound.play(SystemSoundType.alert));
      HapticFeedback.vibrate();
    });
    try {
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => PopScope(
          canPop: false,
          child: AlertDialog(
            icon: const Icon(Icons.emergency, color: kSos, size: 36),
            title: const Text('New emergency dispatch'),
            content: Text(
              '${alert.incidentType}\n'
              '${alert.barangay ?? alert.location}\n'
              'Case #${alert.incidentId}'
              '${alert.etaMinutes == null ? '' : '\nETA ${alert.etaMinutes} min'}',
            ),
            actions: [
              FilledButton(
                onPressed: () async {
                  try {
                    await MobileApiClient.I.acknowledgeResponderAlert(
                      token: token,
                      alertId: alert.alertId,
                    );
                    if (dialogContext.mounted) {
                      Navigator.of(dialogContext).pop();
                    }
                  } on MobileApiException catch (error) {
                    if (dialogContext.mounted) {
                      ScaffoldMessenger.of(dialogContext).showSnackBar(
                        SnackBar(content: Text(error.message)),
                      );
                    }
                  }
                },
                child: const Text('STOP ALERT AND ACCEPT'),
              ),
            ],
          ),
        ),
      );
    } finally {
      soundTimer.cancel();
      _responderAlertDialogOpen = false;
    }
  }

  // Role never changes while the shell is alive, so build these once.
  List<Widget> get _responderDestinations => [
        const NavigationDestination(icon: Icon(Icons.home), label: 'Home'),
        const NavigationDestination(
            icon: Icon(Icons.update), label: 'Incoming'),
        const NavigationDestination(
            icon: Icon(Icons.map_outlined), label: 'Map'),
        NavigationDestination(
          icon: ResponderAvatar(
            initials: initialsOf(
              (AuthController.I.user?['full_name'] ??
                      AuthController.I.user?['name'] ??
                      'Responder')
                  .toString(),
            ),
            radius: 12,
          ),
          label: 'Profile',
        ),
      ];

  late final List<Widget> _destinations = role.name == 'Responder'
      ? _responderDestinations
      : [
          NavigationDestination(
            icon: const Icon(Icons.home),
            label: role.name == 'Volunteer' ? 'Request' : 'Home',
          ),
          NavigationDestination(
            icon: const Icon(Icons.history),
            label: _isResident ? 'Status' : 'History',
          ),
          const NavigationDestination(
            icon: Icon(Icons.account_circle),
            label: 'Profile',
          ),
        ];
  void _sendSos() {
    final sos = SosController.I;
    if (sos.incidentId != null &&
        sos.stage != EmergencyStage.idle &&
        sos.stage != EmergencyStage.completed) {
      if (_isResident) {
        _openLiveStatus();
      } else {
        Nav.push(context, const TrackingScreen());
      }
      return;
    }
    if (_isResident) {
      sos.send();
      Nav.push(context, const SosSendingScreen());
      return;
    }

    SosController.I.send();
    Nav.push(context, const SosSendingScreen());
  }

  void _openSos() => Nav.push(context, SosScreen(role: role));

  void _openResidentIncidentReport() {
    final sos = SosController.I;
    if (sos.stage != EmergencyStage.idle &&
        sos.stage != EmergencyStage.completed) {
      Nav.push(context, const TrackingScreen());
      return;
    }
    Nav.push(context, const ResidentIncidentReportScreen());
  }

  void _logout() {
    SosController.I.reset();
    AuthController.I.logout();
    Nav.resetTo(context, const PortalScreen());
  }

  void _selectTab(int index) {
    if (index == tab) return;
    HapticFeedback.selectionClick();
    setState(() => tab = index);
  }

  void _openLiveStatus() => Nav.push(context, const ResidentLiveStatusScreen());

  Widget _residentBody() {
    switch (tab) {
      case 1:
        return ResidentReportHistoryScreen(
          token: AuthController.I.user?['token'] as String?,
        );
      case 2:
        return const ResidentProfileHome(roleColor: _residentColor);
      default:
        return ResidentHome(
          role: role,
          onSos: _sendSos,
          onReportIncident: _openResidentIncidentReport,
          onViewLiveStatus: _openLiveStatus,
        );
    }
  }

  Widget _responderBody() {
    switch (tab) {
      case 1:
        return const ResponderQueueTab();
      case 2:
        return const ResponderMapTab();
      case 3:
        return ResponderProfileTab(
          onBack: () => _selectTab(0),
          onLogout: _logout,
        );
      default:
        return const ResponderHome();
    }
  }

  Widget _body() {
    if (_isResident) return _residentBody();
    if (role.name == 'Responder') return _responderBody();

    if (tab == 1) {
      return const Center(child: Text('No history yet'));
    }

    if (tab == 2) {
      return Center(
        child: Pressable(
          child: RoleColorButton(
            color: role.color,
            onPressed: _logout,
            child: const Text('Log out'),
          ),
        ),
      );
    }

    switch (role.name) {
      case 'Responder':
        return const ResponderHome();
      case 'Doctor':
        return const DoctorHome();
      case 'Nurse':
        return const NurseHome();
      default:
        return VolunteerHome(role: role);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (role.name == 'Barangay Admin') {
      return BarangayAdminHome(onLogout: _logout);
    }

    if (role.name == 'Doctor') return const DoctorShell();
    if (role.name == 'Nurse') return const NurseShell();
    if (role.name == 'Volunteer') return const VolunteerShell();

    final showFab =
        _isResident ? tab == 2 : role.name == 'Responder' || tab < 2;

    return Scaffold(
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          switchInCurve: Curves.easeOut,
          switchOutCurve: Curves.easeIn,
          layoutBuilder: expandLayout,
          child: KeyedSubtree(key: ValueKey<int>(tab), child: _body()),
        ),
      ),
      // One FAB that scales in/out instead of two copy-pasted ones popping in.
      floatingActionButton: ExcludeSemantics(
        excluding: !showFab,
        child: IgnorePointer(
          ignoring: !showFab,
          child: AnimatedScale(
            scale: showFab ? 1 : 0,
            duration: kMedium,
            curve: showFab ? Curves.easeOutBack : Curves.easeIn,
            child: FloatingActionButton(
              heroTag: null, // stops a Hero flight between old/new Shell
              backgroundColor: kSos,
              shape: const CircleBorder(),
              onPressed: _isResident ? _sendSos : _openSos,
              child: const Text(
                'SOS',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Material(
            color: Colors.white,
            elevation: 2,
            borderRadius: BorderRadius.circular(28),
            clipBehavior: Clip.antiAlias,
            child: NavigationBar(
              height: 68,
              backgroundColor: Colors.transparent,
              surfaceTintColor: Colors.transparent,
              elevation: 0,
              indicatorColor: kResponderBrand.withAlpha(26),
              shadowColor: Colors.transparent,
              selectedIndex: tab,
              animationDuration: const Duration(milliseconds: 300),
              onDestinationSelected: _selectTab,
              destinations: _destinations,
            ),
          ),
        ),
      ),
    );
  }
}

class _VehicleEditorSheet extends StatefulWidget {
  const _VehicleEditorSheet({
    required this.token,
    required this.driverResponderEnabled,
    required this.initialVehicleType,
    required this.initialCallSign,
    required this.initialPlateNumber,
    required this.onSaved,
  });

  final String token;
  final bool driverResponderEnabled;
  final String initialVehicleType;
  final String initialCallSign;
  final String initialPlateNumber;
  final ValueChanged<ResponderProfileSettings> onSaved;

  @override
  State<_VehicleEditorSheet> createState() => _VehicleEditorSheetState();
}

class _VehicleEditorSheetState extends State<_VehicleEditorSheet> {
  final _formKey = GlobalKey<FormState>();
  late String _vehicleType = widget.initialVehicleType;
  late final TextEditingController _callSignController;
  late final TextEditingController _plateNumberController;
  bool _saving = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _callSignController = TextEditingController(text: widget.initialCallSign);
    _plateNumberController =
        TextEditingController(text: widget.initialPlateNumber);
  }

  @override
  void dispose() {
    _callSignController.dispose();
    _plateNumberController.dispose();
    super.dispose();
  }

  String? _validateVehicleType(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Choose Ambulance or PTV.';
    }
    try {
      MobileApiClient.normalizeResponderVehicleType(value);
      return null;
    } on FormatException {
      return 'Choose Ambulance or PTV.';
    } on ArgumentError {
      return 'Choose Ambulance or PTV.';
    }
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final vehicleType =
        MobileApiClient.normalizeResponderVehicleType(_vehicleType);
    setState(() {
      _saving = true;
      _errorMessage = null;
    });

    try {
      final settings = await MobileApiClient.I.saveResponderVehicle(
        token: widget.token,
        enabled: widget.driverResponderEnabled,
        vehicleType: vehicleType,
        callSign: _callSignController.text.trim(),
        plateNumber: _plateNumberController.text.trim(),
      );
      if (!mounted) return;
      widget.onSaved(settings);
      if (Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _errorMessage = error is MobileApiException
            ? error.message
            : 'Vehicle details could not be saved.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    const options = ['Ambulance', 'PTV'];
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Vehicle Details',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed:
                        _saving ? null : () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue:
                    options.contains(_vehicleType) ? _vehicleType : 'Ambulance',
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Vehicle Type',
                  border: OutlineInputBorder(),
                ),
                items: options
                    .map(
                      (type) => DropdownMenuItem<String>(
                        value: type,
                        child: Text(type),
                      ),
                    )
                    .toList(),
                onChanged: _saving
                    ? null
                    : (value) {
                        if (value != null) {
                          setState(() => _vehicleType = value);
                        }
                      },
                validator: _validateVehicleType,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _callSignController,
                maxLength: 30,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(
                  labelText: 'Call Sign',
                  border: OutlineInputBorder(),
                ),
                validator: (value) => value?.trim().isNotEmpty == true
                    ? null
                    : 'Please enter the call sign.',
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _plateNumberController,
                maxLength: 30,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(
                  labelText: 'Plate Number',
                  border: OutlineInputBorder(),
                ),
                validator: (value) => value?.trim().isNotEmpty == true
                    ? null
                    : 'Please enter the plate number.',
              ),
              if (_errorMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  style: const TextStyle(color: Colors.red),
                ),
              ],
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed:
                          _saving ? null : () => Navigator.of(context).pop(),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      onPressed: _saving ? null : _save,
                      child: _saving
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Save Changes'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class ResponderProfileTab extends StatefulWidget {
  final VoidCallback onBack;
  final VoidCallback onLogout;

  const ResponderProfileTab({
    super.key,
    required this.onBack,
    required this.onLogout,
  });

  @override
  State<ResponderProfileTab> createState() => _ResponderProfileTabState();
}

class _ResponderProfileTabState extends State<ResponderProfileTab> {
  String? _profileImageUrl;
  ResponderProfileSettings? _profileSettings;
  bool _profileLoading = true;
  bool _profileImageSaving = false;
  bool _driverSaving = false;
  String? _profileError;

  @override
  void initState() {
    super.initState();
    unawaited(_refreshProfileData());
  }

  Future<void> _refreshProfileData() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      setState(() {
        _profileLoading = false;
        _profileError = 'Sign in again to load your profile.';
      });
      return;
    }
    try {
      final settings = await MobileApiClient.I.responderProfile(token: token);
      final imageUrl = MobileApiClient.I.resolveResponderProfileImageUrl(
        settings.profileImageUrl,
      );
      final user = AuthController.I.user;
      if (user != null) {
        user['driver_responder'] = settings.driverResponderEnabled;
        user['vehicle_type'] = settings.vehicleType;
        user['call_sign'] = settings.callSign;
        user['plate_number'] = settings.plateNumber;
        user['monthly_dispatch_count'] = settings.monthlyDispatchCount;
      }
      if (AuthController.I.user?['token'] == token) {
        AuthController.I.updateResponderProfileImage(imageUrl);
      }
      if (!mounted) return;
      setState(() {
        _profileSettings = settings;
        _profileImageUrl = imageUrl;
        _profileLoading = false;
        _profileError = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _profileLoading = false;
        _profileError = error is MobileApiException
            ? error.message
            : 'Unable to load your profile right now.';
      });
    }
  }

  Future<void> _pickProfileImage() async {
    try {
      final picker = ImagePicker();
      final file = await picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 512,
        maxHeight: 512,
        requestFullMetadata: false,
      );
      if (file == null || !mounted) return;
      if (await file.length() > 5 * 1024 * 1024) {
        _showProfileMessage('Choose an image smaller than 5 MB.');
        return;
      }
      final token = AuthController.I.user?['token'] as String?;
      if (token == null) {
        _showProfileMessage('Sign in again to update your profile photo.');
        return;
      }
      setState(() => _profileImageSaving = true);
      final imageUrl = await MobileApiClient.I.uploadResponderProfileImage(
        token: token,
        photo: file,
      );
      if (AuthController.I.user?['token'] != token) return;
      AuthController.I.updateResponderProfileImage(imageUrl);
      if (mounted) setState(() => _profileImageUrl = imageUrl);
    } on MobileApiException catch (error) {
      _showProfileMessage(error.message);
    } on PlatformException {
      _showProfileMessage('Unable to access the selected photo.');
    } on Exception {
      _showProfileMessage('The profile photo could not be saved.');
    } finally {
      if (mounted) setState(() => _profileImageSaving = false);
    }
  }

  Future<void> _resetProfileImage() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      _showProfileMessage('Sign in again to update your profile photo.');
      return;
    }
    setState(() => _profileImageSaving = true);
    try {
      await MobileApiClient.I.resetResponderProfileImage(token: token);
      if (AuthController.I.user?['token'] != token) return;
      AuthController.I.updateResponderProfileImage(null);
      if (mounted) setState(() => _profileImageUrl = null);
    } on MobileApiException catch (error) {
      _showProfileMessage(error.message);
    } finally {
      if (mounted) setState(() => _profileImageSaving = false);
    }
  }

  void _showProfileMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  void _acceptProfileSettings(ResponderProfileSettings settings) {
    final imageUrl = MobileApiClient.I.resolveResponderProfileImageUrl(
      settings.profileImageUrl,
    );
    final user = AuthController.I.user;
    if (user != null) {
      user['driver_responder'] = settings.driverResponderEnabled;
      user['vehicle_type'] = settings.vehicleType;
      user['call_sign'] = settings.callSign;
      user['plate_number'] = settings.plateNumber;
      user['monthly_dispatch_count'] = settings.monthlyDispatchCount;
    }
    AuthController.I.updateResponderProfileImage(imageUrl);
    if (mounted) {
      setState(() {
        _profileSettings = settings;
        _profileImageUrl = imageUrl;
        _profileError = null;
      });
    }
  }

  Future<void> _handleDriverResponderToggle(bool enabled) async {
    if (enabled) {
      await _showVehicleEditor(driverResponderEnabled: true);
      return;
    }
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) {
      _showProfileMessage('Sign in again to update Driver Responder.');
      return;
    }
    setState(() => _driverSaving = true);
    try {
      final settings = await MobileApiClient.I.saveResponderVehicle(
        token: token,
        enabled: false,
      );
      _acceptProfileSettings(settings);
    } catch (error) {
      _showProfileMessage(
        error is MobileApiException
            ? error.message
            : 'Driver Responder could not be updated.',
      );
    } finally {
      if (mounted) setState(() => _driverSaving = false);
    }
  }

  Future<void> _showVehicleEditor({
    required bool driverResponderEnabled,
  }) async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) return;

    String initialVehicleType = 'Ambulance';
    try {
      initialVehicleType = MobileApiClient.normalizeResponderVehicleType(
        _profileSettings?.vehicleType,
      );
    } on ArgumentError {
      initialVehicleType = 'Ambulance';
    } on FormatException {
      initialVehicleType = 'Ambulance';
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => _VehicleEditorSheet(
        token: token,
        driverResponderEnabled: driverResponderEnabled,
        initialVehicleType: initialVehicleType,
        initialCallSign: _profileSettings?.callSign ?? '',
        initialPlateNumber: _profileSettings?.plateNumber ?? '',
        onSaved: (settings) {
          _acceptProfileSettings(settings);
        },
      ),
    );
  }

  Future<void> _showScheduleEditor() async {
    final token = AuthController.I.user?['token'] as String?;
    if (token == null) return;
    final periods = List<ResponderDutyPeriod>.from(
      _profileSettings?.schedules ?? const <ResponderDutyPeriod>[],
    );
    final profileSettings = _profileSettings;
    var availabilityEnabled = profileSettings == null ||
        !profileSettings.scheduleConfigured ||
        profileSettings.availabilityEnabled;
    var saving = false;
    String? errorMessage;
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheetState) {
          Future<void> pickTime(int index, bool isStart) async {
            final period = periods[index];
            final selected = await showTimePicker(
              context: sheetContext,
              initialTime: _timeFromString(
                isStart ? period.startTime : period.endTime,
              ),
            );
            if (selected == null) return;
            final value = _timeToString(selected);
            setSheetState(() {
              periods[index] = ResponderDutyPeriod(
                dayOfWeek: period.dayOfWeek,
                startTime: isStart ? value : period.startTime,
                endTime: isStart ? period.endTime : value,
              );
            });
          }

          return Padding(
            padding: EdgeInsets.only(
              left: 20,
              right: 20,
              top: 16,
              bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 16,
            ),
            child: SizedBox(
              height: MediaQuery.sizeOf(sheetContext).height * 0.78,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Duty Schedule',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: saving
                            ? null
                            : () => Navigator.of(sheetContext).pop(),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Available during this schedule'),
                    value: availabilityEnabled,
                    onChanged: saving
                        ? null
                        : (value) => setSheetState(
                              () => availabilityEnabled = value,
                            ),
                  ),
                  const Divider(height: 1),
                  const SizedBox(height: 8),
                  Expanded(
                    child: ListView.separated(
                      itemCount: periods.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final period = periods[index];
                        return Padding(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: DropdownButton<int>(
                                      value: period.dayOfWeek,
                                      isExpanded: true,
                                      items: List.generate(
                                        days.length,
                                        (dayIndex) => DropdownMenuItem(
                                          value: dayIndex + 1,
                                          child: Text(days[dayIndex]),
                                        ),
                                      ),
                                      onChanged: saving
                                          ? null
                                          : (value) {
                                              if (value == null) return;
                                              setSheetState(() {
                                                periods[index] =
                                                    ResponderDutyPeriod(
                                                  dayOfWeek: value,
                                                  startTime: period.startTime,
                                                  endTime: period.endTime,
                                                );
                                              });
                                            },
                                    ),
                                  ),
                                  IconButton(
                                    tooltip: 'Remove period',
                                    onPressed: saving
                                        ? null
                                        : () => setSheetState(
                                              () => periods.removeAt(index),
                                            ),
                                    icon: const Icon(Icons.delete_outline),
                                  ),
                                ],
                              ),
                              Wrap(
                                spacing: 8,
                                children: [
                                  OutlinedButton.icon(
                                    onPressed: saving
                                        ? null
                                        : () => pickTime(index, true),
                                    icon: const Icon(Icons.access_time),
                                    label: Text(
                                      'Start ${_formatTime(period.startTime)}',
                                    ),
                                  ),
                                  OutlinedButton.icon(
                                    onPressed: saving
                                        ? null
                                        : () => pickTime(index, false),
                                    icon: const Icon(Icons.access_time),
                                    label: Text(
                                      'End ${_formatTime(period.endTime)}',
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                  TextButton.icon(
                    onPressed: saving
                        ? null
                        : () => setSheetState(() {
                              periods.add(const ResponderDutyPeriod(
                                dayOfWeek: 1,
                                startTime: '07:00:00',
                                endTime: '19:00:00',
                              ));
                              errorMessage = null;
                            }),
                    icon: const Icon(Icons.add),
                    label: const Text('Add period'),
                  ),
                  if (errorMessage != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(
                        errorMessage!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: saving
                              ? null
                              : () => Navigator.of(sheetContext).pop(),
                          child: const Text('Cancel'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton(
                          onPressed: saving
                              ? null
                              : () async {
                                  if (periods.isEmpty && availabilityEnabled) {
                                    setSheetState(() => errorMessage =
                                        'Add at least one duty period.');
                                    return;
                                  }
                                  setSheetState(() {
                                    saving = true;
                                    errorMessage = null;
                                  });
                                  try {
                                    final settings = await MobileApiClient.I
                                        .saveResponderSchedule(
                                      token: token,
                                      availabilityEnabled: availabilityEnabled,
                                      schedules: periods,
                                    );
                                    _acceptProfileSettings(settings);
                                    if (sheetContext.mounted) {
                                      Navigator.of(sheetContext).pop();
                                    }
                                  } catch (error) {
                                    setSheetState(() {
                                      saving = false;
                                      errorMessage = error is MobileApiException
                                          ? error.message
                                          : 'The schedule could not be saved.';
                                    });
                                  }
                                },
                          child: saving
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Text('Save'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  TimeOfDay _timeFromString(String value) {
    final parts = value.split(':');
    return TimeOfDay(
      hour: int.parse(parts[0]),
      minute: int.parse(parts[1]),
    );
  }

  String _timeToString(TimeOfDay value) =>
      '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}:00';

  String _formatTime(String value) => _timeFromString(value).format(context);

  @override
  Widget build(BuildContext context) {
    final user = AuthController.I.user;
    final profile = DashboardController.I.profileFor('Responder');
    final settings = _profileSettings;
    final fullName = settings?.fullName ??
        (user?['full_name'] ?? user?['name'] ?? 'Responder').toString();
    final hasVehicleDetails =
        settings?.vehicleType?.trim().isNotEmpty == true &&
            settings?.callSign?.trim().isNotEmpty == true &&
            settings?.plateNumber?.trim().isNotEmpty == true;
    final dispatchCount = _profileLoading
        ? '—'
        : settings?.monthlyDispatchCount.toString() ?? '—';
    final isOnDuty = settings?.isOnDuty ?? false;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 6, 20, 110),
      children: [
        Align(
          alignment: Alignment.centerLeft,
          child: IconButton(
            visualDensity: VisualDensity.compact,
            padding: EdgeInsets.zero,
            onPressed: widget.onBack,
            icon: const Icon(Icons.arrow_back_ios_new, size: 18),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Profile',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '$fullName · Responder',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 10,
                      color: Colors.black.withValues(alpha: 0.6),
                    ),
                  ),
                ],
              ),
            ),
            PopupMenuButton<String>(
              tooltip: 'Profile image options',
              onSelected: _profileImageSaving
                  ? null
                  : (value) async {
                      if (value == 'choose') {
                        await _pickProfileImage();
                      } else if (value == 'remove') {
                        await _resetProfileImage();
                      }
                    },
              itemBuilder: (context) => [
                const PopupMenuItem(
                  value: 'choose',
                  child: Text('Choose photo'),
                ),
                if (_profileImageUrl != null)
                  const PopupMenuItem(
                    value: 'remove',
                    child: Text('Remove photo'),
                  ),
              ],
              child: ResponderAvatar(
                initials: profile.initial,
                radius: 22,
                backgroundColor: kResponderBrand,
              ),
            ),
          ],
        ),
        if (_profileError != null) ...[
          const SizedBox(height: 8),
          Text(
            _profileError!,
            style: const TextStyle(fontSize: 12, color: Colors.red),
          ),
        ],
        const SizedBox(height: 20),
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: const Color(0xFFE2E2E2)),
          ),
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'This month',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
              Text(
                dispatchCount,
                style: const TextStyle(
                  fontSize: 18,
                  height: 1.15,
                  fontWeight: FontWeight.w800,
                  color: Colors.black,
                ),
              ),
              const SizedBox(height: 2),
              const Text(
                'Dispatches handled',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Vehicle',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        InkWell(
          onTap: _profileLoading
              ? null
              : () => _showVehicleEditor(
                    driverResponderEnabled:
                        settings?.driverResponderEnabled ?? false,
                  ),
          borderRadius: BorderRadius.circular(6),
          child: Container(
            width: double.infinity,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: const Color(0xFFE2E2E2)),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: hasVehicleDetails
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${settings!.vehicleType} — ${settings.callSign}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Plate ${settings.plateNumber}',
                        style: const TextStyle(
                          fontSize: 10,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  )
                : Text(
                    settings?.driverResponderEnabled == true
                        ? 'Vehicle information unavailable'
                        : 'No vehicle registered',
                    style: const TextStyle(fontSize: 11, color: Colors.black54),
                  ),
          ),
        ),
        SwitchListTile.adaptive(
          contentPadding: const EdgeInsets.symmetric(horizontal: 2),
          title: const Text(
            'Driver Responder',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          ),
          value: settings?.driverResponderEnabled ?? false,
          onChanged: _driverSaving ? null : _handleDriverResponderToggle,
        ),
        const SizedBox(height: 14),
        const Text(
          'Settings',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: const Color(0xFFE2E2E2)),
          ),
          child: Column(
            children: [
              InkWell(
                onTap: _showScheduleEditor,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    vertical: 10,
                    horizontal: 14,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Duty Schedule',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _scheduleSummary(settings),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 9,
                                color: Colors.black54,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Icon(
                        Icons.circle,
                        size: 7,
                        color: isOnDuty ? Colors.green : Colors.black38,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        isOnDuty ? 'On Duty' : 'Off Duty',
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Divider(height: 1, thickness: 1),
              InkWell(
                onTap: widget.onLogout,
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 11, horizontal: 14),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Log out',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: kSos,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  String _scheduleSummary(ResponderProfileSettings? settings) {
    if (settings == null || !settings.scheduleConfigured) {
      return 'Schedule not set';
    }
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    final selectedDays = settings.schedules
        .map((period) => period.dayOfWeek)
        .toSet()
        .toList()
      ..sort();
    if (selectedDays.isEmpty) return 'No available periods';
    final daySummary = selectedDays.map((day) => days[day - 1]).join(', ');
    final ranges = settings.schedules
        .map((period) =>
            '${_formatTime(period.startTime)}–${_formatTime(period.endTime)}')
        .toSet();
    final rangeSummary = ranges.length == 1
        ? ranges.single
        : '${settings.schedules.length} time periods';
    return '$daySummary · $rangeSummary';
  }
}

class ResidentStatusHome extends StatelessWidget {
  const ResidentStatusHome({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = SosController.I;

    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        return AnimatedSwitcher(
          duration: kMedium,
          layoutBuilder: expandLayout,
          child: controller.details != null &&
                  controller.stage != EmergencyStage.idle
              ? _ActiveStatus(
                  key: const ValueKey('active'),
                  incident: controller.details!,
                  live: controller.reportError == null,
                )
              : const _EmptyStatus(key: ValueKey('empty')),
        );
      },
    );
  }
}

class ResidentLiveStatusScreen extends StatelessWidget {
  const ResidentLiveStatusScreen({super.key});

  @override
  Widget build(BuildContext context) => const Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(child: ResidentStatusHome()),
      );
}

class _EmptyStatus extends StatelessWidget {
  const _EmptyStatus({super.key});

  @override
  Widget build(BuildContext context) => const Center(
        child: Padding(
          padding: EdgeInsets.all(28),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.history, size: 54, color: Colors.black26),
              SizedBox(height: 14),
              Text(
                'No active emergency',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              SizedBox(height: 6),
              Text(
                'Your active emergency status will appear here.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 11, color: Colors.black54),
              ),
            ],
          ),
        ),
      );
}

class _ActiveStatus extends StatelessWidget {
  final IncidentDetails incident;
  final bool live;
  const _ActiveStatus({
    super.key,
    required this.incident,
    required this.live,
  });

  static const _card = BoxDecoration(
    border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
    borderRadius: BorderRadius.all(Radius.circular(8)),
  );

  int get _currentStep {
    if (incident.hospitalArrivedAt != null) return 6;
    if (incident.hospitalSentAt != null || incident.sentToHospital) return 4;
    if (incident.patientPickedUpAt != null) return 3;
    if (incident.responderArrivedAt != null) return 2;
    if (incident.responderAssignedAt != null ||
        incident.responderName != null) {
      return 1;
    }
    return 0;
  }

  String get _headline {
    if (incident.sentToHospital || incident.patientPickedUpAt != null) {
      return 'Help is confirmed';
    }
    if (incident.responderArrivedAt != null) {
      return 'A responder is checking on you';
    }
    if (incident.responderName != null) {
      return 'A responder is coming to check on you';
    }
    return 'Alert sent · waiting for responder';
  }

  String _time(DateTime? time) {
    if (time == null) return '';
    final localTime = time.toLocal();
    return '${localTime.hour.toString().padLeft(2, '0')}:'
        '${localTime.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final incidentPoint =
        incident.latitude != null && incident.longitude != null
            ? LatLng(incident.latitude!, incident.longitude!)
            : null;
    final responderPoint = incident.responderLatitude != null &&
            incident.responderLongitude != null
        ? LatLng(incident.responderLatitude!, incident.responderLongitude!)
        : null;
    final hospitalPoint =
        incident.hospitalLatitude != null && incident.hospitalLongitude != null
            ? LatLng(incident.hospitalLatitude!, incident.hospitalLongitude!)
            : null;
    final eventTimes = [
      incident.reportedAt ?? SosController.I.sentAt,
      incident.responderAcknowledgedAt ?? incident.responderAssignedAt,
      incident.responderArrivedAt,
      incident.patientPickedUpAt,
      incident.hospitalSentAt,
      incident.hospitalArrivedAt,
    ];
    const eventLabels = [
      'Report sent',
      'Responder on the way',
      'Responder checking on you',
      'Help confirmed',
      'On the way to the hospital',
      'Arrived',
    ];
    final currentStep = _currentStep;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Status',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: live ? const Color(0xFFE3F2E1) : const Color(0xFFFFF3E0),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.circle,
                    color: live
                        ? const Color(0xFF55A65B)
                        : const Color(0xFFE58A24),
                    size: 8,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    live ? 'LIVE' : 'UPDATE DELAYED',
                    style: TextStyle(
                      color: live
                          ? const Color(0xFF245B2C)
                          : const Color(0xFF8A4B00),
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          'Report #${incident.incidentId} · '
          '${incident.barangay ?? incident.location}',
          style: const TextStyle(fontSize: 10, color: Colors.black54),
        ),
        const SizedBox(height: 14),
        DecoratedBox(
          decoration: BoxDecoration(
            color: const Color(0xFFADB5BA),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _headline,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        incident.sentToHospital
                            ? 'estimated arrival at the hospital'
                            : 'estimated arrival at the emergency location',
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 9,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  incident.etaMinutes == null
                      ? '—'
                      : '${incident.etaMinutes} min',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        SizedBox(
          height: 150,
          child: OpenStreetMapView(
            key: ValueKey('resident-status-map-${incident.incidentId}'),
            radius: 10,
            center:
                responderPoint ?? incidentPoint ?? OpenStreetMapApi.townCenter,
            onTap: () => Nav.push(context, const TrackingScreen()),
            markers: [
              if (responderPoint != null)
                OpenStreetMapMarker(
                  point: responderPoint,
                  label: incident.responderName ?? 'Responder',
                  color: kResponderBrand,
                  icon: Icons.support_agent,
                ),
              if (incidentPoint != null)
                OpenStreetMapMarker(
                  point: incidentPoint,
                  label: 'Emergency location',
                  color: kSos,
                  icon: Icons.emergency,
                ),
              if (hospitalPoint != null)
                OpenStreetMapMarker(
                  point: hospitalPoint,
                  label: incident.hospital ?? 'Hospital',
                  color: Colors.blueGrey,
                  icon: Icons.local_hospital,
                ),
            ],
          ),
        ),
        DecoratedBox(
          key: const ValueKey('resident-live-status-timeline'),
          decoration: _card,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 12, 10),
            child: Column(
              children: [
                for (var index = 0; index < eventLabels.length; index++)
                  SizedBox(
                    height: 28,
                    child: Row(
                      children: [
                        SizedBox(
                          width: 24,
                          child: Column(
                            children: [
                              Expanded(
                                child: Container(
                                  width: 2,
                                  color: index > 0 && index <= currentStep
                                      ? const Color(0xFF7DBB75)
                                      : index == 0
                                          ? Colors.transparent
                                          : Colors.black12,
                                ),
                              ),
                              Container(
                                width: 16,
                                height: 16,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: index < currentStep
                                      ? const Color(0xFF79B96F)
                                      : index == currentStep
                                          ? kSos
                                          : const Color(0xFFD9D9D9),
                                ),
                                child: index < currentStep
                                    ? const Icon(
                                        Icons.check,
                                        size: 11,
                                        color: Colors.white,
                                      )
                                    : null,
                              ),
                              Expanded(
                                child: Container(
                                  width: 2,
                                  color: index < currentStep
                                      ? const Color(0xFF7DBB75)
                                      : index == eventLabels.length - 1
                                          ? Colors.transparent
                                          : Colors.black12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            eventLabels[index],
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: index == currentStep
                                  ? FontWeight.w700
                                  : FontWeight.normal,
                              color: index <= currentStep
                                  ? Colors.black
                                  : Colors.black54,
                            ),
                          ),
                        ),
                        if (_time(eventTimes[index]).isNotEmpty)
                          Text(
                            _time(eventTimes[index]),
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
          ),
        ),
        const SizedBox(height: 10),
        Btn(
          'VIEW LIVE STATUS',
          kResidentBrand,
          fontSize: 14,
          onTap: () => Nav.push(context, const TrackingScreen()),
        ),
      ],
    );
  }
}

class ResidentProfileHome extends StatelessWidget {
  final Color roleColor;

  const ResidentProfileHome({
    super.key,
    required this.roleColor,
  });

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        const Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profile',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  SizedBox(height: 3),
                  Text(
                    'Sean Occena · Brgy. San Isidro',
                    style: TextStyle(fontSize: 10),
                  ),
                ],
              ),
            ),
            CircleAvatar(
              radius: 14,
              backgroundColor: _residentColor,
              child: Text(
                'S',
                style: TextStyle(color: Colors.white, fontSize: 11),
              ),
            ),
          ],
        ),
        const SizedBox(height: 22),
        const Text(
          'Household members',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _profileCard('Sean Occena', '24 · Main household member', roleColor),
        _profileCard('Aries Occena', '43 · Household member', roleColor),
        const SizedBox(height: 6),
        Pressable(
          child: OutlinedButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.add_circle_outline, size: 17),
            label: const Text('Add household member'),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Emergency contacts',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _profileCard(
          'Brgy. San Isidro Health Center',
          'Barangay hotline',
          roleColor,
          icon: Icons.local_hospital,
        ),
        _profileCard(
          'Juan Ramos',
          'Next of kin',
          roleColor,
          icon: Icons.phone,
        ),
        const SizedBox(height: 18),
        const Text(
          'Settings',
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        _setting('Notifications', 'On'),
        _setting('Language', 'Filipino'),
        _setting('Log out', '', danger: true, onTap: () {
          AuthController.I.logout();
          Nav.resetTo(context, const PortalScreen());
        }),
      ],
    );
  }

  Widget _profileCard(
    String name,
    String detail,
    Color color, {
    IconData icon = Icons.person,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.all(10),
      decoration: const BoxDecoration(
        border: Border.fromBorderSide(BorderSide(color: Colors.black12)),
        borderRadius: BorderRadius.all(Radius.circular(8)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 14,
            backgroundColor: color,
            child: Icon(icon, size: 15, color: Colors.white),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  detail,
                  style: const TextStyle(fontSize: 8, color: Colors.black54),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _setting(
    String title,
    String value, {
    bool danger = false,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: Colors.black12)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 10,
                  color: danger ? kSos : Colors.black,
                  fontWeight: danger ? FontWeight.bold : FontWeight.normal,
                ),
              ),
            ),
            if (value.isNotEmpty)
              Text(
                value,
                style: const TextStyle(fontSize: 10, color: Colors.black54),
              ),
          ],
        ),
      ),
    );
  }
}
