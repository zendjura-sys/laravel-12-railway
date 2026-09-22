import 'package:flutter/material.dart';
import '../theme.dart';
import 'events_screen.dart';
import 'family_goals_screen.dart';
import 'gallery_screen.dart';
import 'hall_of_fame_screen.dart';
import 'leaderboard_screen.dart';

/// Вкладка "Меню" — розділи родини (рейтинг, зал слави, події, цілі,
/// галерея). Чат і Мої звіти — кнопками на Кабінеті, Налаштування й
/// Адмінка — у меню ⋮ верхньої панелі HomeShell.
class MenuScreen extends StatefulWidget {
  final bool reportsEnabled;
  final bool leaderboardEnabled;

  const MenuScreen(
      {super.key, this.reportsEnabled = true, this.leaderboardEnabled = true});

  @override
  State<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Шапка — спільна верхня панель HomeShell; Налаштування й Адмінка —
      // у її меню ⋮, Чат і Мої звіти — кнопками на Кабінеті.
      body: ListView(
        // Вміст іде під плаваючу навігацію (extendBody у HomeShell) —
        // знизу відступ на її висоту.
        padding: navAwareListPadding(context),
        children: [
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
