import 'package:flutter/material.dart';
import '../theme.dart';
import 'bank_screen.dart';
import 'dashboard_screen.dart';
import 'leaderboard_screen.dart';
import 'reports_screen.dart';

/// Нижня навігація. Кількість і склад вкладок залежать від app-config
/// (Admin → Налаштування → Мобільний застосунок на сайті) — "Кабінет"
/// завжди є, решта можна вимкнути звідти без оновлення застосунку.
/// IndexedStack тримає ввімкнені вкладки в памʼяті одразу, щоб перемикання
/// між ними не смикало мережу щоразу заново.
class HomeShell extends StatefulWidget {
  final bool bankTabEnabled;
  final bool leaderboardTabEnabled;
  final bool reportsTabEnabled;

  const HomeShell({
    super.key,
    this.bankTabEnabled = true,
    this.leaderboardTabEnabled = true,
    this.reportsTabEnabled = true,
  });

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      const DashboardScreen(),
      if (widget.reportsTabEnabled) const ReportsScreen(),
      if (widget.bankTabEnabled) const BankScreen(),
      if (widget.leaderboardTabEnabled) const LeaderboardScreen(),
    ];
    final destinations = [
      const NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home, color: AppColors.gold300),
          label: 'Кабінет'),
      if (widget.reportsTabEnabled)
        const NavigationDestination(
            icon: Icon(Icons.assignment_outlined),
            selectedIcon: Icon(Icons.assignment, color: AppColors.gold300),
            label: 'Звіти'),
      if (widget.bankTabEnabled)
        const NavigationDestination(
            icon: Icon(Icons.account_balance_outlined),
            selectedIcon: Icon(Icons.account_balance, color: AppColors.gold300),
            label: 'Банк'),
      if (widget.leaderboardTabEnabled)
        const NavigationDestination(
            icon: Icon(Icons.leaderboard_outlined),
            selectedIcon: Icon(Icons.leaderboard, color: AppColors.gold300),
            label: 'Рейтинг'),
    ];
    final index = _index.clamp(0, screens.length - 1);

    return Scaffold(
      body: IndexedStack(index: index, children: screens),
      bottomNavigationBar: destinations.length > 1
          ? NavigationBar(
              selectedIndex: index,
              onDestinationSelected: (i) => setState(() => _index = i),
              backgroundColor: AppColors.obsidian900,
              indicatorColor: AppColors.gold400.withValues(alpha: 0.12),
              destinations: destinations,
            )
          : null,
    );
  }
}
