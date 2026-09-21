import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'app_info.dart';
import 'theme.dart';

const _dismissedBuildKey = 'update_dismissed_build';
final _storage = const FlutterSecureStorage();

// Стабільні публічні посилання GitHub Release (той самий тег mobile-latest,
// що й у .github/workflows/mobile-build.yml) — не потребують токена й
// завжди свіжі одразу після успішної CI-збірки. На відміну від
// mobile_app_latest_build у Setting на сайті (яку оновлює лише РУЧНИЙ
// деплой ядра, deploy/setup-vps.sh) — мобільні збірки виходять незалежно
// від нього, тож Setting часто відстає й пропозиція оновитись не з'являлась.
const _githubBuildUrl =
    'https://github.com/zendjura-sys/laravel-12-railway/releases/latest/download/build.txt';
const _githubApkUrl =
    'https://github.com/zendjura-sys/laravel-12-railway/releases/latest/download/monsory-connect.apk';

Future<({int build, String downloadUrl})?> _fetchGithubLatestBuild() async {
  try {
    final response =
        await http.get(Uri.parse(_githubBuildUrl)).timeout(const Duration(seconds: 6));
    if (response.statusCode != 200) return null;
    final build = int.tryParse(response.body.trim());
    if (build == null) return null;
    return (build: build, downloadUrl: _githubApkUrl);
  } catch (_) {
    // Офлайн чи GitHub недоступний — не критично, просто лишаємось на
    // даних із app-config (якщо там є) замість зависання на старті.
    return null;
  }
}

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
  final github = await _fetchGithubLatestBuild();
  final effectiveLatestBuild = github != null && github.build > latestBuild ? github.build : latestBuild;
  final effectiveDownloadUrl = github != null && github.build > latestBuild ? github.downloadUrl : downloadUrl;

  if (effectiveLatestBuild <= currentBuildNumber) return;

  final dismissedRaw = await _storage.read(key: _dismissedBuildKey);
  final dismissed = int.tryParse(dismissedRaw ?? '') ?? 0;
  if (dismissed >= effectiveLatestBuild) return;

  if (!context.mounted) return;

  await showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (dialogContext) => _UpdateDialog(
      latestBuild: effectiveLatestBuild,
      message: message,
      downloadUrl: effectiveDownloadUrl,
    ),
  );
}

class _UpdateDialog extends StatefulWidget {
  final int latestBuild;
  final String? message;
  final String? downloadUrl;

  const _UpdateDialog({required this.latestBuild, this.message, this.downloadUrl});

  @override
  State<_UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends State<_UpdateDialog> {
  double? _progress;
  bool _installing = false;
  String? _error;

  Future<void> _dismiss() async {
    await _storage.write(key: _dismissedBuildKey, value: '${widget.latestBuild}');
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _updateNow() async {
    if (widget.downloadUrl == null) return;

    setState(() {
      _progress = 0;
      _error = null;
    });

    try {
      final dir = await getTemporaryDirectory();
      final filePath = '${dir.path}/monsory-connect.apk';
      final file = File(filePath);

      final request = http.Request('GET', Uri.parse(widget.downloadUrl!));
      final response = await http.Client().send(request);
      if (response.statusCode != 200) {
        throw Exception('Сервер повернув ${response.statusCode}');
      }

      final total = response.contentLength;
      var received = 0;
      final sink = file.openWrite();
      await for (final chunk in response.stream) {
        received += chunk.length;
        sink.add(chunk);
        if (total != null && total > 0 && mounted) {
          setState(() => _progress = received / total);
        }
      }
      await sink.close();

      if (!mounted) return;
      setState(() => _installing = true);

      // Далі керує системний інсталятор Android — якщо дозвіл "встановлення
      // з цього джерела" ще не надано, він сам покаже екран запиту.
      await OpenFilex.open(filePath);

      await _storage.write(key: _dismissedBuildKey, value: '${widget.latestBuild}');
      if (mounted) Navigator.of(context).pop();
    } catch (_) {
      if (mounted) {
        setState(() {
          _progress = null;
          _installing = false;
          _error = 'Не вдалося завантажити оновлення. Перевірте зʼєднання й спробуйте ще раз.';
        });
      }
    }
  }

  /// Резервний варіант — відкрити те саме посилання в браузері, якщо
  /// завантаження прямо в застосунку не вдається (наприклад, немає місця).
  Future<void> _openInBrowser() async {
    if (widget.downloadUrl == null) return;
    await launchUrl(Uri.parse(widget.downloadUrl!), mode: LaunchMode.externalApplication);
    await _storage.write(key: _dismissedBuildKey, value: '${widget.latestBuild}');
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final busy = _progress != null || _installing;

    return PopScope(
      canPop: !busy,
      child: Dialog(
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
                  border: Border.all(color: AppColors.gold400.withValues(alpha: 0.5)),
                  color: AppColors.gold400.withValues(alpha: 0.08),
                ),
                child: Icon(Icons.auto_awesome, color: AppColors.gold300, size: 26),
              ),
              const SizedBox(height: 18),
              const Text(
                'Доступна нова версія',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 10),
              Text(
                (widget.message?.trim().isNotEmpty ?? false)
                    ? widget.message!.trim()
                    : 'Ми покращили Monsory Connect — оновіть застосунок, щоб отримати все нове.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white54, fontSize: 14, height: 1.4),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Color(0xFFF87171), fontSize: 13)),
              ],
              const SizedBox(height: 24),
              if (busy) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                    value: _installing ? null : _progress,
                    minHeight: 6,
                    backgroundColor: Colors.white.withValues(alpha: 0.08),
                    valueColor: AlwaysStoppedAnimation(AppColors.gold400),
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  _installing
                      ? 'Відкриваю встановлення…'
                      : 'Завантажую… ${((_progress ?? 0) * 100).round()}%',
                  style: const TextStyle(color: Colors.white38, fontSize: 12),
                ),
              ] else ...[
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: widget.downloadUrl == null ? null : _updateNow,
                    child: const Text('ОНОВИТИ ЗАРАЗ'),
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    TextButton(onPressed: _dismiss, child: const Text('Не зараз')),
                    if (widget.downloadUrl != null)
                      TextButton(onPressed: _openInBrowser, child: const Text('У браузері')),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
