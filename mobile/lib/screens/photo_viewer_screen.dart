import 'package:flutter/material.dart';
import 'package:gal/gal.dart';
import 'package:http/http.dart' as http;

/// Повноекранний перегляд фото-доказів звіту й фото в чаті — пінч-зум через
/// InteractiveViewer, гортання між фото через PageView. Без окремого
/// пакета для перегляду (photo_view тощо): для альбому з кількох фото
/// цього достатньо. Кнопка завантаження (gal) кладе поточне фото прямо
/// в системну галерею — так само, як довге натискання в Telegram.
class PhotoViewerScreen extends StatefulWidget {
  final List<String> photos;
  final int initialIndex;

  const PhotoViewerScreen({super.key, required this.photos, this.initialIndex = 0});

  @override
  State<PhotoViewerScreen> createState() => _PhotoViewerScreenState();
}

class _PhotoViewerScreenState extends State<PhotoViewerScreen> {
  late final PageController _controller;
  late int _index;
  bool _downloading = false;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _controller = PageController(initialPage: _index);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _download() async {
    if (_downloading) return;
    setState(() => _downloading = true);
    try {
      final hasAccess = await Gal.requestAccess();
      if (!hasAccess) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(const SnackBar(content: Text('Немає дозволу на збереження фото.')));
        }
        return;
      }
      final response = await http.get(Uri.parse(widget.photos[_index]));
      if (response.statusCode != 200) throw Exception('Сервер повернув ${response.statusCode}');
      await Gal.putImageBytes(response.bodyBytes, album: 'Monsory Connect', name: 'monsory-${DateTime.now().millisecondsSinceEpoch}');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Фото збережено в галерею.')));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Не вдалося зберегти фото.')));
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        title: Text('${_index + 1} / ${widget.photos.length}'),
        actions: [
          IconButton(
            icon: _downloading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white70))
                : const Icon(Icons.download_outlined),
            onPressed: _downloading ? null : _download,
          ),
        ],
      ),
      body: PageView.builder(
        controller: _controller,
        itemCount: widget.photos.length,
        onPageChanged: (i) => setState(() => _index = i),
        itemBuilder: (context, i) => InteractiveViewer(
          minScale: 1,
          maxScale: 5,
          child: Center(
            child: Image.network(
              widget.photos[i],
              fit: BoxFit.contain,
              loadingBuilder: (context, child, progress) =>
                  progress == null ? child : const Center(child: CircularProgressIndicator()),
              errorBuilder: (context, error, stack) =>
                  const Icon(Icons.broken_image_outlined, color: Colors.white38, size: 48),
            ),
          ),
        ),
      ),
    );
  }
}
