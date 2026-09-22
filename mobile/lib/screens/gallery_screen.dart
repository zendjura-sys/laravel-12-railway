import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/shimmer_skeleton.dart';
import 'photo_viewer_screen.dart';
import '../widgets/island_top_bar.dart';

class GalleryScreen extends StatefulWidget {
  const GalleryScreen({super.key});

  @override
  State<GalleryScreen> createState() => _GalleryScreenState();
}

class _GalleryScreenState extends State<GalleryScreen> {
  List<dynamic> _memberPhotos = [];
  List<dynamic> _galleryPhotos = [];
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
      final data = await ApiClient.instance.gallery();
      if (mounted) {
        setState(() {
          _memberPhotos = data['memberPhotos'] as List<dynamic>;
          _galleryPhotos = data['galleryPhotos'] as List<dynamic>;
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити галерею.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openViewer(List<dynamic> photos, int index) {
    final urls = photos.map((p) => (p as Map<String, dynamic>)['url'] as String).toList();
    Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => PhotoViewerScreen(photos: urls, initialIndex: index)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const IslandAppBar(title: 'Галерея'),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Padding(padding: EdgeInsets.all(20), child: ShimmerGridSkeleton())
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
                : _memberPhotos.isEmpty && _galleryPhotos.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 80),
                          Center(
                            child: Text('Фото поки що немає.', style: TextStyle(color: Colors.white38)),
                          ),
                        ],
                      )
                    : ListView(
                        padding: const EdgeInsets.symmetric(vertical: 20),
                        children: [
                          if (_memberPhotos.isNotEmpty) ...[
                            const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 20),
                              child: Text('Учасники родини',
                                  style: TextStyle(
                                      color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              height: 96,
                              child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(horizontal: 20),
                                itemCount: _memberPhotos.length,
                                separatorBuilder: (_, __) => const SizedBox(width: 14),
                                itemBuilder: (context, i) {
                                  final member = _memberPhotos[i] as Map<String, dynamic>;
                                  return GestureDetector(
                                    onTap: () => _openViewer(_memberPhotos, i),
                                    child: SizedBox(
                                      width: 72,
                                      child: Column(
                                        children: [
                                          Container(
                                            width: 64,
                                            height: 64,
                                            decoration: BoxDecoration(
                                              shape: BoxShape.circle,
                                              border: Border.all(
                                                  color: AppColors.gold400.withValues(alpha: 0.4), width: 1.5),
                                            ),
                                            child: Hero(
                                              tag: member['url'] as String,
                                              child: ClipOval(
                                                child: Image.network(
                                                  member['url'] as String,
                                                  fit: BoxFit.cover,
                                                  errorBuilder: (context, error, stack) => const Icon(
                                                      Icons.person_outline,
                                                      color: Colors.white38),
                                                ),
                                              ),
                                            ),
                                          ),
                                          const SizedBox(height: 6),
                                          Text(
                                            member['name'] as String? ?? '',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(color: Colors.white70, fontSize: 11),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),
                            const SizedBox(height: 24),
                          ],
                          if (_galleryPhotos.isNotEmpty) ...[
                            const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 20),
                              child: Text('Галерея родини',
                                  style: TextStyle(
                                      color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                            ),
                            const SizedBox(height: 12),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 20),
                              child: GridView.builder(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: 3,
                                  crossAxisSpacing: 8,
                                  mainAxisSpacing: 8,
                                ),
                                itemCount: _galleryPhotos.length,
                                itemBuilder: (context, i) {
                                  final photo = _galleryPhotos[i] as Map<String, dynamic>;
                                  return GestureDetector(
                                    onTap: () => _openViewer(_galleryPhotos, i),
                                    child: Hero(
                                      tag: photo['url'] as String,
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: Image.network(
                                          photo['url'] as String,
                                          fit: BoxFit.cover,
                                          errorBuilder: (context, error, stack) => Container(
                                            color: AppColors.obsidian800,
                                            child: const Icon(Icons.broken_image_outlined, color: Colors.white24),
                                          ),
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),
                          ],
                        ],
                      ),
      ),
    );
  }
}
