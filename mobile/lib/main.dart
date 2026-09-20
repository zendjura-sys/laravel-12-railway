import 'package:flutter/material.dart';
import 'api_client.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'theme.dart';

void main() {
  runApp(const MonsoryConnectApp());
}

class MonsoryConnectApp extends StatelessWidget {
  const MonsoryConnectApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Monsory Connect',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      home: const _StartupGate(),
    );
  }
}

/// Показує кабінет одразу, якщо на пристрої вже є збережений токен, інакше
/// — екран входу. Токен не перевіряється тут звертанням до сервера — якщо
/// він прострочений/відкликаний, вкладка "Кабінет" сама зловить 401 на
/// першому запиті й поверне на логін.
class _StartupGate extends StatelessWidget {
  const _StartupGate();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: ApiClient.instance.token,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
              body: Center(child: CircularProgressIndicator()));
        }
        return snapshot.data != null ? const HomeShell() : const LoginScreen();
      },
    );
  }
}
