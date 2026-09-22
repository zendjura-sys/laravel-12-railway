import 'dart:async';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../api_client.dart';
import '../main.dart' show navigatorKey;
import '../services/e2ee.dart';
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

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  int _index = 0;

  // Heartbeat онлайн-статусу (🟢): поки застосунок на екрані — раз на
  // 45с; у фоні таймер зупиняється, і за ~2 хв учасник стає 🔴 з
  // "був(-ла) у мережі …". Кожен інший API-запит теж оновлює статус.
  Timer? _presenceTimer;

  void _startPresence() {
    _presenceTimer?.cancel();
    ApiClient.instance.presencePing();
    _presenceTimer = Timer.periodic(const Duration(seconds: 45), (_) => ApiClient.instance.presencePing());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _startPresence();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.detached) {
      _presenceTimer?.cancel();
      _presenceTimer = null;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _presenceTimer?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startPresence();
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
    E2eeService.instance.ensureReady();
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      DashboardScreen(reportsEnabled: widget.reportsTabEnabled),
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
      // extendBody — вміст вкладок продовжується ПІД панеллю, тож навколо
      // "острівця" той самий фон, що й у вікні, а не прямокутний слот
      // bottomNavigationBar з обірваним над ним вмістом. Сам слот нічим
      // не заливається: оформлення (колір, радіус, обводка) — лише в
      // Container острівця всередині ClipRRect.
      extendBody: true,
      backgroundColor: Colors.transparent,
      body: IndexedStack(index: index, children: screens),
      // Плаваючий "острівець" у стилі композера Claude: великий радіус,
      // неактивні вкладки — круглі кнопки лише з іконкою, активна
      // розгортається в пілюлю з назвою. Зовнішній радіус = радіус кнопки
      // (26) + внутрішній відступ (10) — контури концентричні, кути
      // крайніх кнопок не впираються в дугу контейнера.
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(16, 0, 16, 10),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(36),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                // Фон — той самий, що в головного вікна: легкий
                // напівпрозорий obsidian950 поверх AuroraBackground, тож
                // крізь панель видно те саме золоте світіння, а не
                // суцільний темний блок, що "відрізав" низ екрана.
                color: AppColors.obsidian950.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(36),
                border: Border.all(color: AppColors.gold400.withValues(alpha: 0.14)),
              ),
              child: Row(
                mainAxisAlignment: destinations.length > 2
                    ? MainAxisAlignment.spaceBetween
                    : MainAxisAlignment.spaceEvenly,
                children: [
                  for (var i = 0; i < destinations.length; i++)
                    _PillNavItem(
                      destination: destinations[i],
                      selected: i == index,
                      onTap: () {
                        if (i != index) HapticFeedback.selectionClick();
                        setState(() => _index = i);
                      },
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PillNavItem extends StatelessWidget {
  final NavigationDestination destination;
  final bool selected;
  final VoidCallback onTap;

  const _PillNavItem({required this.destination, required this.selected, required this.onTap});

  static const double _size = 52;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.gold300 : Colors.white70;
    return Semantics(
      button: true,
      selected: selected,
      label: destination.label,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          customBorder: const StadiumBorder(),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 260),
            curve: Curves.easeOutCubic,
            height: _size,
            constraints: const BoxConstraints(minWidth: _size),
            padding: EdgeInsets.symmetric(horizontal: selected ? 20 : 0),
            decoration: ShapeDecoration(
              color: selected
                  ? AppColors.gold400.withValues(alpha: 0.16)
                  : Colors.white.withValues(alpha: 0.07),
              shape: StadiumBorder(
                side: BorderSide(
                  color: selected
                      ? AppColors.gold400.withValues(alpha: 0.35)
                      : Colors.white.withValues(alpha: 0.06),
                ),
              ),
            ),
            child: AnimatedSize(
              duration: const Duration(milliseconds: 260),
              curve: Curves.easeOutCubic,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconTheme(
                    data: IconThemeData(color: color, size: 24),
                    child: selected ? (destination.selectedIcon ?? destination.icon) : destination.icon,
                  ),
                  if (selected) ...[
                    const SizedBox(width: 10),
                    Text(
                      destination.label,
                      maxLines: 1,
                      style: TextStyle(
                        color: color,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.2,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
