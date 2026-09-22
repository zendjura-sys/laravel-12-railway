import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

final _fmt = NumberFormat.decimalPattern('uk');
String _money(dynamic v) => '${_fmt.format(v ?? 0)}₴';

/// Повний контроль премій/бонусів у мобільній адмінці — той самий набір
/// дій, що вже є на сайті (Admin\BonusAdminController): заявки на
/// готівку, ручні нарахування, перекази, тижневі виплати й ручний
/// запуск розрахунку, тіри інвестицій, налаштування ставок і банку.
/// Один екран з вкладками замість шести окремих — усе разом складає
/// один логічний розділ "Премії", а не шість різних задач.
class AdminBonusesScreen extends StatefulWidget {
  const AdminBonusesScreen({super.key});

  @override
  State<AdminBonusesScreen> createState() => _AdminBonusesScreenState();
}

class _AdminBonusesScreenState extends State<AdminBonusesScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 6, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.adminBonusesOverview();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити дані.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: IslandAppBar(
        title: 'Премії',
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Готівка'),
            Tab(text: 'Нарахування'),
            Tab(text: 'Перекази'),
            Tab(text: 'Виплати'),
            Tab(text: 'Тіри'),
            Tab(text: 'Налаштування'),
          ],
        ),
      ),
      body: _loading
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
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _CashRequestsTab(items: _data!['cashRequests'] as List<dynamic>, onChanged: _load),
                    _ManualAwardsTab(items: _data!['manualAwards'] as List<dynamic>, onChanged: _load),
                    _TransfersTab(items: _data!['transfers'] as List<dynamic>, onChanged: _load),
                    _PayoutsTab(items: _data!['payouts'] as List<dynamic>, onChanged: _load),
                    _TiersTab(items: _data!['tiers'] as List<dynamic>, onChanged: _load),
                    _SettingsTab(settings: _data!['settings'] as Map<String, dynamic>, onChanged: _load),
                  ],
                ),
    );
  }
}

class _EmptyHint extends StatelessWidget {
  final String text;
  const _EmptyHint(this.text);

  @override
  Widget build(BuildContext context) => Center(child: Text(text, style: const TextStyle(color: Colors.white38)));
}

// ---------------- Заявки на готівку ----------------

class _CashRequestsTab extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onChanged;
  const _CashRequestsTab({required this.items, required this.onChanged});

  @override
  State<_CashRequestsTab> createState() => _CashRequestsTabState();
}

class _CashRequestsTabState extends State<_CashRequestsTab> {
  int? _busyId;

  Future<void> _act(int id, bool complete) async {
    setState(() => _busyId = id);
    try {
      if (complete) {
        await ApiClient.instance.adminCompleteCashRequest(id);
      } else {
        await ApiClient.instance.adminCancelCashRequest(id);
      }
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.items.isEmpty) return const _EmptyHint('Заявок на готівку немає.');
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: widget.items.length,
      itemBuilder: (context, i) {
        final r = widget.items[i] as Map<String, dynamic>;
        final pending = r['status'] == 'pending';
        final busy = _busyId == r['id'];
        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: glassPanelDecoration(radius: 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text((r['user'] as Map<String, dynamic>?)?['name'] as String? ?? '—',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                  Text(_money(r['amount']), style: TextStyle(color: AppColors.gold300, fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                switch (r['status']) {
                  'completed' => 'Видано',
                  'cancelled' => 'Скасовано',
                  _ => 'Очікує',
                },
                style: TextStyle(
                    color: pending ? AppColors.gold300.withValues(alpha: 0.7) : Colors.white38, fontSize: 12),
              ),
              if (pending) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: busy ? null : () => _act(r['id'] as int, false),
                        child: const Text('Скасувати'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: busy ? null : () => _act(r['id'] as int, true),
                        child: busy
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('Видати'),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

// ---------------- Ручні нарахування ----------------

class _ManualAwardsTab extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onChanged;
  const _ManualAwardsTab({required this.items, required this.onChanged});

  @override
  State<_ManualAwardsTab> createState() => _ManualAwardsTabState();
}

class _ManualAwardsTabState extends State<_ManualAwardsTab> {
  Future<void> _openForm() async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.obsidian900,
      builder: (_) => const _ManualAwardSheet(),
    );
    if (result == true) widget.onChanged();
  }

  Future<void> _delete(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Видалити нарахування?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Видалити')),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ApiClient.instance.adminDeleteManualAward(id);
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.items.isEmpty
            ? const _EmptyHint('Ручних нарахувань ще немає.')
            : ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
                itemCount: widget.items.length,
                itemBuilder: (context, i) {
                  final a = widget.items[i] as Map<String, dynamic>;
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: glassPanelDecoration(radius: 14),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text((a['user'] as Map<String, dynamic>?)?['name'] as String? ?? '—',
                                  style: const TextStyle(color: Colors.white, fontSize: 14)),
                              if ((a['note'] as String?)?.isNotEmpty == true) ...[
                                const SizedBox(height: 2),
                                Text(a['note'] as String, style: const TextStyle(color: Colors.white54, fontSize: 12)),
                              ],
                              const SizedBox(height: 2),
                              Text('видав(-ла) ${(a['awardedBy'] as Map<String, dynamic>?)?['name'] ?? '—'}',
                                  style: const TextStyle(color: Colors.white24, fontSize: 11)),
                            ],
                          ),
                        ),
                        Text(_money(a['amount']),
                            style: TextStyle(color: AppColors.gold300, fontWeight: FontWeight.w600)),
                        IconButton(
                          icon: const Icon(Icons.delete_outline, color: Colors.white38, size: 20),
                          onPressed: () => _delete(a['id'] as int),
                        ),
                      ],
                    ),
                  );
                },
              ),
        Positioned(
          right: 16,
          bottom: 16,
          child: FloatingActionButton.extended(
            onPressed: _openForm,
            icon: const Icon(Icons.add),
            label: const Text('Нарахувати'),
          ),
        ),
      ],
    );
  }
}

class _ManualAwardSheet extends StatefulWidget {
  const _ManualAwardSheet();

  @override
  State<_ManualAwardSheet> createState() => _ManualAwardSheetState();
}

class _ManualAwardSheetState extends State<_ManualAwardSheet> {
  final _searchController = TextEditingController();
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();
  List<dynamic> _matches = [];
  Map<String, dynamic>? _selected;
  Timer? _debounce;
  bool _saving = false;

  void _onSearchChanged(String q) {
    _debounce?.cancel();
    setState(() => _selected = null);
    if (q.trim().length < 2) {
      setState(() => _matches = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final matches = await ApiClient.instance.adminBonusesSearchMembers(q);
      if (mounted) setState(() => _matches = matches);
    });
  }

  Future<void> _submit() async {
    final amount = int.tryParse(_amountController.text.trim());
    if (_selected == null || amount == null || amount < 1) return;
    setState(() => _saving = true);
    try {
      await ApiClient.instance.adminStoreManualAward(_selected!['id'] as int, amount, _noteController.text.trim());
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _amountController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Ручне нарахування', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
          const SizedBox(height: 12),
          if (_selected != null)
            Chip(
              label: Text(_selected!['name'] as String),
              onDeleted: () => setState(() {
                _selected = null;
                _searchController.clear();
              }),
            )
          else ...[
            TextField(
              controller: _searchController,
              autofocus: true,
              decoration: const InputDecoration(hintText: "Ім'я учасника…"),
              onChanged: _onSearchChanged,
            ),
            if (_matches.isNotEmpty)
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 200),
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _matches.length,
                  itemBuilder: (context, i) {
                    final m = _matches[i] as Map<String, dynamic>;
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(m['name'] as String, style: const TextStyle(color: Colors.white)),
                      onTap: () => setState(() {
                        _selected = m;
                        _matches = [];
                      }),
                    );
                  },
                ),
              ),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Сума, ₴'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _noteController,
            decoration: const InputDecoration(labelText: 'Коментар (необов\'язково)'),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _submit,
              child: _saving
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Нарахувати'),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------- Перекази ----------------

class _TransfersTab extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onChanged;
  const _TransfersTab({required this.items, required this.onChanged});

  @override
  State<_TransfersTab> createState() => _TransfersTabState();
}

class _TransfersTabState extends State<_TransfersTab> {
  int? _busyId;

  Future<void> _reverse(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Скасувати переказ?'),
        content: const Text('Кошти повернуться на баланс відправника.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Ні')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Скасувати переказ')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _busyId = id);
    try {
      await ApiClient.instance.adminReverseTransfer(id);
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.items.isEmpty) return const _EmptyHint('Переказів ще немає.');
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: widget.items.length,
      itemBuilder: (context, i) {
        final t = widget.items[i] as Map<String, dynamic>;
        final reversed = t['reversed_at'] != null;
        final busy = _busyId == t['id'];
        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: glassPanelDecoration(radius: 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      '${(t['sender'] as Map<String, dynamic>?)?['name'] ?? '—'} → ${(t['recipient'] as Map<String, dynamic>?)?['name'] ?? '—'}',
                      style: const TextStyle(color: Colors.white, fontSize: 14),
                    ),
                  ),
                  Text(_money(t['amount']),
                      style: TextStyle(
                          color: reversed ? Colors.white38 : AppColors.gold300,
                          fontWeight: FontWeight.w600,
                          decoration: reversed ? TextDecoration.lineThrough : null)),
                ],
              ),
              if ((t['note'] as String?)?.isNotEmpty == true) ...[
                const SizedBox(height: 4),
                Text(t['note'] as String, style: const TextStyle(color: Colors.white54, fontSize: 12)),
              ],
              if (reversed) ...[
                const SizedBox(height: 6),
                Text('Скасовано (${(t['reversedBy'] as Map<String, dynamic>?)?['name'] ?? '—'})',
                    style: const TextStyle(color: Colors.redAccent, fontSize: 11)),
              ] else ...[
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerRight,
                  child: OutlinedButton(
                    onPressed: busy ? null : () => _reverse(t['id'] as int),
                    child: busy
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Скасувати переказ'),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

// ---------------- Виплати ----------------

class _PayoutsTab extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onChanged;
  const _PayoutsTab({required this.items, required this.onChanged});

  @override
  State<_PayoutsTab> createState() => _PayoutsTabState();
}

class _PayoutsTabState extends State<_PayoutsTab> {
  int? _busyId;
  bool _running = false;

  Future<void> _markPaid(int id) async {
    setState(() => _busyId = id);
    try {
      await ApiClient.instance.adminMarkPayoutPaid(id);
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _runNow() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Запустити розрахунок зараз?'),
        content: const Text('Порахує премії за поточний тиждень і надішле дайджест у сімейний чат — та сама дія, що й за розкладом.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Запустити')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _running = true);
    try {
      await ApiClient.instance.adminRunBonusesNow();
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _running = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
          child: SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _running ? null : _runNow,
              icon: _running
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.play_arrow),
              label: const Text('Запустити розрахунок зараз'),
            ),
          ),
        ),
        Expanded(
          child: widget.items.isEmpty
              ? const _EmptyHint('Виплат ще немає.')
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: widget.items.length,
                  itemBuilder: (context, i) {
                    final p = widget.items[i] as Map<String, dynamic>;
                    final paid = p['paid'] == true;
                    final busy = _busyId == p['id'];
                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: glassPanelDecoration(radius: 14),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text((p['user'] as Map<String, dynamic>?)?['name'] as String? ?? '—',
                                    style: const TextStyle(color: Colors.white, fontSize: 14)),
                                const SizedBox(height: 2),
                                Text('тиждень від ${p['week_start'] ?? '—'}',
                                    style: const TextStyle(color: Colors.white38, fontSize: 11)),
                              ],
                            ),
                          ),
                          Text(_money(p['total_amount']),
                              style: TextStyle(color: AppColors.gold300, fontWeight: FontWeight.w600)),
                          const SizedBox(width: 10),
                          paid
                              ? const Icon(Icons.check_circle, color: Colors.greenAccent, size: 20)
                              : SizedBox(
                                  height: 32,
                                  child: OutlinedButton(
                                    onPressed: busy ? null : () => _markPaid(p['id'] as int),
                                    child: busy
                                        ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
                                        : const Text('Оплачено', style: TextStyle(fontSize: 12)),
                                  ),
                                ),
                        ],
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

// ---------------- Тіри інвестицій ----------------

class _TiersTab extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onChanged;
  const _TiersTab({required this.items, required this.onChanged});

  @override
  State<_TiersTab> createState() => _TiersTabState();
}

class _TiersTabState extends State<_TiersTab> {
  Future<void> _add() async {
    final result = await showDialog<bool>(
      context: context,
      builder: (_) => const _TierDialog(),
    );
    if (result == true) widget.onChanged();
  }

  Future<void> _delete(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Видалити тір?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Видалити')),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ApiClient.instance.adminDeleteTier(id);
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.items.isEmpty
            ? const _EmptyHint('Тірів інвестицій ще немає.')
            : ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
                itemCount: widget.items.length,
                itemBuilder: (context, i) {
                  final t = widget.items[i] as Map<String, dynamic>;
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: glassPanelDecoration(radius: 14),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(t['label'] as String? ?? '', style: const TextStyle(color: Colors.white, fontSize: 14)),
                              const SizedBox(height: 2),
                              Text('від ${_money(t['threshold_amount'])}',
                                  style: const TextStyle(color: Colors.white38, fontSize: 12)),
                            ],
                          ),
                        ),
                        Text('+${_money(t['bonus_amount'])}',
                            style: TextStyle(color: AppColors.gold300, fontWeight: FontWeight.w600)),
                        IconButton(
                          icon: const Icon(Icons.delete_outline, color: Colors.white38, size: 20),
                          onPressed: () => _delete(t['id'] as int),
                        ),
                      ],
                    ),
                  );
                },
              ),
        Positioned(
          right: 16,
          bottom: 16,
          child: FloatingActionButton.extended(onPressed: _add, icon: const Icon(Icons.add), label: const Text('Додати тір')),
        ),
      ],
    );
  }
}

class _TierDialog extends StatefulWidget {
  const _TierDialog();

  @override
  State<_TierDialog> createState() => _TierDialogState();
}

class _TierDialogState extends State<_TierDialog> {
  final _labelController = TextEditingController();
  final _thresholdController = TextEditingController();
  final _bonusController = TextEditingController();
  bool _saving = false;

  Future<void> _submit() async {
    final threshold = int.tryParse(_thresholdController.text.trim());
    final bonus = int.tryParse(_bonusController.text.trim());
    if (_labelController.text.trim().isEmpty || threshold == null || bonus == null) return;
    setState(() => _saving = true);
    try {
      await ApiClient.instance.adminStoreTier(_labelController.text.trim(), threshold, bonus);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _labelController.dispose();
    _thresholdController.dispose();
    _bonusController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Новий тір інвестицій'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(controller: _labelController, decoration: const InputDecoration(labelText: 'Назва')),
          const SizedBox(height: 10),
          TextField(
            controller: _thresholdController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Поріг суми, ₴'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _bonusController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Бонус, ₴'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Скасувати')),
        TextButton(
          onPressed: _saving ? null : _submit,
          child: _saving
              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Додати'),
        ),
      ],
    );
  }
}

// ---------------- Налаштування ----------------

class _SettingsTab extends StatefulWidget {
  final Map<String, dynamic> settings;
  final VoidCallback onChanged;
  const _SettingsTab({required this.settings, required this.onChanged});

  @override
  State<_SettingsTab> createState() => _SettingsTabState();
}

class _SettingsTabState extends State<_SettingsTab> {
  late final Map<String, TextEditingController> _c;
  late bool _transferEnabled;
  late bool _depositEnabled;
  bool _savingRates = false;
  bool _savingBank = false;

  static const _rateFields = [
    ('bizwar_base_rate', 'Ставка за бізвар'),
    ('contract_light_rate', 'Контракт: легкий'),
    ('contract_medium_rate', 'Контракт: середній'),
    ('contract_heavy_rate', 'Контракт: важкий'),
    ('streak_threshold', 'Поріг серії (днів)'),
    ('streak_bonus_amount', 'Бонус за серію'),
    ('contracts_count_threshold', 'Поріг к-сті контрактів'),
    ('contracts_count_bonus_amount', 'Бонус за к-сть'),
    ('min_digest_amount', 'Мін. сума для дайджесту'),
  ];

  static const _bankFields = [
    ('transfer_daily_limit', 'Денний ліміт переказів, ₴'),
    ('transfer_min_amount', 'Мін. сума переказу, ₴'),
    ('deposit_interest_rate', 'Ставка депозиту, %'),
    ('deposit_min_amount', 'Мін. сума депозиту, ₴'),
    ('deposit_term_days', 'Строк депозиту, днів'),
  ];

  @override
  void initState() {
    super.initState();
    _c = {
      for (final f in [..._rateFields, ..._bankFields]) f.$1: TextEditingController(text: '${widget.settings[f.$1] ?? ''}'),
    };
    _transferEnabled = widget.settings['transfer_enabled'] == true;
    _depositEnabled = widget.settings['deposit_enabled'] == true;
  }

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _numbers(List<(String, String)> fields) {
    final result = <String, dynamic>{};
    for (final f in fields) {
      final raw = _c[f.$1]!.text.trim();
      result[f.$1] = raw.isEmpty ? null : (num.tryParse(raw) ?? 0);
    }
    return result;
  }

  Future<void> _saveRates() async {
    setState(() => _savingRates = true);
    try {
      await ApiClient.instance.adminUpdateBonusSettings(_numbers(_rateFields));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Налаштування премій збережено.')));
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _savingRates = false);
    }
  }

  Future<void> _saveBank() async {
    setState(() => _savingBank = true);
    try {
      await ApiClient.instance.adminUpdateBankSettings({
        ..._numbers(_bankFields),
        'transfer_enabled': _transferEnabled,
        'deposit_enabled': _depositEnabled,
      });
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Налаштування банку збережено.')));
      widget.onChanged();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _savingBank = false);
    }
  }

  Widget _field(String key, String label) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: TextField(
          controller: _c[key],
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: label),
        ),
      );

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Ставки премій', style: TextStyle(color: AppColors.gold300, fontSize: 15, fontWeight: FontWeight.w600)),
        const SizedBox(height: 12),
        for (final f in _rateFields) _field(f.$1, f.$2),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _savingRates ? null : _saveRates,
            child: _savingRates
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Зберегти ставки'),
          ),
        ),
        const SizedBox(height: 28),
        Text('Банк', style: TextStyle(color: AppColors.gold300, fontSize: 15, fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Перекази між картками увімкнено', style: TextStyle(color: Colors.white, fontSize: 13)),
          value: _transferEnabled,
          onChanged: (v) => setState(() => _transferEnabled = v),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Депозити увімкнено', style: TextStyle(color: Colors.white, fontSize: 13)),
          value: _depositEnabled,
          onChanged: (v) => setState(() => _depositEnabled = v),
        ),
        const SizedBox(height: 8),
        for (final f in _bankFields) _field(f.$1, f.$2),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _savingBank ? null : _saveBank,
            child: _savingBank
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Зберегти налаштування банку'),
          ),
        ),
        const SizedBox(height: 20),
      ],
    );
  }
}
