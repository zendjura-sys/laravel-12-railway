import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';

class AdminEventsScreen extends StatefulWidget {
  const AdminEventsScreen({super.key});

  @override
  State<AdminEventsScreen> createState() => _AdminEventsScreenState();
}

class _AdminEventsScreenState extends State<AdminEventsScreen> {
  List<dynamic> _events = [];
  bool _loading = true;
  bool _sendingDigest = false;
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
      final events = await ApiClient.instance.adminEvents();
      if (mounted) setState(() => _events = events);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити події.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _sendDigest() async {
    setState(() => _sendingDigest = true);
    try {
      await ApiClient.instance.adminSendEventsDigest();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Дайджест подій надіслано.')));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sendingDigest = false);
    }
  }

  Future<void> _delete(Map<String, dynamic> event) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Видалити подію?'),
        content: Text('«${event['title']}» зникне зі списку без можливості відновити.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Видалити', style: TextStyle(color: Color(0xFFF87171)))),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ApiClient.instance.adminDeleteEvent(event['id'] as int);
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openCreateSheet() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _CreateEventSheet(),
    );
    if (created == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final formatter = DateFormat('dd.MM.yyyy HH:mm');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Події родини'),
        actions: [
          IconButton(
            icon: _sendingDigest
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.campaign_outlined),
            tooltip: 'Надіслати дайджест усіх подій',
            onPressed: _sendingDigest ? null : _sendDigest,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _openCreateSheet,
        backgroundColor: AppColors.gold400,
        foregroundColor: AppColors.obsidian950,
        child: const Icon(Icons.add),
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
                : _events.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 80),
                          Center(
                            child: Text('Подій ще немає — створіть першу кнопкою "+".',
                                style: TextStyle(color: Colors.white38)),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(20, 20, 20, 90),
                        itemCount: _events.length,
                        itemBuilder: (context, i) {
                          final event = _events[i] as Map<String, dynamic>;
                          final startsAt = DateTime.parse(event['startsAt'] as String).toLocal();
                          final isPast = event['isPast'] == true;

                          return Opacity(
                            opacity: isPast ? 0.5 : 1,
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(16),
                              decoration: glassPanelDecoration(radius: 16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(event['title'] as String,
                                            style: const TextStyle(
                                                color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.delete_outline, color: Colors.white38, size: 20),
                                        onPressed: () => _delete(event),
                                      ),
                                    ],
                                  ),
                                  Text(formatter.format(startsAt),
                                      style: TextStyle(color: AppColors.gold300, fontSize: 13)),
                                  if (event['location'] != null) ...[
                                    const SizedBox(height: 2),
                                    Text('📍 ${event['location']}',
                                        style: const TextStyle(color: Colors.white54, fontSize: 13)),
                                  ],
                                  if (event['description'] != null) ...[
                                    const SizedBox(height: 8),
                                    Text(event['description'] as String,
                                        style: const TextStyle(color: Colors.white70, fontSize: 13)),
                                  ],
                                  const SizedBox(height: 10),
                                  Text(
                                    '✓ ${event['goingCount']} прийдуть · ✕ ${event['notGoingCount']} не підуть',
                                    style: const TextStyle(color: Colors.white38, fontSize: 12),
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

class _CreateEventSheet extends StatefulWidget {
  const _CreateEventSheet();

  @override
  State<_CreateEventSheet> createState() => _CreateEventSheetState();
}

class _CreateEventSheetState extends State<_CreateEventSheet> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _locationController = TextEditingController();
  DateTime? _startsAt;
  bool _saving = false;
  bool _aiBusy = false;
  String? _error;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _locationController.dispose();
    super.dispose();
  }

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _startsAt ?? DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_startsAt ?? DateTime.now()),
    );
    if (time == null) return;

    setState(() => _startsAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _askAi() async {
    final hint = await showDialog<String>(
      context: context,
      builder: (ctx) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Підказка для AI'),
          content: TextField(
            controller: controller,
            autofocus: true,
            decoration: const InputDecoration(hintText: 'Наприклад: новорічна вечірка в штабі'),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Скасувати')),
            TextButton(
                onPressed: () => Navigator.of(ctx).pop(controller.text.trim()),
                child: const Text('Згенерувати')),
          ],
        );
      },
    );
    if (hint == null || hint.isEmpty) return;

    setState(() {
      _aiBusy = true;
      _error = null;
    });
    try {
      final draft = await ApiClient.instance.adminEventAiDraft(hint);
      _titleController.text = draft['title'] as String? ?? _titleController.text;
      _descriptionController.text = draft['description'] as String? ?? _descriptionController.text;
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _aiBusy = false);
    }
  }

  Future<void> _save() async {
    if (_titleController.text.trim().isEmpty || _startsAt == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final startsAtStr = DateFormat('yyyy-MM-dd HH:mm:ss').format(_startsAt!);
      await ApiClient.instance.adminCreateEvent(
        title: _titleController.text.trim(),
        description: _descriptionController.text.trim(),
        location: _locationController.text.trim(),
        startsAt: startsAtStr,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final formatter = DateFormat('dd.MM.yyyy HH:mm');

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.all(20),
        decoration: glassPanelDecoration(radius: 20),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Нова подія',
                  style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w500)),
              const SizedBox(height: 16),
              TextField(
                controller: _titleController,
                decoration: const InputDecoration(labelText: 'Назва'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _descriptionController,
                decoration: const InputDecoration(labelText: 'Опис (необовʼязково)'),
                maxLines: 3,
              ),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: _aiBusy ? null : _askAi,
                  child: Text(_aiBusy ? 'Генерую…' : '✨ Згенерувати з підказки'),
                ),
              ),
              const SizedBox(height: 4),
              TextField(
                controller: _locationController,
                decoration: const InputDecoration(labelText: 'Локація (необовʼязково)'),
              ),
              const SizedBox(height: 16),
              InkWell(
                onTap: _pickDateTime,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                  decoration: BoxDecoration(
                    color: AppColors.obsidian800,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.event_outlined, color: Colors.white38, size: 20),
                      const SizedBox(width: 10),
                      Text(
                        _startsAt == null ? 'Дата й час події' : formatter.format(_startsAt!),
                        style: TextStyle(color: _startsAt == null ? Colors.white38 : Colors.white),
                      ),
                    ],
                  ),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                Text(_error!, style: const TextStyle(color: Color(0xFFF87171), fontSize: 13)),
              ],
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _saving || _titleController.text.trim().isEmpty || _startsAt == null ? null : _save,
                child: Text(_saving ? 'Створюю…' : 'Створити подію'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}
