import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../theme.dart';

/// Спільний повноекранний блокер для двох станів з /api/app-config:
/// режим обслуговування (mobile_app_enabled=false) і примусове оновлення
/// (mobile_app_min_build > поточної збірки). Немає кнопки "продовжити" —
/// це навмисне, обидва стани контролює лише адмін через сайт.
class BlockingScreen extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? downloadUrl;
  final VoidCallback onRetry;

  const BlockingScreen({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    required this.onRetry,
    this.downloadUrl,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, color: AppColors.gold300, size: 56),
                const SizedBox(height: 20),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 10),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white54, fontSize: 14),
                ),
                const SizedBox(height: 24),
                if (downloadUrl != null)
                  ElevatedButton(
                    onPressed: () => launchUrl(Uri.parse(downloadUrl!),
                        mode: LaunchMode.externalApplication),
                    child: const Text('ЗАВАНТАЖИТИ ОНОВЛЕННЯ'),
                  ),
                TextButton(
                    onPressed: onRetry, child: const Text('Перевірити ще раз')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
