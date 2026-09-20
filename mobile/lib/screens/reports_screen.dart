import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import 'report_submit_screen.dart';

const _typeLabels = {
  'kapt': 'Капт',
  'contract': 'Контракт',
  'bizwar': 'Бізвар',
  'investment': 'Інвестиції',
  'other': 'Інше'
};

const _statusMeta = {
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
      appBar: AppBar(title: const Text('Мої звіти')),
      floatingActionButton: FloatingActionButton(
        onPressed: _openSubmit,
        backgroundColor: AppColors.gold400,
        foregroundColor: AppColors.obsidian950,
        child: const Icon(Icons.add),
      ),
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
                        itemBuilder: (context, i) => _ReportTile(
                            report: _reports![i] as Map<String, dynamic>),
                      ),
      ),
    );
  }
}

class _ReportTile extends StatelessWidget {
  final Map<String, dynamic> report;
  const _ReportTile({required this.report});

  @override
  Widget build(BuildContext context) {
    final status = report['status'] as String? ?? 'pending';
    final meta = _statusMeta[status] ?? ('—', Colors.white38);
    final type = report['type'] as String? ?? 'other';
    final createdAt = DateTime.tryParse(report['created_at'] as String? ?? '');

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: glassPanelDecoration(radius: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(_typeLabels[type] ?? type,
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w600)),
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
                    style: const TextStyle(
                        color: AppColors.gold300, fontSize: 12)),
              if ((report['photo_count'] as int? ?? 0) > 0)
                Text('📷 ${report['photo_count']}',
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 12)),
            ],
          ),
          if (createdAt != null) ...[
            const SizedBox(height: 6),
            Text(DateFormat('dd.MM.yyyy HH:mm').format(createdAt),
                style: const TextStyle(color: Colors.white24, fontSize: 11)),
          ],
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
