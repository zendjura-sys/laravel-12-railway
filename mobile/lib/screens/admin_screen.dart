import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';

/// Мінімальна нативна адмінка — лише ядро (статистика родини й список
/// учасників), без жодної аддон-специфічної дії (модерація звітів,
/// скасування переказів тощо лишаються на сайті). Бекенд (Api\AdminController)
/// уже гейтить доступ по правах, тут перевірка лише для UI.
class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  List<dynamic> _stats = [];
  List<dynamic> _users = [];
  bool _loading = true;
  String? _error;
  final _searchController = TextEditingController();

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
      final stats = await ApiClient.instance.adminStats();
      final users = await ApiClient.instance.adminUsers();
      if (mounted) {
        setState(() {
          _stats = stats;
          _users = users;
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити дані.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _search(String query) async {
    try {
      final users = await ApiClient.instance.adminUsers(query: query);
      if (mounted) setState(() => _users = users);
    } on ApiException {
      // Тиха невдача пошуку — список лишається як був.
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Адмін')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, style: const TextStyle(color: Colors.white70)),
                        const SizedBox(height: 12),
                        TextButton(onPressed: _load, child: const Text('Повторити')),
                      ],
                    ),
                  )
                : ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      if (_stats.isNotEmpty) ...[
                        Text('Статистика родини',
                            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: _stats.map((s) {
                            final stat = s as Map<String, dynamic>;
                            return Container(
                              width: 150,
                              padding: const EdgeInsets.all(14),
                              decoration: glassPanelDecoration(radius: 14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${stat['value']}',
                                      style: const TextStyle(
                                          color: AppColors.gold300, fontSize: 22, fontWeight: FontWeight.w600)),
                                  const SizedBox(height: 4),
                                  Text(stat['label'] as String,
                                      style: const TextStyle(color: Colors.white38, fontSize: 12)),
                                ],
                              ),
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: 28),
                      ],
                      Text('Учасники',
                          style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _searchController,
                        decoration: const InputDecoration(hintText: "Пошук за ім'ям чи email…"),
                        onChanged: _search,
                      ),
                      const SizedBox(height: 12),
                      ..._users.map((u) {
                        final user = u as Map<String, dynamic>;
                        final roles = (user['roles'] as List).cast<String>();
                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: glassPanelDecoration(radius: 14),
                          child: Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(user['name'] as String,
                                        style: const TextStyle(color: Colors.white, fontSize: 14)),
                                    if (user['position'] != null)
                                      Text(user['position'] as String,
                                          style: const TextStyle(color: Colors.white38, fontSize: 12)),
                                  ],
                                ),
                              ),
                              if (roles.isNotEmpty)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: AppColors.gold400.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(999),
                                    border: Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
                                  ),
                                  child: Text(roles.first,
                                      style: const TextStyle(color: AppColors.gold300, fontSize: 11)),
                                ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
      ),
    );
  }
}
