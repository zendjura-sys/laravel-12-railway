import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

class AdminBroadcastsScreen extends StatefulWidget {
  const AdminBroadcastsScreen({super.key});

  @override
  State<AdminBroadcastsScreen> createState() => _AdminBroadcastsScreenState();
}

class _AdminBroadcastsScreenState extends State<AdminBroadcastsScreen> {
  final _titleController = TextEditingController();
  final _bodyController = TextEditingController();
  List<dynamic> _recent = [];
  List<dynamic> _roles = [];
  List<dynamic> _positions = [];
  String _audienceType = 'all';
  String? _audienceValue;
  bool _pinned = false;
  bool _sending = false;
  bool _polishing = false;
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
      final options = await ApiClient.instance.adminBroadcastAudienceOptions();
      final recent = await ApiClient.instance.adminRecentBroadcasts();
      if (mounted) {
        setState(() {
          _roles = options['roles'] as List;
          _positions = options['positions'] as List;
          _recent = recent;
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

  Future<void> _polish() async {
    if (_bodyController.text.trim().isEmpty) return;
    setState(() => _polishing = true);
    try {
      final polished = await ApiClient.instance.adminPolishBroadcastText(_bodyController.text.trim());
      _bodyController.text = polished;
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _polishing = false);
    }
  }

  Future<void> _send() async {
    if (_titleController.text.trim().isEmpty || _bodyController.text.trim().isEmpty) return;
    setState(() => _sending = true);
    try {
      await ApiClient.instance.adminSendBroadcast(
        title: _titleController.text.trim(),
        body: _bodyController.text.trim(),
        pinned: _pinned,
        audienceType: _audienceType,
        audienceValue: _audienceType == 'all' ? null : _audienceValue,
      );
      _titleController.clear();
      _bodyController.clear();
      setState(() {
        _pinned = false;
        _audienceType = 'all';
        _audienceValue = null;
      });
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Розсилку опубліковано.')));
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _bodyController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const IslandAppBar(title: 'Розсилка'),
      body: _loading
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
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          TextField(
                            controller: _titleController,
                            decoration: const InputDecoration(labelText: 'Заголовок'),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: _bodyController,
                            decoration: const InputDecoration(labelText: 'Текст'),
                            maxLines: 5,
                          ),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: _polishing ? null : _polish,
                              child: Text(_polishing ? 'Покращую…' : '✨ Покращити текст'),
                            ),
                          ),
                          const SizedBox(height: 8),
                          const Text('Кому', style: TextStyle(color: Colors.white38, fontSize: 12)),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              ChoiceChip(
                                label: const Text('Усі'),
                                selected: _audienceType == 'all',
                                onSelected: (_) => setState(() {
                                  _audienceType = 'all';
                                  _audienceValue = null;
                                }),
                              ),
                              ..._roles.map((r) => ChoiceChip(
                                    label: Text('роль: $r'),
                                    selected: _audienceType == 'role' && _audienceValue == r,
                                    onSelected: (_) => setState(() {
                                      _audienceType = 'role';
                                      _audienceValue = r as String;
                                    }),
                                  )),
                              ..._positions.map((p) {
                                final pos = p as Map<String, dynamic>;
                                return ChoiceChip(
                                  label: Text(pos['title'] as String),
                                  selected: _audienceType == 'position' && _audienceValue == pos['key'],
                                  onSelected: (_) => setState(() {
                                    _audienceType = 'position';
                                    _audienceValue = pos['key'] as String;
                                  }),
                                );
                              }),
                            ],
                          ),
                          const SizedBox(height: 8),
                          CheckboxListTile(
                            value: _pinned,
                            onChanged: (v) => setState(() => _pinned = v ?? false),
                            title: const Text('Закріпити', style: TextStyle(color: Colors.white, fontSize: 13)),
                            contentPadding: EdgeInsets.zero,
                            controlAffinity: ListTileControlAffinity.leading,
                          ),
                          const SizedBox(height: 8),
                          ElevatedButton(
                            onPressed: _sending ||
                                    _titleController.text.trim().isEmpty ||
                                    _bodyController.text.trim().isEmpty ||
                                    (_audienceType != 'all' && _audienceValue == null)
                                ? null
                                : _send,
                            child: Text(_sending ? 'Надсилаю…' : 'Опублікувати'),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 28),
                    const Text('Останні розсилки',
                        style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                    const SizedBox(height: 10),
                    ..._recent.map((b) {
                      final broadcast = b as Map<String, dynamic>;
                      return Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(14),
                        decoration: glassPanelDecoration(radius: 14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                if (broadcast['pinned'] == true) ...[
                                  Icon(Icons.push_pin, color: AppColors.gold300, size: 14),
                                  const SizedBox(width: 6),
                                ],
                                Expanded(
                                  child: Text(broadcast['title'] as String,
                                      style: const TextStyle(
                                          color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(broadcast['body'] as String,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: Colors.white54, fontSize: 13)),
                            const SizedBox(height: 6),
                            Text('${broadcast['recipientsCount']} отримувачів · ${broadcast['creatorName'] ?? ''}',
                                style: const TextStyle(color: Colors.white24, fontSize: 11)),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
    );
  }
}
