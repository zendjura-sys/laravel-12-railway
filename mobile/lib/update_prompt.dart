import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:url_launcher/url_launcher.dart';
import 'app_info.dart';
import 'theme.dart';

const _dismissedBuildKey = 'update_dismissed_build';
final _storage = const FlutterSecureStorage();

/// М'яка пропозиція оновитись — на відміну від BlockingScreen (жорсткий
/// блок за mobile_app_min_build), тут застосунок працює як завжди, просто
/// раз показує акуратне вікно. "Раз" — доки на сайті не випустять ЩЕ
/// новішу збірку: номер, на якому людина натиснула "Не зараз", лишається
/// на пристрої, і те саме число (чи старіше) вдруге вікно не відкриє.
Future<void> maybeShowUpdatePrompt(
  BuildContext context, {
  required int latestBuild,
  String? message,
  String? downloadUrl,
}) async {
  if (latestBuild <= currentBuildNumber) return;

  final dismissedRaw = await _storage.read(key: _dismissedBuildKey);
  final dismissed = int.tryParse(dismissedRaw ?? '') ?? 0;
  if (dismissed >= latestBuild) return;

  if (!context.mounted) return;

  await showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (dialogContext) => _UpdateDialog(
      latestBuild: latestBuild,
      message: message,
      downloadUrl: downloadUrl,
    ),
  );
}

class _UpdateDialog extends StatelessWidget {
  final int latestBuild;
  final String? message;
  final String? downloadUrl;

  const _UpdateDialog(
      {required this.latestBuild, this.message, this.downloadUrl});

  Future<void> _dismiss(BuildContext context) async {
    await _storage.write(key: _dismissedBuildKey, value: '$latestBuild');
    if (context.mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: glassPanelDecoration(radius: 20).copyWith(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.obsidian900,
              AppColors.obsidian800,
              AppColors.gold400.withValues(alpha: 0.05)
            ],
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border:
                    Border.all(color: AppColors.gold400.withValues(alpha: 0.5)),
                color: AppColors.gold400.withValues(alpha: 0.08),
              ),
              child: const Icon(Icons.auto_awesome,
                  color: AppColors.gold300, size: 26),
            ),
            const SizedBox(height: 18),
            const Text(
              'Доступна нова версія',
              textAlign: TextAlign.center,
              style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 10),
            Text(
              (message?.trim().isNotEmpty ?? false)
                  ? message!.trim()
                  : 'Ми покращили Monsory Connect — оновіть застосунок, щоб отримати все нове.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                  color: Colors.white54, fontSize: 14, height: 1.4),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: downloadUrl == null
                    ? null
                    : () async {
                        final navigator = Navigator.of(context);
                        await launchUrl(Uri.parse(downloadUrl!),
                            mode: LaunchMode.externalApplication);
                        await _storage.write(
                            key: _dismissedBuildKey, value: '$latestBuild');
                        navigator.pop();
                      },
                child: const Text('ОНОВИТИ ЗАРАЗ'),
              ),
            ),
            const SizedBox(height: 4),
            TextButton(
                onPressed: () => _dismiss(context),
                child: const Text('Не зараз')),
          ],
        ),
      ),
    );
  }
}
