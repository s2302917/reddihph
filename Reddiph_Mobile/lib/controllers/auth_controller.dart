import '../models/role.dart';
import '../services/mobile_api_client.dart';

class AuthController {
  AuthController._();
  static final I = AuthController._();

  Role? role;
  Map<String, dynamic>? user;

  Future<void> login(
    Role r, {
    required String email,
    required String password,
  }) async {
    user = await MobileApiClient.I.login(
      role: r.apiRole,
      email: email,
      password: password,
    );
    role = r;
  }

  Future<String> forgotPassword(Role r, {required String email}) =>
      MobileApiClient.I.forgotPassword(role: r.apiRole, email: email);

  Future<void> changePassword({
    required String password,
    required String confirmPassword,
  }) =>
      MobileApiClient.I.changePassword(
        token: user!['token'] as String,
        password: password,
        confirmPassword: confirmPassword,
      );

  void logout() {
    role = null;
    user = null;
  }
}
