import 'package:flutter/material.dart';
import '../theme.dart';
import 'leaderboard_screen.dart';
import 'reports_screen.dart';
import 'settings_screen.dart';

/// Друга вкладка нижньої навігації — сюди винесено все, чому не місце в
/// тісному бар'ю знизу (Кабінет/Банк лишаються прямими вкладками як
/// найчастіші дії): Рейтинг, Звіти й Налаштування застосунку зараз, і
/// природне місце для всього, що додасться пізніше, без розпухання самого
/// нижнього бару.
class MenuScreen extends StatelessWidget {
  final bool reportsEnabled;
  final bool leaderboardEnabled;

  const MenuScreen(
      {super.key, this.reportsEnabled = true, this.leaderboardEnabled = true});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Меню')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (reportsEnabled)
            _MenuTile(
              icon: Icons.assignment_outlined,
              title: 'Мої звіти',
              subtitle: 'Подати звіт і подивитись статус розгляду',
              onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ReportsScreen())),
            ),
          if (leaderboardEnabled)
            _MenuTile(
              icon: Icons.leaderboard_outlined,
              title: 'Рейтинг родини',
              subtitle: 'Активність, бізвар, контракти, премії',
              onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const LeaderboardScreen())),
            ),
          _MenuTile(
            icon: Icons.settings_outlined,
            title: 'Налаштування',
            subtitle: 'Профіль і вихід з акаунту',
            onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const SettingsScreen())),
          ),
        ],
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _MenuTile(
      {required this.icon,
      required this.title,
      required this.subtitle,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: glassPanelDecoration(radius: 16),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.gold400.withValues(alpha: 0.1),
                border:
                    Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
              ),
              child: Icon(icon, color: AppColors.gold300, size: 20),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w500)),
                  const SizedBox(height: 2),
                  Text(subtitle,
                      style:
                          const TextStyle(color: Colors.white38, fontSize: 12)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.white24),
          ],
        ),
      ),
    );
  }
}
