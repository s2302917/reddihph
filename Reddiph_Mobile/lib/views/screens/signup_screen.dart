import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../models/role.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';

enum _Kind {
  text,
  phone,
  email,
  password,
  signupCode,
  hospital,
  barangay,
  age,
  gender
}

class _Field {
  final String label;
  final _Kind kind;
  final bool optional;
  const _Field(this.label, [this.kind = _Kind.text, this.optional = false]);
}

class _Config {
  final String title, sub, button, done;
  final List<_Field> fields;
  const _Config({
    required this.title,
    required this.sub,
    required this.button,
    required this.done,
    required this.fields,
  });
}

// Every role picks its hospital and barangay from the database.
const _place = [
  _Field('Hospital', _Kind.hospital),
  _Field('Barangay', _Kind.barangay),
];

const _pwConfirm = [
  _Field('Password', _Kind.password),
  _Field('Confirm password', _Kind.password),
];

/// Per-role sign-up forms, keyed by [Role.name]. Edit fields / copy here.
final Map<String, _Config> _configs = {
  'Resident': const _Config(
    title: 'Create resident account',
    sub: 'Self-registration for residents',
    button: 'Sign Up',
    done: 'Resident account created. You can now log in with your email.',
    fields: [
      _Field('Full name'),
      _Field('Email', _Kind.email),
      _Field('Mobile number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      ..._pwConfirm,
    ],
  ),
  'Responder': const _Config(
    title: 'Create barangay responder account',
    sub: 'Select your hospital and barangay',
    button: 'Sign Up',
    done:
        'Responder signup sent. Your Barangay Admin must approve the account before you can log in.',
    fields: [
      _Field('Full name'),
      _Field('Email', _Kind.email),
      _Field('Mobile number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      ..._pwConfirm,
    ],
  ),
  'Doctor': const _Config(
    title: 'Create doctor account',
    sub: 'Select your hospital',
    button: 'Sign Up',
    done:
        'Doctor account created. You can now log in with your hospital email.',
    fields: [
      _Field('Full name'),
      _Field('PRC license number'),
      _Field('Hospital email', _Kind.email),
      _Field('Contact number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      ..._pwConfirm,
    ],
  ),
  'Nurse': const _Config(
    title: 'Create nurse account',
    sub: 'Select your hospital',
    button: 'Sign Up',
    done: 'Nurse account created. You can now log in with your hospital email.',
    fields: [
      _Field('Full name'),
      _Field('Hospital email', _Kind.email),
      _Field('Contact number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      ..._pwConfirm,
    ],
  ),
  'Volunteer': const _Config(
    title: 'Create volunteer account',
    sub: 'Self-registration for community volunteers',
    button: 'Sign Up',
    done:
        'Volunteer signup sent. Your Barangay Admin must approve the account before you can log in.',
    fields: [
      _Field('Full name'),
      _Field('Email', _Kind.email),
      _Field('Mobile number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      _Field('Vehicle (optional)', _Kind.text, true),
      ..._pwConfirm,
    ],
  ),
  'Barangay Admin': const _Config(
    title: 'Create Barangay Admin account',
    sub: 'Enter your authorized signup code and select your assigned barangay',
    button: 'Sign Up',
    done: 'Barangay Admin account created. You can now log in with your email.',
    fields: [
      _Field('Signup code', _Kind.signupCode),
      _Field('Full name'),
      _Field('Email', _Kind.email),
      _Field('Mobile number', _Kind.phone),
      _Field('Age', _Kind.age),
      _Field('Gender', _Kind.gender),
      ..._place,
      _Field('Vehicle (optional)', _Kind.text, true),
      ..._pwConfirm,
    ],
  ),
};

class SignUpScreen extends StatefulWidget {
  final Role role;
  final MobileApiClient? apiClient;
  const SignUpScreen({super.key, required this.role, this.apiClient});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  final _form = GlobalKey<FormState>();
  final _ctrl = <String, TextEditingController>{};
  bool _agree = false;
  bool _agreeError = false;
  List<MobileHospital> _hospitals = [];
  List<String> _genders = [];
  String? _selectedGender;
  int? _selectedHospitalId;
  int? _selectedBarangayId;
  bool _loadingOptions = false;
  bool _submitting = false;
  String? _optionsError;
  final _showPassword = <String, bool>{};

  Role get role => widget.role;
  MobileApiClient get _apiClient => widget.apiClient ?? MobileApiClient.I;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loadingOptions = true;
      _optionsError = null;
    });
    try {
      final options = await _apiClient.signupOptions();
      final noLocations =
          options.hospitals.every((hospital) => hospital.barangays.isEmpty);
      if (noLocations || options.genders.isEmpty) {
        throw const MobileApiException(
          'The server has no signup options configured.',
        );
      }
      if (!mounted) return;
      setState(() {
        _hospitals = options.hospitals;
        _genders = options.genders;
        _loadingOptions = false;
      });
    } on MobileApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _optionsError = error.message;
        _loadingOptions = false;
      });
    }
  }

  _Config get cfg =>
      _configs[role.name] ??
      _Config(
        title: 'Create ${role.name.toLowerCase()} account',
        sub: '',
        button: 'Sign Up',
        done: 'Account request sent.',
        fields: const [_Field('Full name'), ..._pwConfirm],
      );

  // Email and phone labels differ per role (e.g. 'Hospital email'), so look them up by kind.
  String _labelOf(_Kind kind) =>
      cfg.fields.firstWhere((f) => f.kind == kind).label;

  TextEditingController _c(String label) =>
      _ctrl.putIfAbsent(label, () => TextEditingController());

  @override
  void dispose() {
    for (final c in _ctrl.values) {
      c.dispose();
    }
    super.dispose();
  }

  String? _validate(_Field f, String? v) {
    final value = f.kind == _Kind.password ? (v ?? '') : (v ?? '').trim();
    if (f.optional) return null;
    if (value.isEmpty) return 'Required';
    if (f.kind == _Kind.email && !value.contains('@')) {
      return 'Enter a valid email';
    }
    if (f.kind == _Kind.phone &&
        value.replaceAll(RegExp(r'\D'), '').length < 10) {
      return 'Enter a valid number';
    }
    if (f.kind == _Kind.age) {
      final age = int.tryParse(value);
      if (age == null || age < 1 || age > 120) return 'Enter a valid age';
    }
    if (f.kind == _Kind.password) {
      if (value.length < 8 || value.length > 72) {
        return 'Use 8 to 72 characters';
      }
      if (!RegExp(r'[A-Z]').hasMatch(value)) {
        return 'Include at least one uppercase letter';
      }
      if (!RegExp(r'[a-z]').hasMatch(value)) {
        return 'Include at least one lowercase letter';
      }
      if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
        return 'Include at least one special character';
      }
    }
    if (f.kind == _Kind.signupCode && value.length < 8) {
      return 'Enter the signup code provided by your system administrator';
    }
    if (f.label == 'Confirm password' && value != _c('Password').text) {
      return 'Passwords do not match';
    }
    return null;
  }

  Future<void> _submit() async {
    final ok = _form.currentState!.validate();
    setState(() => _agreeError = !_agree);
    if (!ok || !_agree) return;

    final hospitalId = _selectedHospitalId;
    final barangayId = _selectedBarangayId;
    final gender = _selectedGender;
    if (hospitalId == null || barangayId == null || gender == null) return;

    setState(() => _submitting = true);
    try {
      await _apiClient.createAccount(
        role: role.apiRole,
        fullName: _c('Full name').text,
        workEmail: _c(_labelOf(_Kind.email)).text,
        phoneNumber: _c(_labelOf(_Kind.phone)).text,
        age: int.parse(_c('Age').text.trim()),
        gender: gender,
        hospitalId: hospitalId,
        barangayId: barangayId,
        password: _c('Password').text,
        confirmPassword: _c('Confirm password').text,
        signupCode:
            role.name == 'Barangay Admin' ? _c('Signup code').text : null,
        licenseNumber:
            role.name == 'Doctor' ? _c('PRC license number').text : null,
        vehicleType:
            role.name == 'Volunteer' ? _c('Vehicle (optional)').text : null,
      );
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pop(); // back to the login screen
      messenger.showSnackBar(
        SnackBar(content: Text(cfg.done), duration: const Duration(seconds: 6)),
      );
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  InputDecoration _decoration(String hint) => InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(fontSize: 12),
        errorStyle: const TextStyle(fontSize: 10),
        filled: true,
        fillColor: kGrey,
        isDense: true,
        border: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(6)),
            borderSide: BorderSide.none),
      );

  Widget _input(_Field f, bool last) {
    switch (f.kind) {
      case _Kind.gender:
        return DropdownButtonFormField<String>(
          initialValue: _selectedGender,
          isExpanded: true,
          decoration: _decoration('Select gender'),
          style: const TextStyle(fontSize: 12, color: Colors.black87),
          items: [
            for (final gender in _genders)
              DropdownMenuItem(
                value: gender,
                child: Text(gender[0].toUpperCase() + gender.substring(1)),
              ),
          ],
          onChanged: _submitting
              ? null
              : (value) => setState(() => _selectedGender = value),
          validator: (value) => value == null ? 'Required' : null,
        );
      case _Kind.hospital:
        return DropdownButtonFormField<int>(
          initialValue: _selectedHospitalId,
          isExpanded: true,
          decoration: _decoration('Select hospital'),
          style: const TextStyle(fontSize: 12, color: Colors.black87),
          items: [
            for (final hospital in _hospitals)
              DropdownMenuItem(
                value: hospital.id,
                child: Text(hospital.name, overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: _submitting
              ? null
              : (value) => setState(() {
                    _selectedHospitalId = value;
                    _selectedBarangayId = null;
                  }),
          validator: (value) => value == null ? 'Required' : null,
        );
      case _Kind.barangay:
        final hospital = _hospitals.where(
          (item) => item.id == _selectedHospitalId,
        );
        final barangays =
            hospital.isEmpty ? <MobileBarangay>[] : hospital.first.barangays;
        return DropdownButtonFormField<int>(
          key: ValueKey<int?>(_selectedHospitalId),
          initialValue: _selectedBarangayId,
          isExpanded: true,
          decoration: _decoration('Select barangay'),
          style: const TextStyle(fontSize: 12, color: Colors.black87),
          items: [
            for (final barangay in barangays)
              DropdownMenuItem(
                value: barangay.id,
                child: Text(barangay.name),
              ),
          ],
          onChanged: _selectedHospitalId == null || _submitting
              ? null
              : (value) => setState(() => _selectedBarangayId = value),
          validator: (value) => value == null ? 'Required' : null,
        );
      default:
        return TextFormField(
          controller: _c(f.label),
          obscureText: f.kind == _Kind.signupCode ||
              (f.kind == _Kind.password && !(_showPassword[f.label] ?? false)),
          keyboardType: switch (f.kind) {
            _Kind.phone => TextInputType.phone,
            _Kind.age => TextInputType.number,
            _Kind.email => TextInputType.emailAddress,
            _ => TextInputType.text,
          },
          textInputAction: last ? TextInputAction.done : TextInputAction.next,
          decoration: _decoration(switch (f.kind) {
            _Kind.phone => '09XXXXXXXXX',
            _Kind.password => 'Enter ${f.label.toLowerCase()}',
            _ => '',
          })
              .copyWith(
            helperText: f.kind == _Kind.password && f.label == 'Password'
                ? '8–72 characters; include uppercase, lowercase, and a special character.'
                : null,
            suffixIcon: f.kind == _Kind.password
                ? IconButton(
                    tooltip: (_showPassword[f.label] ?? false)
                        ? 'Hide password'
                        : 'Show password',
                    onPressed: () => setState(() {
                      _showPassword[f.label] =
                          !(_showPassword[f.label] ?? false);
                    }),
                    icon: Icon(
                      (_showPassword[f.label] ?? false)
                          ? Icons.visibility_off
                          : Icons.visibility,
                      size: 18,
                    ),
                  )
                : null,
          ),
          validator: (v) => _validate(f, v),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = cfg;
    final tint = Color.alphaBlend(role.color.withAlpha(70), Colors.white);
    return Scaffold(
      appBar: AppBar(),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 32),
        child: Form(
          key: _form,
          child: Column(children: [
            const SizedBox(height: 10),
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                  color: tint, borderRadius: BorderRadius.circular(14)),
              child: Icon(role.icon, color: role.color, size: 26),
            ),
            const SizedBox(height: 12),
            Text(c.title,
                textAlign: TextAlign.center,
                style:
                    const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            if (c.sub.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(c.sub,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 11, color: Colors.black54)),
            ],
            if (_optionsError != null) ...[
              const SizedBox(height: 12),
              Text(
                _optionsError!,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 11, color: Colors.red),
              ),
              TextButton(
                onPressed: _loadingOptions ? null : _loadOptions,
                child: const Text('Retry loading locations'),
              ),
            ],
            if (_genders.isEmpty && _loadingOptions) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
            ],
            const SizedBox(height: 16),
            for (var i = 0; i < c.fields.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(c.fields[i].label,
                          style: const TextStyle(fontSize: 11)),
                      const SizedBox(height: 4),
                      _input(c.fields[i], i == c.fields.length - 1),
                    ]),
              ),
            Row(children: [
              SizedBox(
                width: 24,
                height: 24,
                child: Checkbox(
                  value: _agree,
                  visualDensity: VisualDensity.compact,
                  side: BorderSide(
                      color: _agreeError ? Colors.red : Colors.black54,
                      width: 1.5),
                  onChanged: (v) => setState(() {
                    _agree = v ?? false;
                    _agreeError = false;
                  }),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                    'I agree to the Terms of Use and Data Privacy Notice',
                    style: TextStyle(
                        fontSize: 10,
                        color: _agreeError ? Colors.red : Colors.black87)),
              ),
            ]),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: RoleColorButton(
                color: role.color,
                onPressed: _submitting ||
                        _loadingOptions ||
                        _genders.isEmpty ||
                        _hospitals.isEmpty
                    ? null
                    : _submit,
                child: _submitting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(c.button),
              ),
            ),
            const SizedBox(height: 12),
            GestureDetector(
              onTap: () => Navigator.of(context).pop(),
              child: const Text(
                'Already have an account? Log in',
                style: TextStyle(fontSize: 10, color: Colors.black54),
              ),
            ),
            const SizedBox(height: 24),
          ]),
        ),
      ),
    );
  }
}
