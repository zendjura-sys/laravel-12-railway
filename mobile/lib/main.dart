import 'package:flutter/material.dart';
import 'api_client.dart';
import 'app_info.dart';
import 'screens/blocking_screen.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'theme.dart';
import 'widgets/aurora_background.dart';

void main() {
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
