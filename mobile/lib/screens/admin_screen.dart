import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import 'admin_broadcasts_screen.dart';
import 'admin_events_screen.dart';
import 'admin_family_goals_screen.dart';
import 'admin_leave_requests_screen.dart';
import 'admin_member_edit_screen.dart';
import 'admin_reports_screen.dart';

/// Мінімальна нативна адмінка — ядро (статистика родини й список
/// учасників) плюс модерація з модулів Reports/Member Center, якщо в
/// користувача є відповідне право — жодних інших аддон-специфічних дій
/// (скасування переказів тощо лишається на сайті). Бекенд уже гейтить
/// доступ по правах, тут перевірка лише для показу/приховання плиток.
class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  List<dynamic> _stats = [];
  List<dynamic> _users = [];
  List<String> _permissions = [];
  int? _pendingReports;
  int? _pendingLeaveRequests;
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
      final me = await ApiClient.instance.me();
      final stats = await ApiClient.instance.adminStats();
      final users = await ApiClient.instance.adminUsers();
      final permissions = (me['permissions'] as List?)?.cast<String>() ?? [];
      if (mounted) {
        setState(() {
          _stats = stats;
          _users = users;
          _permissions = permissions;
        });
      }

      if (permissions.contains('reports.manage')) {
        try {
          final pending = await ApiClient.instance.adminPendingReports();
          if (mounted) setState(() => _pendingReports = pending.length);
        } catch (_) {}
      }
      if (permissions.contains('members.manage')) {
        try {
          final pending = await ApiClient.instance.adminPendingLeaveRequests();
          if (mounted) setState(() => _pendingLeaveRequests = pending.length);
        } catch (_) {}
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
                      if (_permissions.contains('reports.manage') ||
                          _permissions.contains('members.manage') ||
                          _permissions.contains('broadcasts.manage') ||
                          _permissions.contains('events.manage') ||
                          _permissions.contains('goals.manage')) ...[
                        Text('Модерація',
                            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 10),
                        if (_permissions.contains('reports.manage'))
                          _ModerationTile(
                            icon: Icons.assignment_turned_in_outlined,
                            label: 'Звіти на розгляді',
                            count: _pendingReports,
                            onTap: () async {
                              await Navigator.of(context)
                                  .push(MaterialPageRoute(builder: (_) => const AdminReportsScreen()));
                              _load();
                            },
                          ),
                        if (_permissions.contains('members.manage'))
                          _ModerationTile(
                            icon: Icons.beach_access_outlined,
                            label: 'Заявки на відпустку',
                            count: _pendingLeaveRequests,
                            onTap: () async {
                              await Navigator.of(context)
                                  .push(MaterialPageRoute(builder: (_) => const AdminLeaveRequestsScreen()));
                              _load();
                            },
                          ),
                        if (_permissions.contains('broadcasts.manage'))
                          _ModerationTile(
                            icon: Icons.campaign_outlined,
                            label: 'Розсилка',
                            count: null,
                            onTap: () => Navigator.of(context)
                                .push(MaterialPageRoute(builder: (_) => const AdminBroadcastsScreen())),
                          ),
                        if (_permissions.contains('events.manage'))
                          _ModerationTile(
                            icon: Icons.event_outlined,
                            label: 'Події родини',
                            count: null,
                            onTap: () => Navigator.of(context)
                                .push(MaterialPageRoute(builder: (_) => const AdminEventsScreen())),
                          ),
                        if (_permissions.contains('goals.manage'))
                          _ModerationTile(
                            icon: Icons.flag_outlined,
                            label: 'Цілі родини',
                            count: null,
                            onTap: () => Navigator.of(context)
                                .push(MaterialPageRoute(builder: (_) => const AdminFamilyGoalsScreen())),
                          ),
                        const SizedBox(height: 28),
                      ],
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
                                      style: TextStyle(
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
                        final canManage = _permissions.contains('users.manage');
                        return InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: canManage
                              ? () async {
                                  final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
                                      builder: (_) => AdminMemberEditScreen(user: user)));
                                  if (changed == true) _load();
                                }
                              : null,
                          child: Container(
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
                                if (roles.isNotEmpty) ...[
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: AppColors.gold400.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(999),
                                      border: Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
                                    ),
                                    child: Text(roles.first,
                                        style: TextStyle(color: AppColors.gold300, fontSize: 11)),
                                  ),
                                ],
                                if (canManage) ...[
                                  const SizedBox(width: 6),
                                  const Icon(Icons.chevron_right, color: Colors.white24, size: 18),
                                ],
                              ],
                            ),
                          ),
                        );
                      }),
                    ],
                  ),
      ),
    );
  }
}

class _ModerationTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final int? count;
  final VoidCallback onTap;

  const _ModerationTile(
      {required this.icon, required this.label, required this.count, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: glassPanelDecoration(radius: 14),
        child: Row(
          children: [
            Icon(icon, color: AppColors.gold300, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 14)),
            ),
            if (count != null && count! > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: AppColors.gold400, borderRadius: BorderRadius.circular(999)),
                child: Text('$count',
                    style: const TextStyle(
                        color: AppColors.obsidian950, fontSize: 11, fontWeight: FontWeight.w600)),
              ),
            const SizedBox(width: 6),
            const Icon(Icons.chevron_right, color: Colors.white24),
          ],
        ),
      ),
    );
  }
}
