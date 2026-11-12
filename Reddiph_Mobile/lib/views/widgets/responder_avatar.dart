import 'package:flutter/material.dart';

import '../../controllers/auth_controller.dart';
import '../../core/theme.dart';
import '../../services/mobile_api_client.dart';

class ResponderAvatar extends StatelessWidget {
  const ResponderAvatar({
    super.key,
    required this.initials,
    this.radius = 18,
    this.backgroundColor = kResponderBrand,
  });

  final String initials;
  final double radius;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
        listenable: AuthController.I,
        builder: (context, _) {
          final imagePath =
              AuthController.I.user?['profile_image_url'] as String?;
          final imageUrl =
              MobileApiClient.I.resolveResponderProfileImageUrl(imagePath);
          final fallback = ColoredBox(
            color: backgroundColor,
            child: Center(
              child: Text(
                initials,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: radius * 0.67,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          );

          return SizedBox.square(
            dimension: radius * 2,
            child: ClipOval(
              child: imageUrl == null
                  ? fallback
                  : Image.network(
                      imageUrl,
                      key: ValueKey(imageUrl),
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => fallback,
                      loadingBuilder: (_, child, progress) =>
                          progress == null ? child : fallback,
                    ),
            ),
          );
        },
      );
}
