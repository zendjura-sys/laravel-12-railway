import 'package:flutter/material.dart';
import '../screens/photo_viewer_screen.dart';

/// Рядок мініатюр фото-доказів — тап відкриває повноекранний перегляд
/// з того самого фото. Спільний для "Мої звіти" й адмін-модерації.
class PhotoThumbnails extends StatelessWidget {
  final List<String> photos;

  const PhotoThumbnails({super.key, required this.photos});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 64,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: photos.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, i) => InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => PhotoViewerScreen(photos: photos, initialIndex: i))),
          child: Hero(
            tag: photos[i],
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.network(
                photos[i],
                width: 64,
                height: 64,
                fit: BoxFit.cover,
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : Container(width: 64, height: 64, color: Colors.white.withValues(alpha: 0.05)),
                errorBuilder: (context, error, stack) => Container(
                  width: 64,
                  height: 64,
                  color: Colors.white.withValues(alpha: 0.05),
                  child: const Icon(Icons.broken_image_outlined, color: Colors.white24, size: 20),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
