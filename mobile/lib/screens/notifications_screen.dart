import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> _notifications = [];
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
      final data = await ApiClient.instance.notifications();
      if (mounted) setState(() => _notifications = data['notifications'] as List<dynamic>);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити сповіщення.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markRead(Map<String, dynamic> notification) async {
    if (notification['read_at'] != null) return;
    setState(() => notification['read_at'] = DateTime.now().toIso8601String());
    await ApiClient.instance.markNotificationRead(notification['id'] as int);
  }

  Future<void> _markAllRead() async {
    setState(() {
      for (final n in _notifications) {
        (n as Map<String, dynamic>)['read_at'] ??= DateTime.now().toIso8601String();
      }
    });
    await ApiClient.instance.markAllNotificationsRead();
  }

  String _formatTime(String iso) {
    final dt = DateTime.parse(iso).toLocal();
    return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')} '
        '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final hasUnread = _notifications.any((n) => (n as Map<String, dynamic>)['read_at'] == null);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Сповіщення'),
        actions: [
          if (hasUnread)
            TextButton(onPressed: _markAllRead, child: const Text('Прочитати все')),
        ],
      ),
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
                : _notifications.isEmpty
                    ? const Center(
                        child: Text('Сповіщень поки немає.', style: TextStyle(color: Colors.white38)))
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _notifications.length,
                        itemBuilder: (context, i) {
                          final n = _notifications[i] as Map<String, dynamic>;
                          final unread = n['read_at'] == null;
                          return InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () => _markRead(n),
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.all(14),
                              decoration: glassPanelDecoration(radius: 14).copyWith(
                                border: unread ? Border.all(color: AppColors.gold400.withValues(alpha: 0.3)) : null,
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (unread)
                                    Container(
                                      margin: const EdgeInsets.only(top: 5, right: 10),
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                          color: AppColors.gold400, shape: BoxShape.circle),
                                    ),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(n['title'] as String? ?? '',
                                            style: TextStyle(
                                                color: Colors.white,
                                                fontSize: 14,
                                                fontWeight: unread ? FontWeight.w600 : FontWeight.w400)),
                                        if (n['body'] != null) ...[
                                          const SizedBox(height: 4),
                                          Text(n['body'] as String,
                                              style: const TextStyle(color: Colors.white54, fontSize: 13)),
                                        ],
                                        const SizedBox(height: 6),
                                        Text(_formatTime(n['created_at'] as String),
                                            style: const TextStyle(color: Colors.white24, fontSize: 11)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}
