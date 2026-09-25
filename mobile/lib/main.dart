import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'api_client.dart';
import 'app_info.dart';
import 'screens/blocking_screen.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'theme.dart';
import 'widgets/aurora_background.dart';

/// FCM викликає це в окремому ізоляті, поки застосунок закритий/згорнутий
/// — тому функція має бути top-level (не метод класу) і сама по собі
/// нічого не робить: системне сповіщення Android показує сам, це лише
/// точка, де можна було б додатково обробити payload.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {}

/// Спільний навігатор — щоб PushNotifications міг відкрити екран
/// сповіщень із тапу по системному сповіщенню, не маючи власного
/// BuildContext (сервіс живе поза деревом віджетів).
final navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Edge-to-edge з прозорими системними панелями: інакше Android малює
  // під плаваючою навігацією власну непрозору смугу (або сірий "скрим"
  // контрасту в режимі трьох кнопок) — прямокутну підкладку під
  // "острівцем". Відступи під системні панелі дає SafeArea.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
    systemNavigationBarColor: Colors.transparent,
    systemNavigationBarDividerColor: Colors.transparent,
    systemNavigationBarIconBrightness: Brightness.light,
    systemNavigationBarContrastEnforced: false,
  ));
  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  runApp(const MonsoryConnectApp());
}

class MonsoryConnectApp extends StatefulWidget {
  const MonsoryConnectApp({super.key});

  @override
  State<MonsoryConnectApp> createState() => _MonsoryConnectAppState();
}

class _MonsoryConnectAppState extends State<MonsoryConnectApp> {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: navigatorKey,
      title: 'Monsory Connect',
      debugShowCheckedModeBanner: false,
      // Перебудовується наново кожного разу, коли _StartupGate повідомляє
      // про застосовану палітру з /api/app-config — buildAppTheme() тоді
      // читає вже оновлені AppColors.gold*.
      theme: buildAppTheme(),
      // Аврора-фон підключається тут один раз — позаду кожного екрана,
      // без потреби вставляти окремо в кожен Scaffold.
      builder: (context, child) => Stack(
        children: [
          const AuroraBackground(),
          if (child != null) child,
        ],
      ),
      home: _StartupGate(onAccentChanged: () => setState(() {})),
    );
  }
}

/// Перший запит застосунку — GET /api/app-config (без токена, керується з
/// Admin → Налаштування → Мобільний застосунок на сайті). Якщо все гаразд,
/// далі — кабінет одразу, якщо на пристрої вже є збережений токен, інакше
/// екран входу. Токен не перевіряється тут зверненням до сервера — якщо
/// він прострочений/відкликаний, вкладка "Кабінет" сама зловить 401 на
/// першому запиті й поверне на логін.
class _StartupGate extends StatefulWidget {
  final VoidCallback? onAccentChanged;

  const _StartupGate({this.onAccentChanged});

  @override
  State<_StartupGate> createState() => _StartupGateState();
}

class _StartupGateState extends State<_StartupGate> {
  late Future<Map<String, dynamic>> _configFuture;
  bool _accentApplied = false;

  @override
  void initState() {
    super.initState();
    _configFuture = ApiClient.instance.appConfig();
  }

  void _retry() {
    setState(() => _configFuture = ApiClient.instance.appConfig());
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _configFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
              body: Center(child: CircularProgressIndicator()));
        }

        if (snapshot.hasError) {
          return BlockingScreen(
            icon: Icons.wifi_off,
            title: 'Немає звʼязку з сервером',
            message: 'Перевірте інтернет-зʼєднання й спробуйте ще раз.',
            onRetry: _retry,
          );
        }

        final config = snapshot.data!;

        // Лише один раз за життя цього стану — далі static AppColors.gold*
        // вже оновлені, повторний виклик при _retry() чи ребілді нічого не
        // зламає, але й не потрібен.
        if (!_accentApplied) {
          _accentApplied = true;
          final accentShades = config['accentShades'];
          if (accentShades is Map) {
            final changed =
                AppColors.applyAccentShades(Map<String, dynamic>.from(accentShades));
            if (changed) {
              WidgetsBinding.instance
                  .addPostFrameCallback((_) => widget.onAccentChanged?.call());
            }
          }
        }

        if (config['enabled'] != true) {
          return BlockingScreen(
            icon: Icons.build_circle_outlined,
            title: 'Технічні роботи',
            message:
                (config['maintenanceMessage'] as String?)?.trim().isNotEmpty ==
                        true
                    ? config['maintenanceMessage'] as String
                    : 'Застосунок тимчасово недоступний. Спробуйте пізніше.',
            onRetry: _retry,
          );
        }

        final minBuild = config['minBuild'] as int? ?? 0;
        if (minBuild > currentBuildNumber) {
          return BlockingScreen(
            icon: Icons.system_update,
            title: 'Оновіть застосунок',
            message:
                'Доступна нова версія Monsory Connect — продовжити роботу зі старою більше не вийде.',
            downloadUrl: config['downloadUrl'] as String?,
            onRetry: _retry,
          );
        }

        return _AfterConfigGate(config: config);
      },
    );
  }
}

class _AfterConfigGate extends StatelessWidget {
  final Map<String, dynamic> config;
  const _AfterConfigGate({required this.config});

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: ApiClient.instance.token,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
              body: Center(child: CircularProgressIndicator()));
        }
        return snapshot.data != null
            ? HomeShell(
                bankTabEnabled: config['bankTabEnabled'] != false,
                leaderboardTabEnabled: config['leaderboardTabEnabled'] != false,
                reportsTabEnabled: config['reportsTabEnabled'] != false,
                latestBuild: config['latestBuild'] as int? ?? 0,
                updateMessage: config['updateMessage'] as String?,
                downloadUrl: config['downloadUrl'] as String?,
              )
            : const LoginScreen();
      },
    );
  }
}
