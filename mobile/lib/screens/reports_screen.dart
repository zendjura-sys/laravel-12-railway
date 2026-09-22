import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/photo_thumbnails.dart';
import 'report_submit_screen.dart';
import '../widgets/island_top_bar.dart';
import '../widgets/selection_delete_bar.dart';

const _typeLabels = {
  'kapt': 'Капт',
  'contract': 'Контракт',
  'bizwar': 'Бізвар',
  'investment': 'Інвестиції',
  'other': 'Інше'
};

final _statusMeta = {
  'pending': ('На розгляді', AppColors.gold300),
  'approved': (
    'Затверджено',
    Color(0xFF6EE7B7),
  ),
  'rejected': (
    'Відхилено',
    Color(0xFFF87171),
  ),
};

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  List<dynamic>? _reports;
  bool _loading = true;
  String? _error;
  // Режим вибору (кнопка 🗑 у шапці). Затверджені звіти вибрати не можна:
  // за них уже нараховано досвід і премії — сервер їх теж не видаляє.
  bool _selecting = false;
  final Set<int> _selected = {};
  bool _deleting = false;

  static bool _deletable(Map<String, dynamic> r) => r['status'] != 'approved';

  List<Map<String, dynamic>> get _deletableReports =>
      (_reports ?? []).cast<Map<String, dynamic>>().where(_deletable).toList();

  void _toggleSelecting() => setState(() {
        _selecting = !_selecting;
        _selected.clear();
      });

  void _toggle(Map<String, dynamic> r) {
    if (!_deletable(r)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Затверджений звіт видалити не можна — за нього вже нараховано досвід і премії.')));
      return;
    }
    final id = r['id'] as int;
    setState(() => _selected.contains(id) ? _selected.remove(id) : _selected.add(id));
  }

  Future<void> _deleteSelected() async {
    final ids = _selected.toList();
    if (!await confirmDeletion(context,
        title: 'Видалити звіти?', message: 'Буде видалено вибрані звіти (${ids.length}) разом із фото.')) {
      return;
    }
    await _bulkDelete(() => ApiClient.instance.bulkDeleteReports(ids: ids));
  }

  Future<void> _deleteAll() async {
    final count = _deletableReports.length;
    if (count == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Немає звітів, які можна видалити — затверджені лишаються.')));
      return;
    }
    if (!await confirmDeletion(context,
        title: 'Видалити всі звіти?',
        message: 'Буде видалено всі звіти на розгляді й відхилені ($count). Затверджені лишаються.')) {
      return;
    }
    await _bulkDelete(() => ApiClient.instance.bulkDeleteReports(all: true));
  }

  Future<void> _bulkDelete(Future<({int deleted, int skipped, String? message})> Function() request) async {
    setState(() => _deleting = true);
    try {
      final result = await request();
      if (!mounted) return;
      setState(() {
        _selecting = false;
        _selected.clear();
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(result.message ?? 'Видалено звітів: ${result.deleted}.')));
      await _load();
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
      final reports = await ApiClient.instance.reports();
      if (mounted) setState(() => _reports = reports);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити звіти.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openSubmit() async {
    final ok = await Navigator.of(context).push<bool>(
        MaterialPageRoute(builder: (_) => const ReportSubmitScreen()));
    if (ok == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: IslandAppBar(
        title: _selecting ? 'Вибрано: ${_selected.length}' : 'Мої звіти',
        actions: [
          if ((_reports?.isNotEmpty ?? false) || _selecting)
            IslandCircleButton(
              icon: Icon(_selecting ? Icons.close_rounded : Icons.delete_outline_rounded),
              tooltip: _selecting ? 'Скасувати' : 'Видалити',
              onTap: _toggleSelecting,
            ),
        ],
      ),
      floatingActionButton: _selecting
          ? null
          : FloatingActionButton(
              onPressed: _openSubmit,
              backgroundColor: AppColors.gold400,
              foregroundColor: AppColors.obsidian950,
              child: const Icon(Icons.add),
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
                ? _ErrorView(message: _error!, onRetry: _load)
                : (_reports?.isEmpty ?? true)
                    ? ListView(
                        children: const [
                          Padding(
                            padding: EdgeInsets.only(top: 80),
                            child: Center(
                                child: Text('Звітів ще немає',
                                    style: TextStyle(color: Colors.white38))),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(20),
                        itemCount: _reports!.length,
                        itemBuilder: (context, i) {
                          final r = _reports![i] as Map<String, dynamic>;
                          return _ReportTile(
                            report: r,
                            selecting: _selecting,
                            selected: _selected.contains(r['id']),
                            selectable: _deletable(r),
                            onTap: _selecting ? () => _toggle(r) : null,
                            onLongPress: _selecting || !_deletable(r)
                                ? null
                                : () => setState(() {
                                      _selecting = true;
                                      _selected
                                        ..clear()
                                        ..add(r['id'] as int);
                                    }),
                          );
                        },
                      ),
      ),
    );
  }
}

class _ReportTile extends StatelessWidget {
  final Map<String, dynamic> report;
  final bool selecting;
  final bool selected;
  final bool selectable;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;

  const _ReportTile({
    required this.report,
    this.selecting = false,
    this.selected = false,
    this.selectable = true,
    this.onTap,
    this.onLongPress,
  });

  @override
  Widget build(BuildContext context) {
    final status = report['status'] as String? ?? 'pending';
    final meta = _statusMeta[status] ?? ('—', Colors.white38);
    final type = report['type'] as String? ?? 'other';
    final createdAt = DateTime.tryParse(report['created_at'] as String? ?? '');
    final photos = (report['photos'] as List?)?.cast<String>() ?? [];

    final tile = Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: glassPanelDecoration(radius: 14).copyWith(
        border: selected ? Border.all(color: const Color(0xFFF87171).withValues(alpha: 0.6)) : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (selecting) ...[
                SelectionMark(selected: selected, enabled: selectable),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Text(_typeLabels[type] ?? type,
                    style: const TextStyle(
                        color: Colors.white, fontWeight: FontWeight.w600)),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: meta.$2.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: meta.$2.withValues(alpha: 0.4)),
                ),
                child: Text(meta.$1,
                    style: TextStyle(color: meta.$2, fontSize: 11)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 12,
            children: [
              if (report['wins_count'] != null)
                Text('W: ${report['wins_count']}',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
              if (report['losses_count'] != null)
                Text('L: ${report['losses_count']}',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
              if (report['light_count'] != null)
                Text(
                    'л:${report['light_count']} с:${report['medium_count']} т:${report['heavy_count']}',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
              if (report['amount'] != null)
                Text(
                    '${NumberFormat.decimalPattern('uk').format(report['amount'])}₴',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
              if (report['grade'] != null)
                Text('Оцінка: ${report['grade']}',
                    style: TextStyle(
                        color: AppColors.gold300, fontSize: 12)),
            ],
          ),
          if (photos.isNotEmpty) ...[
            const SizedBox(height: 10),
            PhotoThumbnails(photos: photos),
          ],
          if (createdAt != null) ...[
            const SizedBox(height: 6),
            Text(DateFormat('dd.MM.yyyy HH:mm').format(createdAt),
                style: const TextStyle(color: Colors.white24, fontSize: 11)),
          ],
        ],
      ),
    );

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      onLongPress: onLongPress,
      child: AnimatedOpacity(
        duration: const Duration(milliseconds: 180),
        opacity: selecting && !selectable ? 0.45 : 1,
        child: tile,
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message,
              style: const TextStyle(color: Colors.white70),
              textAlign: TextAlign.center),
          const SizedBox(height: 12),
          TextButton(onPressed: onRetry, child: const Text('Повторити')),
        ],
      ),
    );
  }
}
