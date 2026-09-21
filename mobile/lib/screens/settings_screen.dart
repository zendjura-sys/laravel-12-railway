import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../api_client.dart';
import '../app_info.dart';
import '../services/push_notifications.dart';
import '../theme.dart';
import 'login_screen.dart';
import 'member_profile_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  Map<String, dynamic>? _me;
  Map<String, dynamic>? _telegram;
  bool _loading = true;
  bool _telegramBusy = false;
  String? _error;
  String? _telegramError;
  bool _loggingOut = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final me = await ApiClient.instance.me();
      if (mounted) setState(() => _me = me);
      // Telegram не критичний для решти екрана — окрема, тиха невдача не
      // повинна блокувати показ профілю/виходу.
      try {
        final telegram = await ApiClient.instance.telegramStatus();
        if (mounted) setState(() => _telegram = telegram);
      } catch (_) {
        // Модуль Telegram Bot може бути не встановлений — секція просто
        // не з'явиться (перевіряємо на null нижче в build()).
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити профіль.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Той самий однокроковий deep-link, що й на сайті (TelegramLinkForm.vue):
  /// беремо код і одразу відкриваємо https://t.me/{bot}?start={code} —
  /// нічого вводити не треба, прив'язка стається сама після /start у боті.
  Future<void> _linkTelegram() async {
    setState(() {
      _telegramBusy = true;
      _telegramError = null;
    });
    try {
      final data = await ApiClient.instance.telegramGenerateCode();
      final code = data['code'] as String?;
      final bot = data['bot_username'] as String?;
      if (code == null || bot == null) {
        setState(() => _telegramError = 'Бот ще не налаштований. Зверніться до адміністратора.');
        return;
      }
      final botHandle = bot.startsWith('@') ? bot.substring(1) : bot;
      await launchUrl(Uri.parse('https://t.me/$botHandle?start=$code'),
          mode: LaunchMode.externalApplication);
      final refreshed = await ApiClient.instance.telegramStatus();
      if (mounted) setState(() => _telegram = refreshed);
    } on ApiException catch (e) {
      setState(() => _telegramError = e.message);
    } catch (_) {
      setState(() => _telegramError = 'Не вдалося отримати посилання.');
    } finally {
      if (mounted) setState(() => _telegramBusy = false);
    }
  }

  Future<void> _unlinkTelegram() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.obsidian900,
        title: const Text("Відв'язати Telegram?", style: TextStyle(color: Colors.white)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text("Відв'язати")),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _telegramBusy = true);
    try {
      await ApiClient.instance.telegramUnlink();
      final refreshed = await ApiClient.instance.telegramStatus();
      if (mounted) setState(() => _telegram = refreshed);
    } finally {
      if (mounted) setState(() => _telegramBusy = false);
    }
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.obsidian900,
        title: const Text('Вийти з акаунту?',
            style: TextStyle(color: Colors.white)),
        content: const Text('Доведеться увійти знову.',
            style: TextStyle(color: Colors.white54)),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: const Text('Скасувати')),
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Вийти')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _loggingOut = true);
    await PushNotifications.instance.unregister();
    await ApiClient.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Налаштування')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!,
                          style: const TextStyle(color: Colors.white70)),
                      const SizedBox(height: 12),
                      TextButton(
                          onPressed: _load, child: const Text('Повторити')),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    InkWell(
                      borderRadius: BorderRadius.circular(20),
                      onTap: _me?['id'] == null
                          ? null
                          : () => Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) =>
                                  MemberProfileScreen(userId: _me!['id'] as int, isSelf: true))),
                      child: Container(
                        padding: const EdgeInsets.all(18),
                        decoration: glassPanelDecoration(),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _me?['name'] as String? ?? '',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w500),
                            ),
                            if (_me?['position'] != null) ...[
                              const SizedBox(height: 4),
                              Text(_me!['position'] as String,
                                  style: TextStyle(
                                      color: AppColors.gold300, fontSize: 13)),
                            ],
                            const SizedBox(height: 10),
                            Text(_me?['email'] as String? ?? '',
                                style: const TextStyle(
                                    color: Colors.white38, fontSize: 13)),
                          ],
                        ),
                      ),
                    ),
                    if (_telegram != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: glassPanelDecoration(radius: 16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.send_outlined, color: AppColors.gold300, size: 18),
                                const SizedBox(width: 8),
                                const Text('Telegram',
                                    style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                              ],
                            ),
                            const SizedBox(height: 10),
                            if (_telegram!['linked'] == true) ...[
                              Text(
                                _telegram!['telegram_username'] != null
                                    ? "Привʼязано · @${_telegram!['telegram_username']}"
                                    : 'Привʼязано',
                                style: const TextStyle(color: Color(0xFF6EE7B7), fontSize: 13),
                              ),
                              const SizedBox(height: 10),
                              TextButton(
                                onPressed: _telegramBusy ? null : _unlinkTelegram,
                                child: const Text("Відв'язати"),
                              ),
                            ] else ...[
                              Text(
                                'Привʼяжіть Telegram, щоб отримувати особисті сповіщення.',
                                style: const TextStyle(color: Colors.white38, fontSize: 12),
                              ),
                              const SizedBox(height: 10),
                              ElevatedButton(
                                onPressed: _telegramBusy ? null : _linkTelegram,
                                child: Text(_telegramBusy ? 'Відкриваємо…' : "Привʼязати Telegram"),
                              ),
                            ],
                            if (_telegramError != null) ...[
                              const SizedBox(height: 8),
                              Text(_telegramError!, style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
                            ],
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 24),
                    _SettingsTile(
                      icon: Icons.logout,
                      label: _loggingOut ? 'Виходимо…' : 'Вийти з акаунту',
                      danger: true,
                      onTap: _loggingOut ? null : _logout,
                    ),
                    const SizedBox(height: 32),
                    Center(
                      child: Text(
                        'Monsory Connect · збірка $currentBuildNumber',
                        style: const TextStyle(
                            color: Colors.white24, fontSize: 12),
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool danger;

  const _SettingsTile(
      {required this.icon,
      required this.label,
      required this.onTap,
      this.danger = false});

  @override
  Widget build(BuildContext context) {
    final color = danger ? const Color(0xFFF87171) : Colors.white;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: glassPanelDecoration(radius: 14),
        child: Row(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 14),
            Text(label, style: TextStyle(color: color, fontSize: 15)),
          ],
        ),
      ),
    );
  }
}
