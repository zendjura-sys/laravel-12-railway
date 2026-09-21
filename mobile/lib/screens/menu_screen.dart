import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import 'admin_screen.dart';
import 'events_screen.dart';
import 'family_goals_screen.dart';
import 'gallery_screen.dart';
import 'hall_of_fame_screen.dart';
import 'leaderboard_screen.dart';
import 'messenger/conversations_screen.dart';
import 'reports_screen.dart';
import 'settings_screen.dart';

/// Друга вкладка нижньої навігації — сюди винесено все, чому не місце в
/// тісному бар'ю знизу (Кабінет/Банк лишаються прямими вкладками як
/// найчастіші дії): Чат, Рейтинг, Звіти й Налаштування зараз, і
/// природне місце для всього, що додасться пізніше, без розпухання самого
/// нижнього бару. "Адмін" з'являється лише в того, у кого є хоч один
/// *.manage дозвіл — перевіряємо на льоту через /api/me, не чекаючи
/// перелогіну після видачі ролі.
class MenuScreen extends StatefulWidget {
  final bool reportsEnabled;
  final bool leaderboardEnabled;

  const MenuScreen(
      {super.key, this.reportsEnabled = true, this.leaderboardEnabled = true});

  @override
  State<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  bool _isAdmin = false;

  @override
  void initState() {
    super.initState();
    _checkAdmin();
  }

  Future<void> _checkAdmin() async {
    try {
      final me = await ApiClient.instance.me();
      final permissions = (me['permissions'] as List?)?.cast<String>() ?? [];
      final isAdmin = permissions.any((p) => p.endsWith('.manage'));
      if (mounted) setState(() => _isAdmin = isAdmin);
    } catch (_) {
      // Немає доступу до /api/me прямо зараз — пункт "Адмін" просто не
      // з'явиться, це не критична для решти меню помилка.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Меню')),
      body: ListView(
        // Знизу більше, ніж по інших краях — плаваюча нижня навігація не
        // ховає контент (Scaffold сам резервує під неї місце), але 20px
        // звідусіль лишали останній картці замало повітря над панеллю,
        // її край візуально впирався в блюр панелі.
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 44),
        children: [
          _MenuTile(
            icon: Icons.forum_outlined,
            title: 'Чат',
            subtitle: 'Сімейний чат і особисті розмови',
            onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const ConversationsScreen())),
          ),
          if (widget.reportsEnabled)
            _MenuTile(
              icon: Icons.assignment_outlined,
              title: 'Мої звіти',
              subtitle: 'Подати звіт і подивитись статус розгляду',
              onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ReportsScreen())),
            ),
          if (widget.leaderboardEnabled)
            _MenuTile(
              icon: Icons.leaderboard_outlined,
              title: 'Рейтинг родини',
              subtitle: 'Активність, бізвар, контракти, премії',
              onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const LeaderboardScreen())),
            ),
          if (widget.leaderboardEnabled)
            _MenuTile(
              icon: Icons.emoji_events_outlined,
              title: 'Зал слави',
              subtitle: 'Топ-3 учасники за різними категоріями',
              onTap: () => Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => const HallOfFameScreen())),
            ),
          _MenuTile(
            icon: Icons.event_outlined,
            title: 'Події родини',
            subtitle: 'Найближчі події й відповідь "прийду/не прийду"',
            onTap: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const EventsScreen())),
          ),
          _MenuTile(
            icon: Icons.flag_outlined,
            title: 'Цілі родини',
            subtitle: 'Спільний прогрес і стрічка активності',
            onTap: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const FamilyGoalsScreen())),
          ),
          _MenuTile(
            icon: Icons.photo_library_outlined,
            title: 'Галерея',
            subtitle: 'Учасники родини й фото подій',
            onTap: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const GalleryScreen())),
          ),
          if (_isAdmin)
            _MenuTile(
              icon: Icons.admin_panel_settings_outlined,
              title: 'Адмін',
              subtitle: 'Статистика родини й список учасників',
              onTap: () => Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => const AdminScreen())),
            ),
          _MenuTile(
            icon: Icons.settings_outlined,
            title: 'Налаштування',
            subtitle: 'Профіль, Telegram і вихід з акаунту',
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
