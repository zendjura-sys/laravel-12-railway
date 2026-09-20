import 'package:flutter/material.dart';
import '../theme.dart';
import 'bank_screen.dart';
import 'dashboard_screen.dart';
import 'leaderboard_screen.dart';

/// Нижня навігація — три вкладки поки що (Кабінет/Банк/Рейтинг), кожна
/// сама вантажить свої дані. IndexedStack тримає всі три в памʼяті одразу,
/// щоб перемикання між вкладками не смикало мережу щоразу заново.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static const _screens = [
    DashboardScreen(),
    BankScreen(),
    LeaderboardScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        backgroundColor: AppColors.obsidian900,
        indicatorColor: AppColors.gold400.withValues(alpha: 0.12),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home, color: AppColors.gold300),
              label: 'Кабінет'),
          NavigationDestination(
              icon: Icon(Icons.account_balance_outlined),
              selectedIcon:
                  Icon(Icons.account_balance, color: AppColors.gold300),
              label: 'Банк'),
          NavigationDestination(
              icon: Icon(Icons.leaderboard_outlined),
              selectedIcon: Icon(Icons.leaderboard, color: AppColors.gold300),
              label: 'Рейтинг'),
        ],
      ),
    );
  }
}
