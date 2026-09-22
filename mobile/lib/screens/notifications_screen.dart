import 'package:flutter/material.dart';
import '../api_client.dart';
import '../services/notification_router.dart';
import '../theme.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/island_top_bar.dart';
import '../widgets/selection_delete_bar.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> _notifications = [];
  bool _loading = true;
  String? _error;
  // Режим вибору — вмикається кнопкою 🗑 у шапці: кружечки біля
  // сповіщень і нижня панель "Видалити всі / Видалити (N)".
  bool _selecting = false;
  final Set<int> _selected = {};
  bool _deleting = false;

  void _toggleSelecting() => setState(() {
        _selecting = !_selecting;
        _selected.clear();
      });

  void _toggle(int id) => setState(() => _selected.contains(id) ? _selected.remove(id) : _selected.add(id));

  Future<void> _deleteSelected() async {
    final ids = _selected.toList();
    if (!await confirmDeletion(context,
        title: 'Видалити сповіщення?', message: 'Буде видалено вибрані сповіщення (${ids.length}).')) {
      return;
    }
    await _bulkDelete(() => ApiClient.instance.bulkDeleteNotifications(ids: ids),
        (n) => ids.contains(n['id']));
  }

  Future<void> _deleteAll() async {
    if (!await confirmDeletion(context,
        title: 'Видалити всі сповіщення?', message: 'Буде видалено всі сповіщення (${_notifications.length}).')) {
      return;
    }
    await _bulkDelete(() => ApiClient.instance.bulkDeleteNotifications(all: true), (_) => true);
  }

  Future<void> _bulkDelete(Future<void> Function() request, bool Function(Map<String, dynamic>) removed) async {
    setState(() => _deleting = true);
    try {
      await request();
      if (!mounted) return;
      setState(() {
        _notifications = _notifications.where((n) => !removed(n as Map<String, dynamic>)).toList();
        _selecting = false;
        _selected.clear();
      });
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Не вдалося видалити.')));
      }
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

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

  Future<void> _open(Map<String, dynamic> notification) async {
    _markRead(notification);
    await openNotificationTarget(context, notification['url'] as String?);
  }

  Future<void> _markAllRead() async {
    setState(() {
      for (final n in _notifications) {
        (n as Map<String, dynamic>)['read_at'] ??= DateTime.now().toIso8601String();
      }
    });
    await ApiClient.instance.markAllNotificationsRead();
  }

  Future<void> _delete(Map<String, dynamic> notification) async {
    setState(() => _notifications.remove(notification));
    try {
      await ApiClient.instance.deleteNotification(notification['id'] as int);
    } catch (_) {
      // Мережа підвела — повертаємо назад, щоб список не розходився з сервером.
      if (mounted) setState(() => _notifications.insert(0, notification));
    }
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
      appBar: IslandAppBar(
        title: _selecting ? 'Вибрано: ${_selected.length}' : 'Сповіщення',
        actions: [
          if (hasUnread && !_selecting)
            IslandCircleButton(icon: const Icon(Icons.done_all_rounded), tooltip: 'Прочитати все', onTap: _markAllRead),
          if (_notifications.isNotEmpty || _selecting)
            IslandCircleButton(
              icon: Icon(_selecting ? Icons.close_rounded : Icons.delete_outline_rounded),
              tooltip: _selecting ? 'Скасувати' : 'Видалити',
              onTap: _toggleSelecting,
            ),
        ],
      ),
      bottomNavigationBar: _selecting
          ? SelectionDeleteBar(
              selectedCount: _selected.length,
              busy: _deleting,
              onDeleteAll: _deleteAll,
              onDeleteSelected: _deleteSelected,
            )
          : null,
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
                          return FadeSlideIn(
                            index: i,
                            child: Dismissible(
                              key: ValueKey(n['id']),
                              direction: _selecting ? DismissDirection.none : DismissDirection.endToStart,
                              onDismissed: (_) => _delete(n),
                              background: Container(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.symmetric(horizontal: 20),
                                alignment: Alignment.centerRight,
                                decoration: BoxDecoration(
                                  color: Colors.redAccent.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: const Icon(Icons.delete_outline, color: Colors.redAccent),
                              ),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(14),
                                onTap: () => _selecting ? _toggle(n['id'] as int) : _open(n),
                                onLongPress: _selecting
                                    ? null
                                    : () => setState(() {
                                          _selecting = true;
                                          _selected
                                            ..clear()
                                            ..add(n['id'] as int);
                                        }),
                                child: Container(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  padding: const EdgeInsets.all(14),
                                  decoration: glassPanelDecoration(radius: 14).copyWith(
                                    border: unread ? Border.all(color: AppColors.gold400.withValues(alpha: 0.3)) : null,
                                  ),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      if (_selecting)
                                        Padding(
                                          padding: const EdgeInsets.only(right: 12),
                                          child: SelectionMark(selected: _selected.contains(n['id'])),
                                        ),
                                      if (unread)
                                        Container(
                                          margin: const EdgeInsets.only(top: 5, right: 10),
                                          width: 8,
                                          height: 8,
                                          decoration: BoxDecoration(
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
                              ),
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}
