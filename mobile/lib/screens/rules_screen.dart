import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

/// Кольори бейджів покарань — ті самі рівні, що на сайті (/rules).
const _levelColors = <String, Color>{
  'remark': Color(0xFF7DD3FC),
  'fine': Color(0xFFFCD34D),
  'reprimand': Color(0xFFFB923C),
  'demotion': Color(0xFFE879F9),
  'kick': Color(0xFFF87171),
  'blacklist': Color(0xFFDC2626),
  'jail': Color(0xFFFB923C),
  'ban': Color(0xFFEF4444),
};

/// Правила родини Monsory і правила проєкту Верба Онлайн — той самий
/// вміст, що й розділ «Правила» на сайті (GET /api/rules).
class RulesScreen extends StatefulWidget {
  const RulesScreen({super.key});

  @override
  State<RulesScreen> createState() => _RulesScreenState();
}

class _RulesScreenState extends State<RulesScreen> {
  List<dynamic> _books = [];
  List<dynamic> _levels = [];
  int _bookIndex = 0;
  String _query = '';
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
      final data = await ApiClient.instance.rules();
      if (mounted) {
        setState(() {
          _books = data['books'] as List<dynamic>? ?? [];
          _levels = data['levels'] as List<dynamic>? ?? [];
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити правила.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _visibleSections(Map<String, dynamic> book) {
    final sections = (book['sections'] as List<dynamic>).cast<Map<String, dynamic>>();
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return sections;
    return sections
        .map((s) {
          final rules = (s['rules'] as List<dynamic>).cast<Map<String, dynamic>>().where((r) {
            final haystack = [
              r['code'],
              r['text'],
              ...(r['notes'] as List<dynamic>),
              ...(r['penalties'] as List<dynamic>).map((p) => (p as Map)['text']),
            ].join(' ').toLowerCase();
            return haystack.contains(q);
          }).toList();
          return {...s, 'rules': rules};
        })
        .where((s) => (s['rules'] as List).isNotEmpty)
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final book = _books.isEmpty ? null : _books[_bookIndex.clamp(0, _books.length - 1)] as Map<String, dynamic>;
    final sections = book == null ? <Map<String, dynamic>>[] : _visibleSections(book);

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: const IslandAppBar(title: 'Правила'),
      body: RefreshIndicator(
        onRefresh: _load,
        edgeOffset: islandTopInset(context),
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      Text(_error!, style: const TextStyle(color: Colors.white70)),
                      const SizedBox(height: 12),
                      TextButton(onPressed: _load, child: const Text('Повторити')),
                    ]),
                  )
                : ListView(
                    padding: islandInsets(context, const EdgeInsets.fromLTRB(16, 12, 16, 32)),
                    children: [
                      SizedBox(
                        height: 40,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          itemCount: _books.length,
                          separatorBuilder: (_, __) => const SizedBox(width: 8),
                          itemBuilder: (context, i) {
                            final b = _books[i] as Map<String, dynamic>;
                            final selected = i == _bookIndex;
                            return ChoiceChip(
                              label: Text('${b['icon']} ${b['short']}'),
                              selected: selected,
                              onSelected: (_) => setState(() {
                                _bookIndex = i;
                                _query = '';
                              }),
                            );
                          },
                        ),
                      ),
                      if (book != null) ...[
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: glassPanelDecoration(radius: 18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${book['icon']} ${book['title']}',
                                  style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w500)),
                              const SizedBox(height: 6),
                              Text(book['description'] as String? ?? '',
                                  style: const TextStyle(color: Colors.white54, fontSize: 13, height: 1.35)),
                              const SizedBox(height: 12),
                              TextField(
                                key: ValueKey('search-${book['slug']}'),
                                onChanged: (v) => setState(() => _query = v),
                                decoration: const InputDecoration(
                                  hintText: 'Пошук: номер, слово, покарання…',
                                  prefixIcon: Icon(Icons.search_rounded),
                                  isDense: true,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (book['kind'] == 'family') ...[
                          const SizedBox(height: 12),
                          _LevelsLegend(levels: _levels),
                        ],
                        for (final section in sections) ...[
                          const SizedBox(height: 16),
                          Padding(
                            padding: const EdgeInsets.only(left: 4, bottom: 8),
                            child: Text(section['title'] as String,
                                style: TextStyle(color: AppColors.gold300, fontSize: 15, fontWeight: FontWeight.w600)),
                          ),
                          for (final rule in (section['rules'] as List<dynamic>).cast<Map<String, dynamic>>())
                            _RuleCard(rule: rule),
                        ],
                        if (sections.isEmpty && _query.trim().isNotEmpty)
                          const Padding(
                            padding: EdgeInsets.only(top: 40),
                            child: Center(child: Text('Нічого не знайдено.', style: TextStyle(color: Colors.white38))),
                          ),
                      ],
                    ],
                  ),
      ),
    );
  }
}

class _LevelsLegend extends StatelessWidget {
  final List<dynamic> levels;
  const _LevelsLegend({required this.levels});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: glassPanelDecoration(radius: 18),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          title: const Text('⚖️ Шкала покарань', style: TextStyle(color: Colors.white, fontSize: 15)),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
          children: [
            for (final l in levels.cast<Map<String, dynamic>>())
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _PenaltyChip(text: l['title'] as String, level: l['key'] as String),
                    const SizedBox(height: 4),
                    Text(l['description'] as String,
                        style: const TextStyle(color: Colors.white60, fontSize: 12.5, height: 1.35)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _RuleCard extends StatelessWidget {
  final Map<String, dynamic> rule;
  const _RuleCard({required this.rule});

  @override
  Widget build(BuildContext context) {
    final notes = (rule['notes'] as List<dynamic>).cast<String>();
    final penalties = (rule['penalties'] as List<dynamic>).cast<Map<String, dynamic>>();
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.gold400.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.gold400.withValues(alpha: 0.3)),
                ),
                child: Text(rule['code'] as String,
                    style: TextStyle(color: AppColors.gold300, fontSize: 12, fontFeatures: const [FontFeature.tabularFigures()])),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(rule['text'] as String,
                    style: const TextStyle(color: Colors.white, fontSize: 14, height: 1.4)),
              ),
            ],
          ),
          for (final note in notes)
            Container(
              margin: const EdgeInsets.only(top: 10),
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
              decoration: BoxDecoration(
                color: const Color(0xFFFBBF24).withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(8),
                border: const Border(left: BorderSide(color: Color(0x80FBBF24), width: 2)),
              ),
              child: Text(note, style: const TextStyle(color: Color(0xCCFDE68A), fontSize: 12.5, height: 1.35)),
            ),
          if (penalties.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final p in penalties) _PenaltyChip(text: p['text'] as String, level: p['level'] as String),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _PenaltyChip extends StatelessWidget {
  final String text;
  final String level;
  const _PenaltyChip({required this.text, required this.level});

  @override
  Widget build(BuildContext context) {
    final color = _levelColors[level] ?? _levelColors['jail']!;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Text(text, style: TextStyle(color: color, fontSize: 12, height: 1.25)),
    );
  }
}
