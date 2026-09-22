import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:audioplayers/audioplayers.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../api_client.dart';
import '../../services/e2ee.dart';
import '../../theme.dart';
import '../member_profile_screen.dart';
import '../photo_viewer_screen.dart';
import 'emoji_data.dart';

class ConversationScreen extends StatefulWidget {
  final int conversationId;

  const ConversationScreen({super.key, required this.conversationId});

  /// Розмова, яку користувач зараз реально бачить на екрані — push-банер
  /// (push_notifications.dart) звіряється з цим перед показом, щоб не
  /// дублювати повідомлення, яке й так щойно з'явилось у відкритому чаті.
  static int? currentlyOpenConversationId;

  @override
  State<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends State<ConversationScreen> {
  String _title = '';
  String _type = 'direct';
  int? _otherUserId;
  int? _otherLastReadMessageId;
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final _draftController = TextEditingController();
  final _scrollController = ScrollController();
  XFile? _photo;
  Timer? _pollTimer;
  Map<String, dynamic>? _replyingTo;
  final _voiceRecorder = AudioRecorder();
  bool _recording = false;
  bool _recordingLocked = false;
  Timer? _recordTimer;
  Duration _recordElapsed = Duration.zero;

  @override
  void initState() {
    super.initState();
    ConversationScreen.currentlyOpenConversationId = widget.conversationId;
    _load();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) => _poll());
    // Кнопка "надіслати" вмикається/вимикається залежно від тексту —
    // TextEditingController сам по собі не тригерить перебудову.
    _draftController.addListener(_onDraftChanged);
  }

  void _onDraftChanged() => setState(() {});

  @override
  void dispose() {
    // Лише якщо це досі ТА САМА розмова — навігація вглиб (наприклад,
    // у профіль учасника) не повинна скидати позначку "відкрито",
    // інакше push-банер знову з'явиться, поки чат просто позаду в стеку.
    if (ConversationScreen.currentlyOpenConversationId == widget.conversationId) {
      ConversationScreen.currentlyOpenConversationId = null;
    }
    _pollTimer?.cancel();
    _recordTimer?.cancel();
    _voiceRecorder.dispose();
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
      _otherUserId = conversation['otherUserId'] as int?;
      final messages = await _decryptIncoming(data['messages'] as List<dynamic>);
      if (mounted) {
        setState(() {
          _title = conversation['title'] as String;
          _type = conversation['type'] as String;
          _messages = messages;
          _otherLastReadMessageId = conversation['otherLastReadMessageId'] as int?;
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
      final data = await ApiClient.instance.messengerMessagesSince(widget.conversationId, _lastId);
      final fresh = data['messages'] as List<dynamic>;
      final otherLastRead = data['otherLastReadMessageId'] as int?;
      if (!mounted) return;

      if (fresh.isNotEmpty) {
        final decrypted = await _decryptIncoming(fresh);
        setState(() {
          _messages = [..._messages, ...decrypted];
          _otherLastReadMessageId = otherLastRead;
        });
        _scrollToBottom();
      } else if (otherLastRead != _otherLastReadMessageId) {
        // Немає нових повідомлень, але співрозмовник міг щойно прочитати
        // наші — оновлюємо самі лише галочки, без зайвого relayout списку.
        setState(() => _otherLastReadMessageId = otherLastRead);
      }
    } catch (_) {
      // Тиха невдача опитування — спробуємо ще раз наступним тіком.
    }
  }

  /// Наскрізне шифрування (Фаза 1) — лише direct-розмови (_otherUserId
  /// не null). Повідомлення типу text_e2ee розшифровуються тут, ОДИН РАЗ,
  /// до потрапляння в _messages — далі весь рендер (включно з
  /// _MessageBubble) працює з уже звичайним текстом і нічого не знає
  /// про шифрування.
  Future<List<dynamic>> _decryptIncoming(List<dynamic> raw) async {
    if (_otherUserId == null) return raw;

    final result = <dynamic>[];
    for (final item in raw) {
      final m = item as Map<String, dynamic>;
      if (m['type'] != 'text_e2ee') {
        result.add(m);
        continue;
      }
      final plain = await E2eeService.instance.decryptFrom(_otherUserId!, m['body'] as String);
      result.add({...m, 'body': plain ?? '🔒 Не вдалося розшифрувати повідомлення'});
    }
    return result;
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

    final replyToId = _replyingTo?['id'] as int?;

    setState(() => _sending = true);
    try {
      // encryptFor() повертає null, якщо співрозмовник ще не опублікував
      // публічний ключ (старіша версія застосунку тощо) — тоді просто
      // надсилаємо нешифрованим, як і раніше, замість блокувати відправку.
      final encrypted = _type == 'direct' && _otherUserId != null
          ? await E2eeService.instance.encryptFor(_otherUserId!, body)
          : null;
      final message = encrypted != null
          ? await ApiClient.instance
              .sendMessengerEncryptedText(widget.conversationId, encrypted, replyToMessageId: replyToId)
          : await ApiClient.instance.sendMessengerText(widget.conversationId, body, replyToMessageId: replyToId);
      // Той самий шлях, що й вхідні: сервер повертає рівно те, що
      // надіслали (шифротекст), і власне повідомлення розшифровується тим
      // самим спільним ключем (ECDH статика-статика симетрична для обох
      // напрямків), тож не потрібен окремий "я вже знаю відкритий текст" шлях.
      final decrypted = await _decryptIncoming([message]);
      setState(() {
        _messages = [..._messages, ...decrypted];
        _draftController.clear();
        _replyingTo = null;
      });
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _startReply(Map<String, dynamic> message) => setState(() => _replyingTo = message);

  Future<void> _confirmDeleteMessage(Map<String, dynamic> message) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Видалити повідомлення?'),
        content: const Text('Дію не можна скасувати.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Скасувати')),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Видалити', style: TextStyle(color: Colors.redAccent)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ApiClient.instance.deleteMessengerMessage(widget.conversationId, message['id'] as int);
      if (mounted) {
        setState(() {
          _messages = _messages.where((m) => (m as Map<String, dynamic>)['id'] != message['id']).toList();
          if (_replyingTo != null && _replyingTo!['id'] == message['id']) _replyingTo = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Не вдалося видалити повідомлення.')));
      }
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

  Future<void> _showAttachmentSheet() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: AppColors.obsidian900,
      builder: (_) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.image_outlined),
              title: const Text('Фото'),
              onTap: () => Navigator.of(context).pop('photo'),
            ),
            ListTile(
              leading: const SizedBox(width: 24, child: Text('GIF', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.bold))),
              title: const Text('Gif'),
              onTap: () => Navigator.of(context).pop('gif'),
            ),
            ListTile(
              leading: const Icon(Icons.sticky_note_2_outlined),
              title: const Text('Стікер'),
              onTap: () => Navigator.of(context).pop('sticker'),
            ),
            ListTile(
              leading: const Icon(Icons.insert_drive_file_outlined),
              title: const Text('Файл'),
              onTap: () => Navigator.of(context).pop('file'),
            ),
            ListTile(
              leading: const Icon(Icons.location_on_outlined),
              title: const Text('Геопозиція'),
              onTap: () => Navigator.of(context).pop('location'),
            ),
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: const Text('Контакт'),
              onTap: () => Navigator.of(context).pop('contact'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || choice == null) return;
    switch (choice) {
      case 'photo':
        await _pickPhoto();
      case 'gif':
        _showGifPicker();
      case 'sticker':
        _showStickerPicker();
      case 'file':
        await _sendFile();
      case 'location':
        await _sendLocation();
      case 'contact':
        await _pickContact();
    }
  }

  Future<void> _sendFile() async {
    final result = await FilePicker.pickFiles();
    final file = result?.files.single;
    if (file == null || file.path == null) return;

    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerFile(widget.conversationId, file.path!, file.name);
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<bool> _ensureLocationPermission() async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.deniedForever || permission == LocationPermission.denied) {
      return false;
    }
    return Geolocator.isLocationServiceEnabled();
  }

  Future<void> _sendLocation() async {
    setState(() => _sending = true);
    try {
      if (!await _ensureLocationPermission()) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(const SnackBar(content: Text('Немає дозволу на геопозицію.')));
        }
        return;
      }
      final position =
          await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium));
      final message =
          await ApiClient.instance.sendMessengerLocation(widget.conversationId, position.latitude, position.longitude);
      if (mounted) setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Не вдалося визначити геопозицію.')));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickContact() async {
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      backgroundColor: AppColors.obsidian900,
      isScrollControlled: true,
      builder: (_) => const _ContactPickerSheet(),
    );
    if (!mounted || selected == null) return;

    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerContact(widget.conversationId, selected['id'] as int);
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _startRecording() async {
    if (!await _voiceRecorder.hasPermission()) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Немає дозволу на мікрофон.')));
      }
      return;
    }
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _voiceRecorder.start(const RecordConfig(), path: path);
    setState(() {
      _recording = true;
      _recordingLocked = false;
      _recordElapsed = Duration.zero;
    });
    _recordTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recordElapsed += const Duration(seconds: 1));
    });
  }

  Future<void> _stopRecordingAndSend() async {
    _recordTimer?.cancel();
    final path = await _voiceRecorder.stop();
    final duration = _recordElapsed.inSeconds;
    if (mounted) setState(() { _recording = false; _recordingLocked = false; });
    // Зарано відпущений палець — менше секунди — швидше скасувати, ніж
    // надсилати порожній чи майже порожній звук.
    if (path == null || duration < 1) return;

    setState(() => _sending = true);
    try {
      final message = await ApiClient.instance.sendMessengerVoice(widget.conversationId, path, duration);
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _cancelRecording() async {
    _recordTimer?.cancel();
    await _voiceRecorder.cancel();
    if (mounted) setState(() { _recording = false; _recordingLocked = false; });
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

  String _formatDuration(int totalSeconds) {
    final m = (totalSeconds ~/ 60).toString().padLeft(2, '0');
    final s = (totalSeconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  /// Один Row для звичайного вводу й запису голосового — навмисно НЕ два
  /// окремі віджети, між якими перемикались за _recording (як було раніше).
  /// Той підхід ламав запис: onLongPressStart ставив _recording=true,
  /// setState одразу підміняв усе піддерево композера на інший віджет —
  /// і GestureDetector, що тримав активний жест "довге натискання", просто
  /// знищувався разом зі своїм розпізнавачем. Палець фізично лишався на
  /// екрані, але слухати onLongPressEnd для нього вже було нікому: запис
  /// тривав нескінченно, відпускання нічого не надсилало. Тепер кнопка
  /// мікрофона/відправки — один і той самий GestureDetector на тому самому
  /// місці Row завжди (стабільний Key), змінюється лише його вміст.
  Widget _buildComposerRow() {
    final hasContent = _draftController.text.trim().isNotEmpty || _photo != null;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        if (_recording) ...[
          IconButton(
            icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
            onPressed: _cancelRecording,
          ),
          Icon(Icons.fiber_manual_record, color: Colors.redAccent.withValues(alpha: 0.8), size: 14),
          const SizedBox(width: 8),
          Text('Запис… ${_formatDuration(_recordElapsed.inSeconds)}', style: const TextStyle(color: Colors.white70)),
          const Spacer(),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Text(
              _recordingLocked ? 'Натисніть, щоб надіслати' : 'Проведіть вгору, щоб зафіксувати',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.3), fontSize: 11),
            ),
          ),
        ] else ...[
          // Фото/gif/стікер переїхали в один пікер вкладень (_showAttachmentSheet)
          // разом із файлом/геопозицією/контактом — п'ять окремих кнопок тут
          // лишали текстовому полю замало місця й воно стискалось у вузьку
          // колонку з переносом слова "Повідомлення" по буквах.
          IconButton(icon: const Icon(Icons.attach_file), onPressed: _showAttachmentSheet),
          IconButton(icon: const Icon(Icons.emoji_emotions_outlined), onPressed: _showEmojiPicker),
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
        ],
        _buildTrailingAction(key: const ValueKey('composer-trailing-action'), hasContent: hasContent),
      ],
    );
  }

  Widget _buildTrailingAction({required Key key, required bool hasContent}) {
    if (_sending) {
      return Padding(
        key: key,
        padding: const EdgeInsets.all(12),
        child: const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
      );
    }
    // Запис зафіксовано (проведено вгору) — палець уже відпущено, запис
    // триває сам, тут звичайна кнопка "надіслати" замість утримання.
    if (_recording && _recordingLocked) {
      return IconButton(
        key: key,
        icon: Icon(Icons.send, color: AppColors.gold300),
        onPressed: _stopRecordingAndSend,
      );
    }
    if (hasContent && !_recording) {
      return IconButton(key: key, icon: Icon(Icons.send, color: AppColors.gold300), onPressed: _sendText);
    }
    // Утримання — запис (Telegram-подібний жест), проведення пальцем вгору
    // без відпускання — фіксація запису (не треба тримати весь час).
    return GestureDetector(
      key: key,
      onLongPressStart: (_) => _startRecording(),
      onLongPressMoveUpdate: (details) {
        if (_recording && !_recordingLocked && details.offsetFromOrigin.dy < -60) {
          setState(() => _recordingLocked = true);
        }
      },
      onLongPressEnd: (_) {
        if (!_recordingLocked) _stopRecordingAndSend();
      },
      child: SizedBox(
        width: 48,
        height: 48,
        child: Icon(_recording ? Icons.mic : Icons.mic_none, color: AppColors.gold300),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: InkWell(
          onTap: _type == 'direct' && _otherUserId != null
              ? () => Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => MemberProfileScreen(userId: _otherUserId!)))
              : null,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Flexible(child: Text(_title, overflow: TextOverflow.ellipsis)),
              if (_type == 'direct') ...[
                const SizedBox(width: 8),
                Tooltip(
                  message: 'Наскрізне шифрування',
                  child: Icon(Icons.lock_outline, size: 16, color: AppColors.gold300.withValues(alpha: 0.7)),
                ),
              ],
            ],
          ),
        ),
      ),
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
                          // ValueKey(id), не index — щоб бульбашка, яка вже
                          // з'явилась, не програвала анімацію заново при
                          // кожному setState() від опитування нових повідомлень.
                          return TweenAnimationBuilder<double>(
                            key: ValueKey(m['id']),
                            tween: Tween(begin: 0, end: 1),
                            duration: const Duration(milliseconds: 260),
                            curve: Curves.easeOutCubic,
                            builder: (context, t, child) => Opacity(
                              opacity: t,
                              child: Transform.translate(offset: Offset(0, (1 - t) * 10), child: child),
                            ),
                            // Dismissible з confirmDismiss, що завжди повертає
                            // false, — стандартний спосіб зробити "своп для
                            // дії" (тут — відповісти), не видаляючи елемент:
                            // бульбашка пружинить назад одразу після свопу.
                            //
                            // Align зовні — обов'язковий: Dismissible всередині
                            // сам є Stack'ом, який "розслаблює" (loosen)
                            // обмеження ширини для свого child і позиціонує
                            // його за замовчуванням по лівому краю
                            // (AlignmentDirectional.topStart), повністю
                            // ігноруючи crossAxisAlignment бульбашки всередині
                            // — без цього Align усі повідомлення (свої й
                            // чужі) прилипали ліворуч, різнились лише кольором.
                            child: Align(
                              alignment: m['isMine'] == true ? Alignment.centerRight : Alignment.centerLeft,
                              child: Dismissible(
                              key: ValueKey('reply-${m['id']}'),
                              direction: DismissDirection.startToEnd,
                              confirmDismiss: (_) async {
                                _startReply(m);
                                return false;
                              },
                              background: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 12),
                                child: Icon(Icons.reply, color: AppColors.gold300.withValues(alpha: 0.6)),
                              ),
                              child: GestureDetector(
                                onLongPress: m['isMine'] == true ? () => _confirmDeleteMessage(m) : null,
                                child: _MessageBubble(
                                  message: m,
                                  showSenderName: _type == 'family' || _type == 'deputies',
                                  showReadReceipt: _type == 'direct',
                                  otherLastReadMessageId: _otherLastReadMessageId,
                                  formatTime: _formatTime,
                                ),
                              ),
                            ),
                            ),
                          );
                        },
                      ),
                    ),
                    if (_replyingTo != null)
                      Container(
                        margin: const EdgeInsets.fromLTRB(12, 4, 12, 0),
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.05),
                          borderRadius: BorderRadius.circular(10),
                          border: Border(left: BorderSide(color: AppColors.gold400, width: 3)),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _replyingTo!['isMine'] == true
                                        ? 'Ви'
                                        : (_replyingTo!['senderName'] as String? ?? 'Учасник'),
                                    style: TextStyle(color: AppColors.gold300, fontSize: 12, fontWeight: FontWeight.w600),
                                  ),
                                  Text(
                                    _replyingTo!['body'] as String? ?? '',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.close, size: 18),
                              onPressed: () => setState(() => _replyingTo = null),
                            ),
                          ],
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
                        child: _buildComposerRow(),
                      ),
                    ),
                  ],
                ),
    );
  }
}

Map<String, dynamic> _decodeMessageBody(String raw) {
  if (raw.isEmpty) return const {};
  try {
    final decoded = jsonDecode(raw);
    return decoded is Map<String, dynamic> ? decoded : const {};
  } catch (_) {
    return const {};
  }
}

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> message;
  final bool showSenderName;
  final bool showReadReceipt;
  final int? otherLastReadMessageId;
  final String Function(String) formatTime;

  const _MessageBubble({
    required this.message,
    required this.showSenderName,
    this.showReadReceipt = false,
    this.otherLastReadMessageId,
    required this.formatTime,
  });

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
            GestureDetector(
              onTap: attachmentUrl == null
                  ? null
                  : () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => PhotoViewerScreen(photos: [attachmentUrl]))),
              child: Hero(
                tag: attachmentUrl ?? 'msg-photo-${message['id']}',
                child: Image.network(attachmentUrl ?? '', fit: BoxFit.cover),
              ),
            ),
            if (body.isNotEmpty)
              Container(
                color: isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                child: Text(body, style: TextStyle(color: isMine ? AppColors.obsidian950 : Colors.white70)),
              ),
          ],
        ),
      );
    } else if (type == 'contact') {
      content = _ContactCard(contact: message['contact'] as Map<String, dynamic>?, isMine: isMine);
    } else if (type == 'location') {
      final loc = _decodeMessageBody(body);
      content = _LocationCard(
        lat: (loc['lat'] as num?)?.toDouble(),
        lng: (loc['lng'] as num?)?.toDouble(),
        isMine: isMine,
      );
    } else if (type == 'file') {
      final meta = _decodeMessageBody(body);
      content = _FileCard(
        name: meta['name'] as String? ?? 'Файл',
        size: meta['size'] as int?,
        url: attachmentUrl,
        isMine: isMine,
      );
    } else if (type == 'voice') {
      final meta = _decodeMessageBody(body);
      content = _VoicePlayer(
        url: attachmentUrl,
        duration: meta['duration'] as int? ?? 0,
        isMine: isMine,
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
            InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: message['senderId'] == null
                  ? null
                  : () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => MemberProfileScreen(userId: message['senderId'] as int))),
              child: Padding(
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
            ),
          if (message['replyTo'] != null)
            Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
              margin: const EdgeInsets.only(bottom: 3),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.04),
                borderRadius: BorderRadius.circular(8),
                border: Border(left: BorderSide(color: AppColors.gold400.withValues(alpha: 0.6), width: 2)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    (message['replyTo'] as Map<String, dynamic>)['senderName'] as String? ?? 'Учасник',
                    style: TextStyle(color: AppColors.gold300.withValues(alpha: 0.8), fontSize: 10, fontWeight: FontWeight.w600),
                  ),
                  Text(
                    (message['replyTo'] as Map<String, dynamic>)['body'] as String? ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white38, fontSize: 11),
                  ),
                ],
              ),
            ),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
            child: content,
          ),
          Padding(
            padding: const EdgeInsets.only(top: 2, left: 4, right: 4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(formatTime(message['createdAt'] as String),
                    style: const TextStyle(color: Colors.white24, fontSize: 10)),
                if (isMine && showReadReceipt) ...[
                  const SizedBox(width: 3),
                  Builder(builder: (context) {
                    final id = message['id'] as int?;
                    final read = id != null && otherLastReadMessageId != null && id <= otherLastReadMessageId!;
                    return Icon(
                      read ? Icons.done_all : Icons.done,
                      size: 13,
                      color: read ? AppColors.gold300 : Colors.white24,
                    );
                  }),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ContactCard extends StatelessWidget {
  final Map<String, dynamic>? contact;
  final bool isMine;

  const _ContactCard({required this.contact, required this.isMine});

  @override
  Widget build(BuildContext context) {
    final fg = isMine ? AppColors.obsidian950 : Colors.white;
    final sub = isMine ? AppColors.obsidian950.withValues(alpha: 0.6) : Colors.white54;
    final avatarUrl = contact?['avatarUrl'] as String?;

    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: contact == null
          ? null
          : () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => MemberProfileScreen(userId: contact!['id'] as int))),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(16),
          border: isMine ? null : Border.all(color: Colors.white10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: Colors.white24,
              backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
              child: avatarUrl == null ? Icon(Icons.person, color: fg) : null,
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(contact?['name'] as String? ?? 'Учасника видалено',
                    style: TextStyle(color: fg, fontWeight: FontWeight.w600)),
                if ((contact?['position'] as String?)?.isNotEmpty == true)
                  Text(contact!['position'] as String, style: TextStyle(color: sub, fontSize: 12)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationCard extends StatelessWidget {
  final double? lat;
  final double? lng;
  final bool isMine;

  const _LocationCard({required this.lat, required this.lng, required this.isMine});

  Future<void> _open() async {
    if (lat == null || lng == null) return;
    final uri = Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final fg = isMine ? AppColors.obsidian950 : Colors.white;
    final sub = isMine ? AppColors.obsidian950.withValues(alpha: 0.6) : Colors.white54;

    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: lat == null || lng == null ? null : _open,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(16),
          border: isMine ? null : Border.all(color: Colors.white10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.location_on, color: fg),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Геопозиція', style: TextStyle(color: fg, fontWeight: FontWeight.w600)),
                if (lat != null && lng != null)
                  Text('${lat!.toStringAsFixed(5)}, ${lng!.toStringAsFixed(5)}',
                      style: TextStyle(color: sub, fontSize: 12)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FileCard extends StatefulWidget {
  final String name;
  final int? size;
  final String? url;
  final bool isMine;

  const _FileCard({required this.name, required this.size, required this.url, required this.isMine});

  @override
  State<_FileCard> createState() => _FileCardState();
}

class _FileCardState extends State<_FileCard> {
  bool _busy = false;

  String _formatSize(int? bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return '$bytes Б';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} КБ';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} МБ';
  }

  Future<void> _openFile() async {
    if (widget.url == null || _busy) return;
    setState(() => _busy = true);
    try {
      final dir = await getTemporaryDirectory();
      final safeName = widget.name.replaceAll(RegExp(r'[\\/]'), '_');
      final filePath = '${dir.path}/$safeName';
      final response = await http.get(Uri.parse(widget.url!));
      if (response.statusCode != 200) throw Exception('Сервер повернув ${response.statusCode}');
      await File(filePath).writeAsBytes(response.bodyBytes);
      await OpenFilex.open(filePath);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Не вдалося відкрити файл')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fg = widget.isMine ? AppColors.obsidian950 : Colors.white;
    final sub = widget.isMine ? AppColors.obsidian950.withValues(alpha: 0.6) : Colors.white54;

    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: _busy ? null : _openFile,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: widget.isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(16),
          border: widget.isMine ? null : Border.all(color: Colors.white10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _busy
                ? SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: fg))
                : Icon(Icons.insert_drive_file, color: fg),
            const SizedBox(width: 10),
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(widget.name,
                      maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg, fontWeight: FontWeight.w600)),
                  if (widget.size != null)
                    Text(_formatSize(widget.size), style: TextStyle(color: sub, fontSize: 12)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _VoicePlayer extends StatefulWidget {
  final String? url;
  final int duration;
  final bool isMine;

  const _VoicePlayer({required this.url, required this.duration, required this.isMine});

  @override
  State<_VoicePlayer> createState() => _VoicePlayerState();
}

class _VoicePlayerState extends State<_VoicePlayer> {
  final _player = AudioPlayer();
  bool _playing = false;
  Duration _position = Duration.zero;
  StreamSubscription<void>? _completeSub;
  StreamSubscription<Duration>? _positionSub;

  @override
  void initState() {
    super.initState();
    _completeSub = _player.onPlayerComplete.listen((_) {
      if (mounted) {
        setState(() {
          _playing = false;
          _position = Duration.zero;
        });
      }
    });
    _positionSub = _player.onPositionChanged.listen((p) {
      if (mounted) setState(() => _position = p);
    });
  }

  @override
  void dispose() {
    _completeSub?.cancel();
    _positionSub?.cancel();
    _player.dispose();
    super.dispose();
  }

  Future<void> _toggle() async {
    if (widget.url == null) return;
    if (_playing) {
      await _player.pause();
      if (mounted) setState(() => _playing = false);
    } else {
      await _player.play(UrlSource(widget.url!));
      if (mounted) setState(() => _playing = true);
    }
  }

  String _fmt(int totalSeconds) {
    final m = totalSeconds ~/ 60;
    final s = totalSeconds % 60;
    return '$m:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final fg = widget.isMine ? AppColors.obsidian950 : Colors.white;
    final elapsed = _position.inSeconds;
    final shown = _playing || elapsed > 0 ? elapsed : widget.duration;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: widget.isMine ? AppColors.gold400.withValues(alpha: 0.9) : Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(20),
        border: widget.isMine ? null : Border.all(color: Colors.white10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
            icon: Icon(_playing ? Icons.pause_circle_filled : Icons.play_circle_fill, color: fg, size: 32),
            onPressed: widget.url == null ? null : _toggle,
          ),
          const SizedBox(width: 8),
          Icon(Icons.graphic_eq, color: fg.withValues(alpha: 0.6), size: 18),
          const SizedBox(width: 8),
          Text(_fmt(shown), style: TextStyle(color: fg, fontSize: 12)),
        ],
      ),
    );
  }
}

class _ContactPickerSheet extends StatefulWidget {
  const _ContactPickerSheet();

  @override
  State<_ContactPickerSheet> createState() => _ContactPickerSheetState();
}

class _ContactPickerSheetState extends State<_ContactPickerSheet> {
  List<dynamic> _matches = [];
  Timer? _debounce;
  bool _loading = false;

  Future<void> _search(String query) async {
    if (query.trim().length < 2) {
      setState(() => _matches = []);
      return;
    }
    setState(() => _loading = true);
    try {
      final matches = await ApiClient.instance.searchMessengerMembers(query);
      if (mounted) setState(() => _matches = matches);
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
            const Text('Поділитись контактом',
                style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
            const SizedBox(height: 12),
            TextField(
              autofocus: true,
              decoration: const InputDecoration(hintText: "Пошук за ім'ям…"),
              onChanged: _onChanged,
            ),
            const SizedBox(height: 12),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : ListView.builder(
                      itemCount: _matches.length,
                      itemBuilder: (context, i) {
                        final m = _matches[i] as Map<String, dynamic>;
                        return ListTile(
                          leading: CircleAvatar(
                            backgroundColor: AppColors.obsidian800,
                            backgroundImage:
                                m['avatar_path'] != null ? NetworkImage(m['avatar_path'] as String) : null,
                            child: m['avatar_path'] == null
                                ? Text((m['name'] as String? ?? '?').substring(0, 1).toUpperCase())
                                : null,
                          ),
                          title: Text(m['name'] as String? ?? '', style: const TextStyle(color: Colors.white)),
                          onTap: () => Navigator.of(context).pop(m),
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
