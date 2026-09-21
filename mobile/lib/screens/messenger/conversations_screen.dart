import 'dart:async';
import 'package:flutter/material.dart';
import '../../api_client.dart';
import '../../theme.dart';
import '../../widgets/fade_slide_in.dart';
import '../../widgets/shimmer_skeleton.dart';
import '../member_profile_screen.dart';
import 'conversation_screen.dart';

String _initial(String? name) {
  final trimmed = (name ?? '').trim();
  return trimmed.isEmpty ? '?' : trimmed.substring(0, 1).toUpperCase();
}

class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen> {
  List<dynamic> _conversations = [];
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
      final data = await ApiClient.instance.messengerConversations();
      if (mounted) setState(() => _conversations = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити чати.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openNewChat() async {
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      backgroundColor: AppColors.obsidian900,
      isScrollControlled: true,
      builder: (_) => const _MemberSearchSheet(),
    );
    if (selected == null || !mounted) return;

    try {
      final conversationId = await ApiClient.instance.startMessengerDirect(selected['id'] as int);
      if (!mounted) return;
      await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ConversationScreen(conversationId: conversationId)));
      _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  String _formatTime(String? iso) {
    if (iso == null) return '';
    final dt = DateTime.parse(iso).toLocal();
    final now = DateTime.now();
    final sameDay = dt.year == now.year && dt.month == now.month && dt.day == now.day;
    if (sameDay) {
      return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    }
    return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Чат'),
        actions: [
          IconButton(icon: const Icon(Icons.add_comment_outlined), onPressed: _openNewChat),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Padding(padding: EdgeInsets.all(16), child: ShimmerListSkeleton())
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
                : ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _conversations.length,
                    itemBuilder: (context, i) {
                      final c = _conversations[i] as Map<String, dynamic>;
                      final last = c['lastMessage'] as Map<String, dynamic>?;
                      final unread = c['unread'] as int? ?? 0;
                      final isFamily = c['type'] == 'family';
                      final isDeputies = c['type'] == 'deputies';
                      final isGroup = isFamily || isDeputies;

                      return FadeSlideIn(
                        index: i,
                        child: InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) => ConversationScreen(conversationId: c['id'] as int)));
                          _load();
                        },
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(14),
                          decoration: glassPanelDecoration(radius: 16),
                          child: Row(
                            children: [
                              GestureDetector(
                                onTap: isGroup || c['otherUserId'] == null
                                    ? null
                                    : () => Navigator.of(context).push(MaterialPageRoute(
                                        builder: (_) => MemberProfileScreen(userId: c['otherUserId'] as int))),
                                child: Container(
                                  width: 44,
                                  height: 44,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: isGroup
                                        ? AppColors.gold400.withValues(alpha: 0.15)
                                        : Colors.white.withValues(alpha: 0.05),
                                    border: Border.all(
                                        color: isGroup
                                            ? AppColors.gold400.withValues(alpha: 0.3)
                                            : Colors.white24),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(
                                    isFamily ? '👪' : (isDeputies ? '🎖️' : _initial(c['title'] as String?)),
                                    style: const TextStyle(fontSize: 16, color: Colors.white70),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(c['title'] as String? ?? '',
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(color: Colors.white, fontSize: 14)),
                                        ),
                                        Text(_formatTime(last?['createdAt'] as String?),
                                            style: const TextStyle(color: Colors.white24, fontSize: 11)),
                                      ],
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      last == null
                                          ? 'Ще немає повідомлень'
                                          : '${last['isMine'] == true ? 'Ви: ' : ''}${last['body'] ?? ''}',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(color: Colors.white38, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              if (unread > 0) ...[
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                  decoration: BoxDecoration(
                                      color: AppColors.gold400, borderRadius: BorderRadius.circular(999)),
                                  child: Text('$unread',
                                      style: const TextStyle(
                                          color: AppColors.obsidian950, fontSize: 11, fontWeight: FontWeight.w600)),
                                ),
                              ],
                            ],
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

class _MemberSearchSheet extends StatefulWidget {
  const _MemberSearchSheet();

  @override
  State<_MemberSearchSheet> createState() => _MemberSearchSheetState();
}

class _MemberSearchSheetState extends State<_MemberSearchSheet> {
  final _controller = TextEditingController();
  List<dynamic> _matches = [];
  Timer? _debounce;

  void _onChanged(String q) {
    _debounce?.cancel();
    if (q.trim().length < 2) {
      setState(() => _matches = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final matches = await ApiClient.instance.searchMessengerMembers(q);
      if (mounted) setState(() => _matches = matches);
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Нова розмова', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
          const SizedBox(height: 12),
          TextField(
            controller: _controller,
            autofocus: true,
            decoration: const InputDecoration(hintText: "Ім'я учасника…"),
            onChanged: _onChanged,
          ),
          const SizedBox(height: 12),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 280),
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: _matches.length,
              itemBuilder: (context, i) {
                final m = _matches[i] as Map<String, dynamic>;
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(m['name'] as String, style: const TextStyle(color: Colors.white)),
                  onTap: () => Navigator.of(context).pop(m),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
