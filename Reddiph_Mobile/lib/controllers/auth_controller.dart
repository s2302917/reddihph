import 'package:flutter/foundation.dart';

import '../models/role.dart';
import '../services/mobile_api_client.dart';

class AuthController extends ChangeNotifier {
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
    notifyListeners();
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

  void updateResponderProfileImage(String? imageUrl) {
    final currentUser = user;
    if (currentUser == null) return;
    currentUser['profile_image_url'] = imageUrl;
    notifyListeners();
  }

  void logout() {
    role = null;
    user = null;
    notifyListeners();
  }
}
