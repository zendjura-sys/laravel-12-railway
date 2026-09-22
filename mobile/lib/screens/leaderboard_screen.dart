import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/shimmer_skeleton.dart';
import '../widgets/island_top_bar.dart';

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> {
  Map<String, dynamic>? _data;
  String _category = 'xp';
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load([String? category]) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.leaderboard(category: category);
      if (mounted) {
        setState(() {
          _data = data;
          _category = data['category'] as String;
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити рейтинг.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = (_data?['categories'] as Map<String, dynamic>?) ?? {};
    final entries = (_data?['leaderboard'] as List<dynamic>?) ?? [];

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: const IslandAppBar(title: 'Рейтинг родини'),
      body: RefreshIndicator(
        onRefresh: () => _load(_category),
        edgeOffset: islandTopInset(context),
        child: _error != null
            ? _ErrorView(message: _error!, onRetry: () => _load(_category))
            : ListView(
                padding: islandInsets(context, const EdgeInsets.all(20)),
                children: [
                  if (categories.isNotEmpty)
                    SizedBox(
                      height: 40,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: categories.entries
                            .map((e) => Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ChoiceChip(
                                    label: Text(e.value as String),
                                    selected: _category == e.key,
                                    onSelected: (_) => _load(e.key),
                                    selectedColor: AppColors.gold400
                                        .withValues(alpha: 0.15),
                                    labelStyle: TextStyle(
                                      color: _category == e.key
                                          ? AppColors.gold200
                                          : Colors.white54,
                                      fontSize: 12,
                                    ),
                                    backgroundColor: AppColors.obsidian800,
                                    side: BorderSide(
                                        color: _category == e.key
                                            ? AppColors.gold400
                                            : Colors.white12),
                                  ),
                                ))
                            .toList(),
                      ),
                    ),
                  const SizedBox(height: 20),
                  if (_loading)
                    const ShimmerListSkeleton()
                  else if (entries.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: Center(
                          child: Text('Поки що порожньо',
                              style: TextStyle(color: Colors.white38))),
                    )
                  else
                    ...entries.asMap().entries.map((e) => _RankTile(
                        rank: e.key + 1,
                        entry: e.value as Map<String, dynamic>,
                        category: _category)),
                ],
              ),
      ),
    );
  }
}

class _RankTile extends StatelessWidget {
  final int rank;
  final Map<String, dynamic> entry;
  final String category;

  const _RankTile(
      {required this.rank, required this.entry, required this.category});

  String _statValue() {
    final fmt = NumberFormat.decimalPattern('uk');
    switch (category) {
      case 'xp':
        return '${entry['xp']} очок';
      case 'bizwar':
        return '${entry['kapt_wins']}–${entry['kapt_losses']}';
      case 'contracts':
        return '${entry['contracts_count']}';
      case 'streak':
        return '${entry['current_streak']}';
      case 'bonuses':
        return '${fmt.format(entry['total_amount'])}₴';
      case 'balance':
        return '${fmt.format(entry['balance'])}₴';
      default:
        return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final topColor = rank == 1
        ? AppColors.gold300
        : rank <= 3
            ? AppColors.gold400.withValues(alpha: 0.6)
            : Colors.white30;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 12),
      child: Row(
        children: [
          SizedBox(
            width: 32,
            child: Text('$rank',
                textAlign: TextAlign.center,
                style: TextStyle(
                    color: topColor,
                    fontSize: 18,
                    fontWeight: FontWeight.w600)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry['name'] as String? ?? '—',
                    style: const TextStyle(color: Colors.white, fontSize: 14)),
                Text(entry['position'] as String? ?? 'без посади',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
              ],
            ),
          ),
          Text(_statValue(),
              style: TextStyle(
                  color: AppColors.gold300,
                  fontWeight: FontWeight.w600,
                  fontSize: 14)),
        ],
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
