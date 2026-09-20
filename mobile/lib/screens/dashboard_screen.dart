import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/member_card_widget.dart';
import 'login_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.dashboard();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        await _logout();
        return;
      }
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити дані.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _logout() async {
    await ApiClient.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _data?['user'] as Map<String, dynamic>?;
    final bankCard = _data?['bankCard'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Кабінет'),
        actions: [
          IconButton(
              icon: const Icon(Icons.logout),
              onPressed: _logout,
              tooltip: 'Вийти'),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorView(message: _error!, onRetry: _load)
                : ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      Text(
                        'Вітаємо, ${user?['name'] ?? ''}',
                        style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w300,
                            color: Colors.white),
                      ),
                      if (user?['position'] != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          user!['position'] as String,
                          style:
                              TextStyle(color: AppColors.gold300, fontSize: 14),
                        ),
                      ],
                      const SizedBox(height: 24),
                      if (bankCard != null)
                        MemberCardWidget(
                          maskedNumber: bankCard['number'] as String,
                          name: bankCard['name'] as String,
                          balance: bankCard['balance'] as int,
                        )
                      else
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: glassPanelDecoration(),
                          child: const Text(
                            'Банк ще не підключено для родини.',
                            style: TextStyle(color: Colors.white54),
                          ),
                        ),
                    ],
                  ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message,
              style: const TextStyle(color: Colors.white70),
              textAlign: TextAlign.center),
          const SizedBox(height: 12),
          TextButton(onPressed: onRetry, child: const Text('Повторити')),
        ],
      ),
    );
  }
}
