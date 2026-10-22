import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../models/role.dart';
import '../../services/mobile_api_client.dart';

/// Card that asks for the account email and requests a temporary password.
Future<void> showForgotPasswordDialog(BuildContext context, Role role) =>
    showDialog<void>(
      context: context,
      builder: (_) => _ForgotPasswordDialog(role: role),
    );

/// Non-dismissible card that forces a new password before the dashboard opens.
/// Completes with true once the password was changed.
Future<bool> showChangePasswordDialog(BuildContext context) async {
  final changed = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => const _ChangePasswordDialog(),
  );
  return changed ?? false;
}

/// Shared submit handling: shows a spinner flag and reports API errors inline.
mixin _SubmitState<T extends StatefulWidget> on State<T> {
  bool busy = false;
  String? error;

  Future<void> submit(Future<void> Function() action) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action();
    } on MobileApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }
}

class _ForgotPasswordDialog extends StatefulWidget {
  final Role role;
  const _ForgotPasswordDialog({required this.role});

  @override
  State<_ForgotPasswordDialog> createState() => _ForgotPasswordDialogState();
}

class _ForgotPasswordDialogState extends State<_ForgotPasswordDialog>
    with _SubmitState {
  final _email = TextEditingController();
  String? _sent;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  void _send() {
    if (_email.text.trim().isEmpty) {
      setState(() => error = 'Enter your email address.');
      return;
    }
    submit(() async {
      final message = await AuthController.I
          .forgotPassword(widget.role, email: _email.text);
      if (mounted) setState(() => _sent = message);
    });
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Forgot password'),
        content: _sent != null
            ? Text(_sent!)
            : Column(mainAxisSize: MainAxisSize.min, children: [
                const Text(
                    'Enter your email and we will send you a temporary password.',
                    style: TextStyle(fontSize: 14)),
                const SizedBox(height: 12),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                      hintText: 'you@gmail.com',
                      hintStyle: const TextStyle(fontSize: 14),
                      errorText: error,
                      isDense: true),
                ),
              ]),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(_sent != null ? 'Close' : 'Cancel'),
          ),
          if (_sent == null)
            FilledButton(
              onPressed: busy ? null : _send,
              child: Text(busy ? 'Sending...' : 'Send'),
            ),
        ],
      );
}

class _ChangePasswordDialog extends StatefulWidget {
  const _ChangePasswordDialog();

  @override
  State<_ChangePasswordDialog> createState() => _ChangePasswordDialogState();
}

class _ChangePasswordDialogState extends State<_ChangePasswordDialog>
    with _SubmitState {
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _showPassword = false;
  bool _showConfirm = false;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  void _save() => submit(() async {
        await AuthController.I.changePassword(
          password: _password.text,
          confirmPassword: _confirm.text,
        );
        if (mounted) Navigator.pop(context, true);
      });

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: false,
        child: AlertDialog(
          title: const Text('Create a new password'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            const Text(
                'You signed in with a temporary password. Choose a new one to continue.',
                style: TextStyle(fontSize: 14)),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              obscureText: !_showPassword,
              decoration: InputDecoration(
                hintText: 'New password (8–72 characters)',
                helperText:
                    'Include uppercase, lowercase, and a special character.',
                isDense: true,
                suffixIcon: IconButton(
                  tooltip: _showPassword ? 'Hide password' : 'Show password',
                  onPressed: () =>
                      setState(() => _showPassword = !_showPassword),
                  icon: Icon(
                      _showPassword ? Icons.visibility_off : Icons.visibility),
                ),
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _confirm,
              obscureText: !_showConfirm,
              decoration: InputDecoration(
                  hintText: 'Confirm new password',
                  errorText: error,
                  isDense: true,
                  suffixIcon: IconButton(
                    tooltip: _showConfirm ? 'Hide password' : 'Show password',
                    onPressed: () =>
                        setState(() => _showConfirm = !_showConfirm),
                    icon: Icon(
                        _showConfirm ? Icons.visibility_off : Icons.visibility),
                  )),
            ),
          ]),
          actions: [
            FilledButton(
              onPressed: busy ? null : _save,
              child: Text(busy ? 'Saving...' : 'Save password'),
            ),
          ],
        ),
      );
}
