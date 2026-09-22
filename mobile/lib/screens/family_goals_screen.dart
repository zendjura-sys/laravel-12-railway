import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

class FamilyGoalsScreen extends StatefulWidget {
  const FamilyGoalsScreen({super.key});

  @override
  State<FamilyGoalsScreen> createState() => _FamilyGoalsScreenState();
}

class _FamilyGoalsScreenState extends State<FamilyGoalsScreen> {
  List<dynamic> _goals = [];
  List<dynamic> _activity = [];
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
      final data = await ApiClient.instance.familyGoals();
      if (mounted) {
        setState(() {
          _goals = data['goals'] as List<dynamic>;
          _activity = data['activity'] as List<dynamic>;
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити цілі.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: const IslandAppBar(title: 'Цілі родини'),
      body: RefreshIndicator(
        onRefresh: _load,
        edgeOffset: islandTopInset(context),
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
                : ListView(
                    padding: islandInsets(context, const EdgeInsets.all(20)),
                    children: [
                      if (_goals.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 40),
                          child: Center(
                            child: Text('Активних цілей поки немає.',
                                style: TextStyle(color: Colors.white38)),
                          ),
                        )
                      else
                        ..._goals.map((g) => _GoalCard(goal: g as Map<String, dynamic>)),
                      if (_activity.isNotEmpty) ...[
                        const SizedBox(height: 20),
                        const Text('Стрічка активності',
                            style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 10),
                        ..._activity.map((a) => _ActivityTile(event: a as Map<String, dynamic>)),
                      ],
                    ],
                  ),
      ),
    );
  }
}

class _GoalCard extends StatelessWidget {
  final Map<String, dynamic> goal;

  const _GoalCard({required this.goal});

  @override
  Widget build(BuildContext context) {
    final completed = goal['status'] == 'completed';
    final percent = (goal['progressPercent'] as int?) ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: glassPanelDecoration(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (completed) ...[
                const Text('🎉', style: TextStyle(fontSize: 16)),
                const SizedBox(width: 6),
              ],
              Expanded(
                child: Text(goal['title'] as String,
                    style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
              ),
            ],
          ),
          if (goal['description'] != null) ...[
            const SizedBox(height: 6),
            Text(goal['description'] as String,
                style: const TextStyle(color: Colors.white70, fontSize: 13)),
          ],
          const SizedBox(height: 12),
          if (goal['targetValue'] != null) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: percent / 100,
                minHeight: 8,
                backgroundColor: Colors.white.withValues(alpha: 0.08),
                valueColor: AlwaysStoppedAnimation(completed ? const Color(0xFF6EE7B7) : AppColors.gold400),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${goal['currentValue']}${goal['unit'] != null ? ' ${goal['unit']}' : ''} з ${goal['targetValue']}${goal['unit'] != null ? ' ${goal['unit']}' : ''} · $percent%',
              style: const TextStyle(color: Colors.white38, fontSize: 12),
            ),
          ] else
            Text('Прогрес: ${goal['currentValue']}${goal['unit'] != null ? ' ${goal['unit']}' : ''}',
                style: TextStyle(color: AppColors.gold300, fontSize: 13)),
        ],
      ),
    );
  }
}

class _ActivityTile extends StatelessWidget {
  final Map<String, dynamic> event;

  const _ActivityTile({required this.event});

  @override
  Widget build(BuildContext context) {
    final createdAt = DateTime.parse(event['createdAt'] as String).toLocal();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(top: 6, right: 10),
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: AppColors.gold400.withValues(alpha: 0.6), shape: BoxShape.circle),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(event['message'] as String,
                    style: const TextStyle(color: Colors.white70, fontSize: 13)),
                Text(DateFormat('dd.MM.yyyy HH:mm').format(createdAt),
                    style: const TextStyle(color: Colors.white24, fontSize: 11)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
