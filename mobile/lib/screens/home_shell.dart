import 'dart:async';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../api_client.dart';
import '../main.dart' show navigatorKey;
import '../services/e2ee.dart';
import '../services/push_notifications.dart';
import '../services/update_notifier.dart';
import '../theme.dart';
import '../update_prompt.dart';
import '../widgets/island_top_bar.dart';
import 'admin_screen.dart';
import 'bank_screen.dart';
import 'dashboard_screen.dart';
import 'menu_screen.dart';
import 'notifications_screen.dart';
import 'settings_screen.dart';

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
      UpdateNotifier.instance.check();
      _checkAdmin();
      _refreshUnread();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.detached) {
      _presenceTimer?.cancel();
      _presenceTimer = null;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _presenceTimer?.cancel();
    _unreadTimer?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startPresence();
    // Нова версія — не модальним вікном на старті, а оголошенням у верхній
    // панелі, яке з'являється у фоні, щойно CI випустить збірку.
    UpdateNotifier.instance.start(
      configLatestBuild: widget.latestBuild,
      configMessage: widget.updateMessage,
      configDownloadUrl: widget.downloadUrl,
    );
    _checkAdmin();
    _unreadTimer = Timer.periodic(const Duration(seconds: 60), (_) => _refreshUnread());
    PushNotifications.instance.init(navigatorKey);
    E2eeService.instance.ensureReady();
  }

  // Пункт "Адмінка" в меню ⋮ — лише для того, у кого є хоч один *.manage
  // дозвіл; перевіряється на льоту (і при поверненні в застосунок), без
  // перелогіну після видачі ролі.
  bool _isAdmin = false;

  Future<void> _checkAdmin() async {
    try {
      final me = await ApiClient.instance.me();
      final permissions = (me['permissions'] as List?)?.cast<String>() ?? [];
      final isAdmin = permissions.any((p) => p.endsWith('.manage'));
      if (mounted && isAdmin != _isAdmin) setState(() => _isAdmin = isAdmin);
    } catch (_) {}
  }

  Timer? _unreadTimer;

  Future<void> _refreshUnread() async {
    try {
      final notifications = await ApiClient.instance.notifications();
      AppBadges.unreadNotifications.value = notifications['unreadCount'] as int? ?? 0;
    } catch (_) {}
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const NotificationsScreen()));
    _refreshUnread();
  }

  Future<void> _openUpdate() async {
    final notifier = UpdateNotifier.instance;
    final build = notifier.availableBuild;
    if (build == null) return;
    await showUpdateDialog(context,
        latestBuild: build, message: notifier.message, downloadUrl: notifier.downloadUrl);
    notifier.check();
  }

  Future<void> _openOverflowMenu(BuildContext buttonContext) async {
    final box = buttonContext.findRenderObject() as RenderBox;
    final overlay = Navigator.of(context).overlay!.context.findRenderObject() as RenderBox;
    final topRight = box.localToGlobal(box.size.bottomRight(Offset.zero), ancestor: overlay);
    final choice = await showMenu<String>(
      context: context,
      position: RelativeRect.fromLTRB(topRight.dx - 220, topRight.dy + 8, overlay.size.width - topRight.dx, 0),
      color: AppColors.obsidian900.withValues(alpha: 0.96),
      elevation: 12,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: AppColors.gold400.withValues(alpha: 0.18)),
      ),
      items: [
        _menuItem('settings', Icons.settings_outlined, 'Налаштування'),
        if (_isAdmin) _menuItem('admin', Icons.admin_panel_settings_outlined, 'Адмінка'),
      ],
    );
    if (!mounted || choice == null) return;
    final page = switch (choice) {
      'admin' => const AdminScreen(),
      _ => const SettingsScreen(),
    };
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
    _refreshUnread();
  }

  PopupMenuItem<String> _menuItem(String value, IconData icon, String label) {
    return PopupMenuItem<String>(
      value: value,
      height: 48,
      child: Row(
        children: [
          Icon(icon, color: AppColors.gold300, size: 22),
          const SizedBox(width: 14),
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 15)),
        ],
      ),
    );
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
      body: ListenableBuilder(
        listenable: UpdateNotifier.instance,
        builder: (context, _) {
          final mq = MediaQuery.of(context);
          final update = UpdateNotifier.instance.availableBuild;
          // Верхній "острівець" (як і нижній) плаває над вмістом: вкладки
          // отримують його висоту в MediaQuery.padding.top і прокручуються
          // під ним (navAwareListPadding), а не під прямокутною AppBar.
          final chrome = 8 + IslandCircleButton.size + (update != null ? 8 + UpdateBannerIsland.height : 0) + 4;
          return Stack(
            children: [
              MediaQuery(
                data: mq.copyWith(padding: mq.padding.copyWith(top: mq.padding.top + chrome)),
                child: IndexedStack(index: index, children: screens),
              ),
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Row(
                          children: [
                            ValueListenableBuilder<int>(
                              valueListenable: AppBadges.unreadNotifications,
                              builder: (context, unread, _) => IslandCircleButton(
                                icon: const Icon(Icons.notifications_outlined),
                                badge: unread,
                                tooltip: 'Сповіщення',
                                onTap: _openNotifications,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: SizedBox(
                                height: IslandCircleButton.size,
                                child: IslandTitlePill(title: 'MONSORY', subtitle: destinations[index].label),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Builder(
                              builder: (buttonContext) => IslandCircleButton(
                                icon: const Icon(Icons.more_vert_rounded),
                                tooltip: 'Ще',
                                onTap: () => _openOverflowMenu(buttonContext),
                              ),
                            ),
                          ],
                        ),
                        AnimatedSize(
                          duration: const Duration(milliseconds: 320),
                          curve: Curves.easeOutCubic,
                          alignment: Alignment.topCenter,
                          child: update == null
                              ? const SizedBox(width: double.infinity)
                              : Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: UpdateBannerIsland(
                                    buildNumber: update,
                                    onTap: _openUpdate,
                                    onDismiss: UpdateNotifier.instance.dismiss,
                                  ),
                                ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
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
