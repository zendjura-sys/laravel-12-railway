import 'dart:ui';
import 'package:flutter/material.dart';
import '../main.dart' show navigatorKey;
import '../services/push_notifications.dart';
import '../theme.dart';
import '../update_prompt.dart';
import 'bank_screen.dart';
import 'dashboard_screen.dart';
import 'menu_screen.dart';

/// Нижня навігація. Кабінет/Банк — прямі вкладки (найчастіші дії),
/// решта (Звіти/Рейтинг/Налаштування) — усередині "Меню", щоб бар знизу
/// не розпухав із кожною новою функцією застосунку. Склад теж залежить
/// від app-config (Admin → Налаштування → Мобільний застосунок) —
/// "Кабінет" і "Меню" завжди є, "Банк" можна вимкнути звідти без
/// оновлення застосунку.
class HomeShell extends StatefulWidget {
  final bool bankTabEnabled;
  final bool leaderboardTabEnabled;
  final bool reportsTabEnabled;
  final int latestBuild;
  final String? updateMessage;
  final String? downloadUrl;

  const HomeShell({
    super.key,
    this.bankTabEnabled = true,
    this.leaderboardTabEnabled = true,
    this.reportsTabEnabled = true,
    this.latestBuild = 0,
    this.updateMessage,
    this.downloadUrl,
  });

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    // Після першого кадру — Dialog потребує вже змонтований Navigator над
    // собою, а показувати запит на оновлення поверх ще порожнього екрана
    // недоречно.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      maybeShowUpdatePrompt(
        context,
        latestBuild: widget.latestBuild,
        message: widget.updateMessage,
        downloadUrl: widget.downloadUrl,
      );
    });
    PushNotifications.instance.init(navigatorKey);
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      const DashboardScreen(),
      if (widget.bankTabEnabled) const BankScreen(),
      MenuScreen(
          reportsEnabled: widget.reportsTabEnabled,
          leaderboardEnabled: widget.leaderboardTabEnabled),
    ];
    final destinations = [
      NavigationDestination(
          icon: const Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home, color: AppColors.gold300),
          label: 'Кабінет'),
      if (widget.bankTabEnabled)
        NavigationDestination(
            icon: const Icon(Icons.account_balance_outlined),
            selectedIcon: Icon(Icons.account_balance, color: AppColors.gold300),
            label: 'Банк'),
      NavigationDestination(
          icon: const Icon(Icons.menu_outlined),
          selectedIcon: Icon(Icons.menu, color: AppColors.gold300),
          label: 'Меню'),
    ];
    final index = _index.clamp(0, screens.length - 1);

    return Scaffold(
      body: IndexedStack(index: index, children: screens),
      // Справжнє "рідке скло" на панелі навігації — BackdropFilter
      // блюрить контент, що скролиться позаду, а не просто малює
      // напівпрозорий колір поверх.
      bottomNavigationBar: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
          child: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (i) => setState(() => _index = i),
            backgroundColor: AppColors.obsidian900.withValues(alpha: 0.55),
            indicatorColor: AppColors.gold400.withValues(alpha: 0.16),
            destinations: destinations,
          ),
        ),
      ),
    );
  }
}
