import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../api_client.dart';
import '../../theme.dart';
import 'emoji_data.dart';

class ConversationScreen extends StatefulWidget {
  final int conversationId;

  const ConversationScreen({super.key, required this.conversationId});

  @override
  State<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends State<ConversationScreen> {
  String _title = '';
  String _type = 'direct';
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final _draftController = TextEditingController();
  final _scrollController = ScrollController();
  XFile? _photo;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) => _poll());
    // Кнопка "надіслати" вмикається/вимикається залежно від тексту —
    // TextEditingController сам по собі не тригерить перебудову.
    _draftController.addListener(_onDraftChanged);
  }

  void _onDraftChanged() => setState(() {});

  @override
  void dispose() {
    _pollTimer?.cancel();
    _draftController.removeListener(_onDraftChanged);
    _draftController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  int get _lastId => _messages.isEmpty ? 0 : _messages.last['id'] as int;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.messengerConversation(widget.conversationId);
      final conversation = data['conversation'] as Map<String, dynamic>;
      if (mounted) {
        setState(() {
          _title = conversation['title'] as String;
          _type = conversation['type'] as String;
          _messages = data['messages'] as List<dynamic>;
        });
      }
      _scrollToBottom();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити чат.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _poll() async {
    try {
      final fresh = await ApiClient.instance.messengerMessagesSince(widget.conversationId, _lastId);
      if (fresh.isNotEmpty && mounted) {
        setState(() => _messages = [..._messages, ...fresh]);
        _scrollToBottom();
      }
    } catch (_) {
      // Тиха невдача опитування — спробуємо ще раз наступним тіком.
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
      }
    });
  }

  Future<void> _sendText() async {
    final body = _draftController.text.trim();
    if (_photo != null) {
      await _sendPhoto();
      return;
    }
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerText(widget.conversationId, body);
      setState(() {
        _messages = [..._messages, message];
        _draftController.clear();
      });
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _sendPhoto() async {
    if (_photo == null || _sending) return;
    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerPhoto(
        widget.conversationId,
        _photo!,
        caption: _draftController.text.trim(),
      );
      setState(() {
        _messages = [..._messages, message];
        _draftController.clear();
        _photo = null;
      });
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickPhoto() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (picked != null) setState(() => _photo = picked);
  }

  Future<void> _sendGif(String url) async {
    Navigator.of(context).pop();
    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerGif(widget.conversationId, url);
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _sendSticker(int stickerId) async {
    Navigator.of(context).pop();
    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerSticker(widget.conversationId, stickerId);
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _showEmojiPicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.obsidian900,
      builder: (_) => SizedBox(
        height: 320,
        child: DefaultTabController(
          length: emojiGroups.length,
          child: Column(
            children: [
              TabBar(
                isScrollable: true,
                tabs: emojiGroups.map((g) => Tab(text: g.key)).toList(),
              ),
              Expanded(
                child: TabBarView(
                  children: emojiGroups.map((g) {
                    return GridView.builder(
                      padding: const EdgeInsets.all(12),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 8),
                      itemCount: g.value.length,
                      itemBuilder: (context, i) => InkWell(
                        onTap: () {
                          _draftController.text += g.value[i];
                          Navigator.of(context).pop();
                        },
                        child: Center(child: Text(g.value[i], style: const TextStyle(fontSize: 22))),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showGifPicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.obsidian900,
      isScrollControlled: true,
      builder: (_) => _GifPickerSheet(onSelect: _sendGif),
    );
  }

  void _showStickerPicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.obsidian900,
      isScrollControlled: true,
      builder: (_) => _StickerPickerSheet(onSelect: _sendSticker),
    );
  }

  String _formatTime(String iso) {
    final dt = DateTime.parse(iso).toLocal();
    return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_title)),
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
              : Column(
                  children: [
                    Expanded(
                      child: ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.all(16),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) {
                          final m = _messages[i] as Map<String, dynamic>;
                          return _MessageBubble(
                            message: m,
                            showSenderName: _type == 'family',
                            formatTime: _formatTime,
                          );
                        },
                      ),
                    ),
                    if (_photo != null)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        child: Row(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.file(File(_photo!.path), width: 56, height: 56, fit: BoxFit.cover),
                            ),
                            const SizedBox(width: 8),
                            TextButton(
                              onPressed: () => setState(() => _photo = null),
                              child: const Text('Скасувати'),
                            ),
                          ],
                        ),
                      ),
                    SafeArea(
                      top: false,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(8, 4, 8, 8),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            IconButton(icon: const Icon(Icons.image_outlined), onPressed: _pickPhoto),
                            IconButton(icon: const Icon(Icons.emoji_emotions_outlined), onPressed: _showEmojiPicker),
                            IconButton(
                                icon: const Text('GIF', style: TextStyle(fontWeight: FontWeight.bold)),
                                onPressed: _showGifPicker),
                            IconButton(icon: const Icon(Icons.sticky_note_2_outlined), onPressed: _showStickerPicker),
                            Expanded(
                              child: TextField(
                                controller: _draftController,
                                minLines: 1,
                                maxLines: 4,
                                decoration: InputDecoration(
                                  hintText: _photo != null ? 'Підпис до фото…' : 'Повідомлення…',
                                ),
                              ),
                            ),
                            IconButton(
                              icon: _sending
                                  ? const SizedBox(
                                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : Icon(Icons.send, color: AppColors.gold300),
                              onPressed: (_sending || (_draftController.text.trim().isEmpty && _photo == null))
                                  ? null
                                  : _sendText,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> message;
  final bool showSenderName;
  final String Function(String) formatTime;

  const _MessageBubble({required this.message, required this.showSenderName, required this.formatTime});

  @override
  Widget build(BuildContext context) {
    final isMine = message['isMine'] == true;
    final type = message['type'] as String? ?? 'text';
    final body = message['body'] as String? ?? '';
    final attachmentUrl = message['attachmentUrl'] as String?;

    Widget content;
    if (type == 'gif' || type == 'sticker') {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.network(attachmentUrl ?? '', height: type == 'sticker' ? 96 : 140, fit: BoxFit.contain),
      );
    } else if (type == 'photo') {
      content = ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.network(attachmentUrl ?? '', fit: BoxFit.cover),
            if (body.isNotEmpty)
              Container(
                color: isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                child: Text(body, style: TextStyle(color: isMine ? AppColors.obsidian950 : Colors.white70)),
              ),
          ],
        ),
      );
    } else {
      content = Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(16),
          border: isMine ? null : Border.all(color: Colors.white10),
        ),
        child: Text(body, style: TextStyle(color: isMine ? AppColors.obsidian950 : Colors.white70)),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          if (!isMine && showSenderName)
            Padding(
              padding: const EdgeInsets.only(left: 4, bottom: 2),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(message['senderName'] as String? ?? '',
                      style: TextStyle(color: AppColors.gold300, fontSize: 11, fontWeight: FontWeight.w500)),
                  if ((message['senderPosition'] as String?)?.isNotEmpty == true) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                      decoration: BoxDecoration(
                        color: AppColors.gold400.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
                      ),
                      child: Text(
                        (message['senderPosition'] as String).toUpperCase(),
                        style: TextStyle(color: AppColors.gold300.withValues(alpha: 0.8), fontSize: 9, letterSpacing: 0.3),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
            child: content,
          ),
          Padding(
            padding: const EdgeInsets.only(top: 2, left: 4, right: 4),
            child: Text(formatTime(message['createdAt'] as String),
                style: const TextStyle(color: Colors.white24, fontSize: 10)),
          ),
        ],
      ),
    );
  }
}

class _GifPickerSheet extends StatefulWidget {
  final ValueChanged<String> onSelect;

  const _GifPickerSheet({required this.onSelect});

  @override
  State<_GifPickerSheet> createState() => _GifPickerSheetState();
}

class _GifPickerSheetState extends State<_GifPickerSheet> {
  List<dynamic> _gifs = [];
  Timer? _debounce;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _search('');
  }

  Future<void> _search(String q) async {
    setState(() => _loading = true);
    try {
      final gifs = await ApiClient.instance.searchGifs(q);
      if (mounted) setState(() => _gifs = gifs);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onChanged(String q) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () => _search(q));
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 420,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(
              autofocus: false,
              decoration: const InputDecoration(hintText: 'Пошук gif…'),
              onChanged: _onChanged,
            ),
            const SizedBox(height: 12),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _gifs.isEmpty
                      ? const Center(
                          child: Text('Нічого не знайдено, або GIF не налаштовано адміністратором.',
                              style: TextStyle(color: Colors.white38), textAlign: TextAlign.center))
                      : GridView.builder(
                          gridDelegate:
                              const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, crossAxisSpacing: 6, mainAxisSpacing: 6),
                          itemCount: _gifs.length,
                          itemBuilder: (context, i) {
                            final gif = _gifs[i] as Map<String, dynamic>;
                            return InkWell(
                              onTap: () => widget.onSelect(gif['url'] as String),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: Image.network(gif['previewUrl'] as String, fit: BoxFit.cover),
                              ),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StickerPickerSheet extends StatefulWidget {
  final ValueChanged<int> onSelect;

  const _StickerPickerSheet({required this.onSelect});

  @override
  State<_StickerPickerSheet> createState() => _StickerPickerSheetState();
}

class _StickerPickerSheetState extends State<_StickerPickerSheet> {
  List<dynamic> _stickers = [];
  bool _loading = true;
  bool _uploading = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final stickers = await ApiClient.instance.messengerStickers();
      if (mounted) setState(() => _stickers = stickers);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _upload() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (picked == null) return;

    setState(() => _uploading = true);
    try {
      final sticker = await ApiClient.instance.uploadMessengerSticker(picked);
      if (mounted) setState(() => _stickers = [sticker, ..._stickers]);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _confirmDelete(Map<String, dynamic> sticker) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.obsidian900,
        title: const Text('Видалити стікер?', style: TextStyle(color: Colors.white)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Видалити')),
        ],
      ),
    );
    if (confirmed != true) return;

    final id = sticker['id'] as int;
    setState(() => _stickers = _stickers.where((s) => s['id'] != id).toList());
    await ApiClient.instance.deleteMessengerSticker(id);
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 380,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              children: [
                const Text('Мої стікери', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                const Spacer(),
                TextButton(
                  onPressed: _uploading ? null : _upload,
                  child: Text(_uploading ? 'Завантаження…' : '+ Додати'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _stickers.isEmpty
                      ? const Center(
                          child: Text('Ще немає стікерів — додайте перший.', style: TextStyle(color: Colors.white38)))
                      : GridView.builder(
                          gridDelegate:
                              const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 4, crossAxisSpacing: 8, mainAxisSpacing: 8),
                          itemCount: _stickers.length,
                          itemBuilder: (context, i) {
                            final sticker = _stickers[i] as Map<String, dynamic>;
                            return InkWell(
                              onTap: () => widget.onSelect(sticker['id'] as int),
                              onLongPress: () => _confirmDelete(sticker),
                              child: Container(
                                decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(10)),
                                padding: const EdgeInsets.all(6),
                                child: Image.network(sticker['url'] as String, fit: BoxFit.contain),
                              ),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
