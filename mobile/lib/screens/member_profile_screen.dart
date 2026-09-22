import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/presence_label.dart';
import 'messenger/conversation_screen.dart';
import 'settings_screen.dart';
import '../widgets/island_top_bar.dart';

/// Профіль учасника — свій чи чужий. Свій відкривається з Кабінету чи
/// Налаштувань і веде далі в Налаштування; чужий — звідусіль, де є ім'я
/// чи аватар співрозмовника (месенджер, списки учасників), і дає одразу
/// написати повідомлення.
class MemberProfileScreen extends StatefulWidget {
  final int userId;
  final bool isSelf;

  const MemberProfileScreen({super.key, required this.userId, this.isSelf = false});

  @override
  State<MemberProfileScreen> createState() => _MemberProfileScreenState();
}

class _MemberProfileScreenState extends State<MemberProfileScreen> {
  Map<String, dynamic>? _profile;
  bool _loading = true;
  bool _startingChat = false;
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
      final data = await ApiClient.instance.userProfile(widget.userId);
      if (mounted) setState(() => _profile = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити профіль.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _message() async {
    setState(() => _startingChat = true);
    try {
      final conversationId = await ApiClient.instance.startMessengerDirect(widget.userId);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => ConversationScreen(conversationId: conversationId)));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _startingChat = false);
    }
  }

  String _formatDate(String iso) {
    final dt = DateTime.parse(iso).toLocal();
    return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}.${dt.year}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const IslandAppBar(title: 'Профіль'),
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
                    Center(
                      child: Column(
                        children: [
                          CircleAvatar(
                            radius: 54,
                            backgroundColor: AppColors.obsidian800,
                            backgroundImage: _profile!['avatarUrl'] != null
                                ? NetworkImage(_profile!['avatarUrl'] as String)
                                : null,
                            child: _profile!['avatarUrl'] == null
                                ? Text(
                                    (_profile!['name'] as String? ?? '?').substring(0, 1).toUpperCase(),
                                    style: TextStyle(fontSize: 36, color: AppColors.gold300),
                                  )
                                : null,
                          ),
                          const SizedBox(height: 16),
                          Text(
                            _profile!['name'] as String? ?? '',
                            style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w400),
                            textAlign: TextAlign.center,
                          ),
                          if (_profile!['presence'] is Map) ...[
                            const SizedBox(height: 4),
                            PresenceLabel(presence: _profile!['presence'] as Map<String, dynamic>, fontSize: 13),
                          ],
                          if (_profile!['position'] != null) ...[
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.gold400.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
                              ),
                              child: Text(_profile!['position'] as String,
                                  style: TextStyle(color: AppColors.gold300, fontSize: 12)),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 28),
                    if (!widget.isSelf)
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: _startingChat ? null : _message,
                          icon: _startingChat
                              ? const SizedBox(
                                  width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.chat_bubble_outline, size: 18),
                          label: const Text('Написати повідомлення'),
                        ),
                      )
                    else
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.of(context)
                              .push(MaterialPageRoute(builder: (_) => const SettingsScreen())),
                          icon: const Icon(Icons.settings_outlined, size: 18),
                          label: const Text('Налаштування'),
                        ),
                      ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (_profile!['memberSince'] != null)
                            _InfoRow(
                              icon: Icons.calendar_today_outlined,
                              label: 'У родині з',
                              value: _formatDate(_profile!['memberSince'] as String),
                            ),
                          if (_profile!['birthDate'] != null) ...[
                            const SizedBox(height: 12),
                            _InfoRow(
                              icon: Icons.cake_outlined,
                              label: 'День народження',
                              value: _profile!['birthDate'] as String,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: AppColors.gold300, size: 18),
        const SizedBox(width: 12),
        Text(label, style: const TextStyle(color: Colors.white38, fontSize: 13)),
        const Spacer(),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 13)),
      ],
    );
  }
}
