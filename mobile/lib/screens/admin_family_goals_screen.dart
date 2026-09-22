import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

class AdminFamilyGoalsScreen extends StatefulWidget {
  const AdminFamilyGoalsScreen({super.key});

  @override
  State<AdminFamilyGoalsScreen> createState() => _AdminFamilyGoalsScreenState();
}

class _AdminFamilyGoalsScreenState extends State<AdminFamilyGoalsScreen> {
  List<dynamic> _goals = [];
  Map<String, dynamic> _metrics = {};
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
      final data = await ApiClient.instance.adminFamilyGoals();
      if (mounted) {
        setState(() {
          _goals = data['goals'] as List<dynamic>;
          _metrics = data['metrics'] as Map<String, dynamic>;
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

  Future<void> _updateProgress(Map<String, dynamic> goal) async {
    final controller = TextEditingController(text: '${goal['currentValue']}');
    final value = await showDialog<int>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Прогрес: ${goal['title']}'),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
              labelText: 'Поточне значення${goal['unit'] != null ? ' (${goal['unit']})' : ''}'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Скасувати')),
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(int.tryParse(controller.text.trim())),
              child: const Text('Зберегти')),
        ],
      ),
    );
    if (value == null) return;

    try {
      await ApiClient.instance.adminUpdateGoalProgress(goal['id'] as int, value);
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _close(Map<String, dynamic> goal) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Закрити ціль?'),
        content: Text('«${goal['title']}» позначиться як не досягнута, без можливості відновити.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Закрити', style: TextStyle(color: Color(0xFFF87171)))),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await ApiClient.instance.adminCloseFamilyGoal(goal['id'] as int);
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openCreateSheet() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CreateGoalSheet(metrics: _metrics),
    );
    if (created == true) _load();
  }

  String _statusLabel(String status) => switch (status) {
        'completed' => 'Досягнута',
        'failed' => 'Закрита',
        _ => 'Активна',
      };

  Color _statusColor(String status) => switch (status) {
        'completed' => const Color(0xFF6EE7B7),
        'failed' => const Color(0xFFF87171),
        _ => AppColors.gold300,
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const IslandAppBar(title: 'Цілі родини'),
      floatingActionButton: FloatingActionButton(
        onPressed: _openCreateSheet,
        backgroundColor: AppColors.gold400,
        foregroundColor: AppColors.obsidian950,
        child: const Icon(Icons.add),
      ),
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
                : _goals.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 80),
                          Center(
                            child: Text('Цілей ще немає — створіть першу кнопкою "+".',
                                style: TextStyle(color: Colors.white38)),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(20, 20, 20, 90),
                        itemCount: _goals.length,
                        itemBuilder: (context, i) {
                          final goal = _goals[i] as Map<String, dynamic>;
                          final status = goal['status'] as String;
                          final percent = (goal['progressPercent'] as int?) ?? 0;
                          final isManual = goal['metric'] == null;

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: glassPanelDecoration(radius: 16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(goal['title'] as String,
                                          style: const TextStyle(
                                              color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: _statusColor(status).withValues(alpha: 0.12),
                                        borderRadius: BorderRadius.circular(999),
                                      ),
                                      child: Text(_statusLabel(status),
                                          style: TextStyle(color: _statusColor(status), fontSize: 11)),
                                    ),
                                  ],
                                ),
                                if (goal['description'] != null) ...[
                                  const SizedBox(height: 6),
                                  Text(goal['description'] as String,
                                      style: const TextStyle(color: Colors.white70, fontSize: 13)),
                                ],
                                const SizedBox(height: 10),
                                if (goal['targetValue'] != null) ...[
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(999),
                                    child: LinearProgressIndicator(
                                      value: percent / 100,
                                      minHeight: 8,
                                      backgroundColor: Colors.white.withValues(alpha: 0.08),
                                      valueColor: AlwaysStoppedAnimation(_statusColor(status)),
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    '${goal['currentValue']}${goal['unit'] != null ? ' ${goal['unit']}' : ''} з ${goal['targetValue']}${goal['unit'] != null ? ' ${goal['unit']}' : ''} · $percent%',
                                    style: const TextStyle(color: Colors.white38, fontSize: 12),
                                  ),
                                ],
                                const SizedBox(height: 4),
                                Text(
                                  isManual
                                      ? 'Прогрес вручну · створив: ${goal['creatorName'] ?? '—'}'
                                      : 'Автопрогрес: ${_metrics[goal['metric']] ?? goal['metric']} · створив: ${goal['creatorName'] ?? '—'}',
                                  style: const TextStyle(color: Colors.white24, fontSize: 11),
                                ),
                                if (status == 'active') ...[
                                  const SizedBox(height: 10),
                                  Row(
                                    children: [
                                      if (isManual)
                                        TextButton(
                                          onPressed: () => _updateProgress(goal),
                                          child: const Text('Оновити прогрес'),
                                        ),
                                      const Spacer(),
                                      TextButton(
                                        onPressed: () => _close(goal),
                                        style: TextButton.styleFrom(foregroundColor: const Color(0xFFF87171)),
                                        child: const Text('Закрити'),
                                      ),
                                    ],
                                  ),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}

class _CreateGoalSheet extends StatefulWidget {
  final Map<String, dynamic> metrics;

  const _CreateGoalSheet({required this.metrics});

  @override
  State<_CreateGoalSheet> createState() => _CreateGoalSheetState();
}

class _CreateGoalSheetState extends State<_CreateGoalSheet> {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _targetController = TextEditingController();
  final _unitController = TextEditingController();
  String? _metric;
  DateTime? _deadline;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _targetController.dispose();
    _unitController.dispose();
    super.dispose();
  }

  Future<void> _pickDeadline() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _deadline ?? DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365 * 2)),
    );
    if (picked != null) setState(() => _deadline = picked);
  }

  Future<void> _save() async {
    if (_titleController.text.trim().isEmpty) return;

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ApiClient.instance.adminCreateFamilyGoal(
        title: _titleController.text.trim(),
        description: _descriptionController.text.trim(),
        targetValue: int.tryParse(_targetController.text.trim()),
        unit: _unitController.text.trim(),
        metric: _metric,
        deadline: _deadline != null ? DateFormat('yyyy-MM-dd').format(_deadline!) : null,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.all(20),
        decoration: glassPanelDecoration(radius: 20),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Нова ціль',
                  style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w500)),
              const SizedBox(height: 16),
              TextField(
                controller: _titleController,
                decoration: const InputDecoration(labelText: 'Назва'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _descriptionController,
                decoration: const InputDecoration(labelText: 'Опис (необовʼязково)'),
                maxLines: 3,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _targetController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Ціль (число, необовʼязково)'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _unitController,
                      decoration: const InputDecoration(labelText: 'Одиниці'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Text('Прогрес', style: TextStyle(color: Colors.white38, fontSize: 12)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ChoiceChip(
                    label: const Text('Вручну'),
                    selected: _metric == null,
                    onSelected: (_) => setState(() => _metric = null),
                  ),
                  ...widget.metrics.entries.map((e) => ChoiceChip(
                        label: Text(e.value as String),
                        selected: _metric == e.key,
                        onSelected: (_) => setState(() => _metric = e.key),
                      )),
                ],
              ),
              const SizedBox(height: 12),
              InkWell(
                onTap: _pickDeadline,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                  decoration: BoxDecoration(
                    color: AppColors.obsidian800,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.event_outlined, color: Colors.white38, size: 20),
                      const SizedBox(width: 10),
                      Text(
                        _deadline == null
                            ? 'Дедлайн (необовʼязково)'
                            : DateFormat('dd.MM.yyyy').format(_deadline!),
                        style: TextStyle(color: _deadline == null ? Colors.white38 : Colors.white),
                      ),
                    ],
                  ),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                Text(_error!, style: const TextStyle(color: Color(0xFFF87171), fontSize: 13)),
              ],
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _saving || _titleController.text.trim().isEmpty ? null : _save,
                child: Text(_saving ? 'Створюю…' : 'Створити ціль'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}
