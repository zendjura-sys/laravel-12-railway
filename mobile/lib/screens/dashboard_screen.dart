import 'package:flutter/material.dart';
import '../api_client.dart';
import '../services/push_notifications.dart';
import '../theme.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/member_card_widget.dart';
import 'login_screen.dart';
import 'member_profile_screen.dart';
import 'messenger/conversations_screen.dart';
import 'notifications_screen.dart';
import 'reports_screen.dart';

class DashboardScreen extends StatefulWidget {
  final bool reportsEnabled;

  const DashboardScreen({super.key, this.reportsEnabled = true});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  Map<String, dynamic>? _profile;
  int _unreadNotifications = 0;
  bool _loading = true;
  String? _error;

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
      final data = await ApiClient.instance.dashboard();
      if (mounted) setState(() => _data = data);

      // Прогрес і сповіщення — не критичні для решти екрана: якщо
      // модуль не встановлено чи запит не вдався, кабінет усе одно
      // показує картку й вітання, просто без цих секцій/бейджа.
      try {
        final progress = await ApiClient.instance.progress();
        if (mounted) setState(() => _profile = progress['profile'] as Map<String, dynamic>?);
      } catch (_) {}
      try {
        final notifications = await ApiClient.instance.notifications();
        if (mounted) setState(() => _unreadNotifications = notifications['unreadCount'] as int? ?? 0);
      } catch (_) {}
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        await _logout();
        return;
      }
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити дані.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _logout() async {
    await PushNotifications.instance.unregister();
    await ApiClient.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final user = _data?['user'] as Map<String, dynamic>?;
    final bankCard = _data?['bankCard'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Кабінет'),
        actions: [
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined),
                onPressed: _openNotifications,
              ),
              if (_unreadNotifications > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: TweenAnimationBuilder<double>(
                    key: ValueKey(_unreadNotifications),
                    tween: Tween(begin: 0.4, end: 1),
                    duration: const Duration(milliseconds: 220),
                    curve: Curves.elasticOut,
                    builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: BoxDecoration(
                          color: AppColors.gold400, borderRadius: BorderRadius.circular(999)),
                      constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                      child: Text(
                        _unreadNotifications > 99 ? '99+' : '$_unreadNotifications',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                            color: AppColors.obsidian950, fontSize: 10, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorView(message: _error!, onRetry: _load)
                : ListView(
                    // Знизу більше, ніж по інших краях — та сама причина,
                    // що в menu_screen.dart: плаваюча нижня навігація
                    // лишала останній картці замало повітря над собою.
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 44),
                    children: [
                      InkWell(
                        borderRadius: BorderRadius.circular(8),
                        onTap: user?['id'] == null
                            ? null
                            : () => Navigator.of(context).push(MaterialPageRoute(
                                builder: (_) =>
                                    MemberProfileScreen(userId: user!['id'] as int, isSelf: true))),
                        child: Text(
                          'Вітаємо, ${user?['name'] ?? ''}',
                          style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w300,
                              color: Colors.white),
                        ),
                      ),
                      if (user?['position'] != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          user!['position'] as String,
                          style:
                              TextStyle(color: AppColors.gold300, fontSize: 14),
                        ),
                      ],
                      const SizedBox(height: 24),
                      FadeSlideIn(
                        index: 0,
                        child: bankCard != null
                            ? MemberCardWidget(
                                maskedNumber: bankCard['number'] as String,
                                name: bankCard['name'] as String,
                                balance: bankCard['balance'] as int,
                              )
                            : Container(
                                padding: const EdgeInsets.all(20),
                                decoration: glassPanelDecoration(),
                                child: const Text(
                                  'Банк ще не підключено для родини.',
                                  style: TextStyle(color: Colors.white54),
                                ),
                              ),
                      ),
                      if (_profile != null) ...[
                        const SizedBox(height: 24),
                        FadeSlideIn(index: 1, child: _ProgressStats(profile: _profile!)),
                      ],
                      const SizedBox(height: 24),
                      FadeSlideIn(
                        index: 2,
                        child: Row(
                          children: [
                            Expanded(
                              child: _QuickActionButton(
                                icon: Icons.forum_outlined,
                                label: 'Чат',
                                onTap: () => Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const ConversationsScreen())),
                              ),
                            ),
                            if (widget.reportsEnabled) ...[
                              const SizedBox(width: 12),
                              Expanded(
                                child: _QuickActionButton(
                                  icon: Icons.assignment_outlined,
                                  label: 'Звіти',
                                  onTap: () => Navigator.of(context)
                                      .push(MaterialPageRoute(builder: (_) => const ReportsScreen())),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
      ),
    );
  }
}

class _QuickActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _QuickActionButton({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: glassPanelDecoration(),
        child: Column(
          children: [
            Icon(icon, color: AppColors.gold300),
            const SizedBox(height: 6),
            Text(label, style: const TextStyle(color: Colors.white70, fontSize: 13)),
          ],
        ),
      ),
    );
  }
}

class _ProgressStats extends StatelessWidget {
  final Map<String, dynamic> profile;

  const _ProgressStats({required this.profile});

  @override
  Widget build(BuildContext context) {
    final tiles = [
      ('Рівень', '${profile['level'] ?? '—'}'),
      ('Досвід', '${profile['xp'] ?? 0}'),
      ('Серія', '${profile['current_streak'] ?? 0}'),
      ('Контракти', '${profile['contracts_count'] ?? 0}'),
    ];

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: glassPanelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Прогрес',
              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
          const SizedBox(height: 14),
          Row(
            children: tiles
                .map((t) => Expanded(
                      child: Column(
                        children: [
                          Text(t.$2,
                              style: TextStyle(
                                  color: AppColors.gold300, fontSize: 18, fontWeight: FontWeight.w600)),
                          const SizedBox(height: 2),
                          Text(t.$1,
                              style: const TextStyle(color: Colors.white38, fontSize: 11),
                              textAlign: TextAlign.center),
                        ],
                      ),
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message,
              style: const TextStyle(color: Colors.white70),
              textAlign: TextAlign.center),
          const SizedBox(height: 12),
          TextButton(onPressed: onRetry, child: const Text('Повторити')),
        ],
      ),
    );
  }
}
