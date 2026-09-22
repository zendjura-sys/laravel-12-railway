import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

const _categoryMeta = {
  'xp': ('Досвід', Icons.military_tech_outlined, ''),
  'streak': ('Серія перемог', Icons.local_fire_department_outlined, ''),
  'bizwar': ('Перемоги в бізварі', Icons.shield_outlined, ''),
  'veterans': ('Ветерани родини', Icons.emoji_events_outlined, ' дн.'),
  'bonuses': ('Премії за весь час', Icons.payments_outlined, '₴'),
};

const _medals = ['🥇', '🥈', '🥉'];

class HallOfFameScreen extends StatefulWidget {
  const HallOfFameScreen({super.key});

  @override
  State<HallOfFameScreen> createState() => _HallOfFameScreenState();
}

class _HallOfFameScreenState extends State<HallOfFameScreen> {
  Map<String, dynamic> _records = {};
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
      final records = await ApiClient.instance.hallOfFame();
      if (mounted) setState(() => _records = records);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити Зал слави.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat.decimalPattern('uk');
    final categories = _categoryMeta.keys.where((k) => (_records[k] as List?)?.isNotEmpty ?? false).toList();

    return Scaffold(
      appBar: const IslandAppBar(title: 'Зал слави'),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
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
                : categories.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 80),
                          Center(
                            child: Text('Поки що немає рекордів для показу.',
                                style: TextStyle(color: Colors.white38)),
                          ),
                        ],
                      )
                    : ListView(
                        padding: const EdgeInsets.all(20),
                        children: categories.map((key) {
                          final (label, icon, suffix) = _categoryMeta[key]!;
                          final entries = (_records[key] as List).cast<dynamic>();

                          return Container(
                            margin: const EdgeInsets.only(bottom: 14),
                            padding: const EdgeInsets.all(16),
                            decoration: glassPanelDecoration(radius: 16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Icon(icon, color: AppColors.gold300, size: 18),
                                    const SizedBox(width: 8),
                                    Text(label,
                                        style: const TextStyle(
                                            color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                ...entries.asMap().entries.map((entry) {
                                  final i = entry.key;
                                  final row = entry.value as Map<String, dynamic>;
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: Row(
                                      children: [
                                        SizedBox(
                                          width: 28,
                                          child: Text(i < _medals.length ? _medals[i] : '${i + 1}.',
                                              style: const TextStyle(fontSize: 15)),
                                        ),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(row['name'] as String? ?? '—',
                                                  style: const TextStyle(color: Colors.white, fontSize: 13)),
                                              if (row['position'] != null)
                                                Text(row['position'] as String,
                                                    style: const TextStyle(color: Colors.white38, fontSize: 11)),
                                            ],
                                          ),
                                        ),
                                        Text('${fmt.format(row['value'])}$suffix',
                                            style: TextStyle(
                                                color: AppColors.gold300,
                                                fontSize: 14,
                                                fontWeight: FontWeight.w600)),
                                      ],
                                    ),
                                  );
                                }),
                              ],
                            ),
                          );
                        }).toList(),
                      ),
      ),
    );
  }
}
