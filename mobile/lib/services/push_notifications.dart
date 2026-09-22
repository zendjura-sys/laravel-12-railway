import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import '../api_client.dart';
import '../screens/messenger/conversation_screen.dart';
import '../screens/notifications_screen.dart';
import 'notification_router.dart';

/// Мобільний push (FCM) — четвертий канал доставки поруч із web-копією,
/// Telegram і браузерним push (див. серверний
/// Addons\Notifications\Services\NotificationService). Мовчить, якщо
/// пристрій відмовив у дозволі на сповіщення чи бекенд ще не налаштував
/// службовий обліковий запис Firebase — тоді просто немає push, решта
/// застосунку працює як завжди.
class PushNotifications {
  PushNotifications._();
  static final PushNotifications instance = PushNotifications._();

  final _messaging = FirebaseMessaging.instance;
  String? _registeredToken;
  bool _initialized = false;

  /// Викликається один раз, коли застосунок бачить, що користувач уже
  /// увійшов (HomeShell.initState) — реєструвати токен для неавтентифікованої
  /// сесії нема сенсу, /api/device-tokens все одно вимагає auth:sanctum.
  Future<void> init(GlobalKey<NavigatorState> navigatorKey) async {
    if (_initialized) return;
    _initialized = true;

    try {
      final settings = await _messaging.requestPermission(alert: true, badge: true, sound: true);
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final token = await _messaging.getToken();
      if (token != null) await _registerToken(token);

      _messaging.onTokenRefresh.listen(_registerToken);

      // Застосунок відкритий (foreground) — FCM сам не показує системне
      // сповіщення в цьому стані, тож даємо коротку репліку зверху; при
      // згорнутому/закритому застосунку Android показує сповіщення сам,
      // без участі коду тут.
      FirebaseMessaging.onMessage.listen((message) {
        final title = message.notification?.title;
        final body = message.notification?.body;
        if (title == null) return;

        final url = message.data['url'] as String?;
        // Повідомлення саме в ту розмову, яку користувач і так дивиться
        // зараз, — воно вже з'явилось у чаті через опитування, репліка
        // зверху тут лише дублює те, що й так видно.
        if (url != null && _isCurrentlyOpenConversation(url)) return;

        final context = navigatorKey.currentContext;
        if (context == null) return;
        // Контекст береться наживо всередині синхронного колбека listen(),
        // а не зберігається через await — попередження хибне.
        // ignore: use_build_context_synchronously
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(body != null ? '$title\n$body' : title),
          behavior: SnackBarBehavior.floating,
          action: url == null
              ? null
              : SnackBarAction(
                  label: 'Відкрити',
                  // ignore: use_build_context_synchronously
                  onPressed: () => openNotificationTarget(context, url),
                ),
        ));
      });

      // Застосунок був згорнутий і його відкрили тапом по сповіщенню —
      // ведемо за призначенням (конкретний чат, звіти на розгляд тощо),
      // якщо url розпізнано, інакше — у загальний список сповіщень.
      FirebaseMessaging.onMessageOpenedApp.listen((message) => _openTarget(navigatorKey, message));

      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null) _openTarget(navigatorKey, initialMessage);
    } catch (_) {
      // Немає Google Play Services на пристрої, дозвіл відхилено назавжди
      // тощо — push просто не працює, решта застосунку не повинна падати.
    }
  }

  void _openNotifications(GlobalKey<NavigatorState> navigatorKey) {
    navigatorKey.currentState?.push(
        MaterialPageRoute(builder: (_) => const NotificationsScreen()));
  }

  bool _isCurrentlyOpenConversation(String url) {
    final path = Uri.tryParse(url)?.path ?? url;
    if (!path.startsWith('/messenger/')) return false;
    final id = int.tryParse(path.replaceFirst('/messenger/', ''));
    return id != null && id == ConversationScreen.currentlyOpenConversationId;
  }

  Future<void> _openTarget(GlobalKey<NavigatorState> navigatorKey, RemoteMessage message) async {
    final context = navigatorKey.currentContext;
    if (context == null) return;
    // ignore: use_build_context_synchronously
    final opened = await openNotificationTarget(context, message.data['url'] as String?);
    if (!opened) _openNotifications(navigatorKey);
  }

  Future<void> _registerToken(String token) async {
    if (token == _registeredToken) return;
    try {
      await ApiClient.instance.registerDeviceToken(token, platform: 'android');
      _registeredToken = token;
    } catch (_) {
      // Немає мережі саме в цю мить — спробуємо знову за наступного init()
      // (наступний запуск застосунку) чи onTokenRefresh.
    }
  }

  /// Викликається перед виходом з акаунту — щоб пристрій, який лишився в
  /// чужих руках, не отримував push уже колишнього користувача.
  Future<void> unregister() async {
    if (_registeredToken == null) return;
    try {
      await ApiClient.instance.unregisterDeviceToken(_registeredToken!);
    } catch (_) {}
    _registeredToken = null;
    _initialized = false;
  }
}
