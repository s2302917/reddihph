import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/theme.dart';
import '../../models/role.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/password_dialogs.dart';
import 'shell.dart';
import 'signup_screen.dart';

class LoginScreen extends StatefulWidget {
  final Role role;
  const LoginScreen({super.key, required this.role});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  late final Map<String, TextEditingController> _controllers = {
    for (final field in widget.role.fields) field: TextEditingController(),
  };
  bool _isLoading = false;
  bool _showPassword = false;

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _login() async {
    final email = _controllers['Email']!.text.trim();
    final password = _controllers['Password']!.text;
    if (email.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter your email and password.')),
      );
      return;
    }

    setState(() => _isLoading = true);
    try {
      await AuthController.I.login(
        widget.role,
        email: email,
        password: password,
      );
      if (!mounted) return;
      if (AuthController.I.user?['must_change_password'] == true) {
        final changed = await showChangePasswordDialog(context);
        if (!changed) {
          AuthController.I.logout();
          return;
        }
      }
      if (mounted) Nav.resetTo(context, const Shell());
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(children: [
            const SizedBox(height: 30),
            const Logo(),
            const SizedBox(height: 20),
            Text(widget.role.loginTitle,
                textAlign: TextAlign.center,
                style:
                    const TextStyle(fontWeight: FontWeight.bold, fontSize: 20)),
            const SizedBox(height: 30),
            for (final f in widget.role.fields)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(f, style: const TextStyle(fontSize: 13)),
                      const SizedBox(height: 4),
                      TextField(
                        controller: _controllers[f],
                        obscureText: f == 'Password' && !_showPassword,
                        keyboardType: f == 'Email'
                            ? TextInputType.emailAddress
                            : TextInputType.text,
                        textInputAction: f == 'Password'
                            ? TextInputAction.done
                            : TextInputAction.next,
                        decoration: InputDecoration(
                          hintText: switch (f) {
                            'Password' => 'Enter Password',
                            'Email' => 'you@gmail.com',
                            _ => 'Enter $f',
                          },
                          hintStyle: const TextStyle(fontSize: 14),
                          filled: true,
                          fillColor: kGrey,
                          isDense: true,
                          suffixIcon: f == 'Password'
                              ? IconButton(
                                  tooltip: _showPassword
                                      ? 'Hide password'
                                      : 'Show password',
                                  onPressed: () => setState(
                                      () => _showPassword = !_showPassword),
                                  icon: Icon(
                                    _showPassword
                                        ? Icons.visibility_off
                                        : Icons.visibility,
                                    size: 19,
                                  ),
                                )
                              : null,
                          border: const OutlineInputBorder(
                              borderRadius:
                                  BorderRadius.all(Radius.circular(6)),
                              borderSide: BorderSide.none),
                        ),
                      ),
                    ]),
              ),
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerRight,
              child: GestureDetector(
                onTap: () => showForgotPasswordDialog(context, widget.role),
                child: const Text(
                  'Forgot password?',
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.black54,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 14),
            Pressable(
              child: SizedBox(
                width: double.infinity,
                height: 44,
                child: RoleColorButton(
                  color: widget.role.color,
                  onPressed: _isLoading ? null : _login,
                  child: _isLoading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Log in'),
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (widget.role.name == 'Barangay Admin')
              GestureDetector(
                onTap: () => Nav.push(context, SignUpScreen(role: widget.role)),
                child: Text(
                  'New here? Create a Barangay Admin account.',
                  style: TextStyle(
                    fontSize: 13,
                    color: widget.role.color,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              )
            else if (widget.role.name == 'Responder')
              GestureDetector(
                onTap: () => Nav.push(
                  context,
                  SignUpScreen(role: widget.role),
                ),
                child: const Text(
                  'New here? Create a Responder account.',
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.black54,
                  ),
                ),
              )
            else
              GestureDetector(
                onTap: () => Nav.push(context, SignUpScreen(role: widget.role)),
                child: Text(
                  'New here? Create a ${widget.role.name} account.',
                  style: const TextStyle(
                    fontSize: 13,
                    color: Colors.black54,
                  ),
                ),
              ),
          ]),
        ),
      );
}
