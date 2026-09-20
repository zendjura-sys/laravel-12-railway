import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';

const _grades = ['S', 'A', 'B', 'C', 'D', 'F', 'G'];
const _lowGrades = ['D', 'F', 'G'];

const _typeLabels = {
  'kapt': 'Капт',
  'contract': 'Контракт',
  'bizwar': 'Бізвар',
  'investment': 'Інвестиції',
  'other': 'Інше',
};

class AdminReportsScreen extends StatefulWidget {
  const AdminReportsScreen({super.key});

  @override
  State<AdminReportsScreen> createState() => _AdminReportsScreenState();
}

class _AdminReportsScreenState extends State<AdminReportsScreen> {
  List<dynamic> _reports = [];
  bool _loading = true;
  String? _error;
  int? _busyId;

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
      final reports = await ApiClient.instance.adminPendingReports();
      if (mounted) setState(() => _reports = reports);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити звіти.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _approve(Map<String, dynamic> report) async {
    final result = await showModalBottomSheet<(String, String?)>(
      context: context,
      backgroundColor: AppColors.obsidian900,
      builder: (_) => const _GradeSheet(),
    );
    if (result == null) return;

    setState(() => _busyId = report['id'] as int);
    try {
      await ApiClient.instance.adminApproveReport(report['id'] as int, grade: result.$1, gradeReason: result.$2);
      setState(() => _reports = _reports.where((r) => r['id'] != report['id']).toList());
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _reject(Map<String, dynamic> report) async {
    final noteController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.obsidian900,
        title: const Text('Відхилити звіт?', style: TextStyle(color: Colors.white)),
        content: TextField(
          controller: noteController,
          decoration: const InputDecoration(hintText: 'Причина (необовʼязково)'),
          maxLines: 3,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Відхилити')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _busyId = report['id'] as int);
    try {
      await ApiClient.instance.adminRejectReport(report['id'] as int, note: noteController.text.trim());
      setState(() => _reports = _reports.where((r) => r['id'] != report['id']).toList());
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Звіти на розгляді')),
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
                : _reports.isEmpty
                    ? const Center(
                        child: Text('Немає звітів на розгляді.', style: TextStyle(color: Colors.white38)))
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _reports.length,
                        itemBuilder: (context, i) {
                          final r = _reports[i] as Map<String, dynamic>;
                          final busy = _busyId == r['id'];
                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(14),
                            decoration: glassPanelDecoration(radius: 14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        '${_typeLabels[r['type']] ?? r['type']} · ${r['userName'] ?? ''}',
                                        style: const TextStyle(
                                            color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500),
                                      ),
                                    ),
                                    Text(r['reportDate'] as String? ?? '',
                                        style: const TextStyle(color: Colors.white38, fontSize: 12)),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(_reportSummary(r), style: const TextStyle(color: Colors.white54, fontSize: 13)),
                                if (r['description'] != null) ...[
                                  const SizedBox(height: 4),
                                  Text(r['description'] as String,
                                      style: const TextStyle(color: Colors.white38, fontSize: 12)),
                                ],
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        onPressed: busy ? null : () => _reject(r),
                                        child: const Text('Відхилити'),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: ElevatedButton(
                                        onPressed: busy ? null : () => _approve(r),
                                        child: busy
                                            ? const SizedBox(
                                                width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                            : const Text('Затвердити'),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                      ),
      ),
    );
  }

  String _reportSummary(Map<String, dynamic> r) {
    final parts = <String>[];
    if (r['winsCount'] != null || r['lossesCount'] != null) {
      parts.add('${r['winsCount'] ?? 0}W / ${r['lossesCount'] ?? 0}L');
    }
    if (r['amount'] != null) parts.add('${r['amount']} ₴');
    if (r['photoCount'] != null && (r['photoCount'] as int) > 0) parts.add('📷 ${r['photoCount']}');
    return parts.isEmpty ? '—' : parts.join(' · ');
  }
}

class _GradeSheet extends StatefulWidget {
  const _GradeSheet();

  @override
  State<_GradeSheet> createState() => _GradeSheetState();
}

class _GradeSheetState extends State<_GradeSheet> {
  String? _selected;
  final _reasonController = TextEditingController();

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final needsReason = _selected != null && _lowGrades.contains(_selected);

    return Padding(
      padding: EdgeInsets.only(
          left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Оцінка звіту', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _grades.map((g) {
              final selected = _selected == g;
              return ChoiceChip(
                label: Text(g),
                selected: selected,
                onSelected: (_) => setState(() => _selected = g),
              );
            }).toList(),
          ),
          if (needsReason) ...[
            const SizedBox(height: 14),
            TextField(
              controller: _reasonController,
              decoration: const InputDecoration(hintText: 'Причина низької оцінки (обовʼязково)'),
              maxLines: 2,
              onChanged: (_) => setState(() {}),
            ),
          ],
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: _selected == null || (needsReason && _reasonController.text.trim().isEmpty)
                ? null
                : () => Navigator.of(context).pop((_selected!, _reasonController.text.trim())),
            child: const Text('Затвердити'),
          ),
        ],
      ),
    );
  }
}
