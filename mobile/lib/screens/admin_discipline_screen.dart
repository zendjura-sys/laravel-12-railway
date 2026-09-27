import 'dart:async';
import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';
import '../widgets/penalty_card.dart';

/// Мобільна адмінка покарань (members.manage) — ті самі дії, що й розділ
/// «Покарання» на сайті: видати (з пунктом правил і сумою штрафу),
/// позначити штраф сплаченим, скасувати.
class AdminDisciplineScreen extends StatefulWidget {
  const AdminDisciplineScreen({super.key});

  @override
  State<AdminDisciplineScreen> createState() => _AdminDisciplineScreenState();
}

class _AdminDisciplineScreenState extends State<AdminDisciplineScreen> {
  static const _filters = [
    ('open', 'Активні'),
    ('fines', 'Штрафи'),
    ('closed', 'Закриті'),
    ('all', 'Усі'),
  ];

  String _status = 'open';
  Map<String, dynamic>? _data;
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
      _loading = _data == null;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.adminDiscipline(status: _status);
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити покарання.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _snack(String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

  Future<void> _markPaid(Map<String, dynamic> p) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Штраф сплачено?'),
        content: Text('Підтвердити, що ${(p['user'] as Map?)?['name']} сплатив(-ла) ${formatMoney(p['amount'] as num?)} поза банком?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Ні')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Так, сплачено')),
        ],
      ),
    );
    if (ok != true) return;
    await _run(p, () => ApiClient.instance.adminMarkPenaltyPaid(p['id'] as int), 'Штраф позначено сплаченим.');
  }

  Future<void> _revoke(Map<String, dynamic> p) async {
    final controller = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Скасувати: ${p['typeLabel']}'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 2,
          decoration: const InputDecoration(hintText: 'Причина скасування (оскарження, помилка…)'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Назад')),
          TextButton(onPressed: () => Navigator.of(context).pop(controller.text.trim()), child: const Text('Скасувати покарання')),
        ],
      ),
    );
    if (note == null || note.isEmpty) return;
    await _run(p, () => ApiClient.instance.adminRevokePenalty(p['id'] as int, note), 'Покарання скасовано.');
  }

  Future<void> _run(Map<String, dynamic> p, Future<void> Function() action, String success) async {
    setState(() => _busyId = p['id'] as int);
    try {
      await action();
      _snack(success);
      await _load();
    } on ApiException catch (e) {
      _snack(e.message);
    } catch (_) {
      _snack('Не вдалося виконати дію.');
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _openIssue() async {
    final issued = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.obsidian900,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => _IssueSheet(
        types: ((_data?['types'] as List<dynamic>?) ?? []).cast<Map<String, dynamic>>(),
        rules: ((_data?['rules'] as List<dynamic>?) ?? []).cast<Map<String, dynamic>>(),
      ),
    );
    if (issued == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final stats = (_data?['stats'] as Map<String, dynamic>?) ?? {};
    final penalties = ((_data?['penalties'] as List<dynamic>?) ?? []).cast<Map<String, dynamic>>();

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: IslandAppBar(
        title: 'Покарання',
        actions: [
          IslandCircleButton(icon: const Icon(Icons.add_rounded), tooltip: 'Видати покарання', onTap: _data == null ? null : _openIssue),
        ],
      ),
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
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _StatChip(label: 'Зауваження', value: '${stats['openRemarks'] ?? 0}', color: penaltyColors['remark']!),
                          _StatChip(label: 'Догани', value: '${stats['openReprimands'] ?? 0}', color: penaltyColors['reprimand']!),
                          _StatChip(
                            label: 'Штрафи',
                            value: '${stats['unpaidFines'] ?? 0} · ${formatMoney(stats['unpaidFinesAmount'] as num?)}',
                            color: penaltyColors['fine']!,
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        height: 40,
                        child: ListView(
                          scrollDirection: Axis.horizontal,
                          children: [
                            for (final f in _filters)
                              Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: ChoiceChip(
                                  label: Text(f.$2),
                                  selected: _status == f.$1,
                                  onSelected: (_) {
                                    setState(() => _status = f.$1);
                                    _load();
                                  },
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (penalties.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 40),
                          child: Center(child: Text('Нічого немає.', style: TextStyle(color: Colors.white38))),
                        ),
                      for (final p in penalties)
                        PenaltyCard(
                          penalty: p,
                          showMember: true,
                          actions: [
                            if (isOpenPenalty(p) && p['type'] == 'fine')
                              OutlinedButton(
                                onPressed: _busyId == p['id'] ? null : () => _markPaid(p),
                                child: const Text('Сплачено'),
                              ),
                            if (isOpenPenalty(p))
                              TextButton(
                                onPressed: _busyId == p['id'] ? null : () => _revoke(p),
                                child: const Text('Скасувати'),
                              ),
                          ],
                        ),
                    ],
                  ),
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  const _StatChip({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: Colors.white54, fontSize: 11)),
          Text(value, style: TextStyle(color: color, fontSize: 15, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

/// Форма видачі: учасник (пошук), тип, пункт правил, сума (для штрафу), причина.
class _IssueSheet extends StatefulWidget {
  final List<Map<String, dynamic>> types;
  final List<Map<String, dynamic>> rules;
  const _IssueSheet({required this.types, required this.rules});

  @override
  State<_IssueSheet> createState() => _IssueSheetState();
}

class _IssueSheetState extends State<_IssueSheet> {
  final _search = TextEditingController();
  final _reason = TextEditingController();
  final _amount = TextEditingController();
  Timer? _debounce;
  List<dynamic> _matches = [];
  Map<String, dynamic>? _member;
  String _type = 'remark';
  String? _ruleCode;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _reason.dispose();
    _amount.dispose();
    super.dispose();
  }

  void _onSearch(String q) {
    _debounce?.cancel();
    if (_member != null && q != _member!['name']) setState(() => _member = null);
    if (q.trim().length < 2) {
      setState(() => _matches = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final found = await ApiClient.instance.adminDisciplineSearchMembers(q.trim());
        if (mounted) setState(() => _matches = found);
      } catch (_) {}
    });
  }

  Future<void> _submit() async {
    final amount = int.tryParse(_amount.text.replaceAll(RegExp(r'\s'), ''));
    if (_member == null) return setState(() => _error = 'Оберіть учасника зі списку.');
    if (_reason.text.trim().isEmpty) return setState(() => _error = 'Вкажіть причину.');
    if (_type == 'fine' && (amount == null || amount < 1)) return setState(() => _error = 'Вкажіть суму штрафу.');

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final message = await ApiClient.instance.adminIssuePenalty(
        userId: _member!['id'] as int,
        type: _type,
        reason: _reason.text.trim(),
        ruleCode: _ruleCode,
        amount: _type == 'fine' ? amount : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$message Учасника сповіщено.')));
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося видати покарання.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = _member?['summary'] as Map<String, dynamic>?;
    final rule = widget.rules.where((r) => r['code'] == _ruleCode).firstOrNull;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2)),
                ),
              ),
              const SizedBox(height: 14),
              const Text('Видати покарання', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
              const SizedBox(height: 14),
              TextField(
                controller: _search,
                onChanged: _onSearch,
                decoration: const InputDecoration(labelText: 'Учасник', prefixIcon: Icon(Icons.person_search_outlined)),
              ),
              for (final m in _matches.cast<Map<String, dynamic>>())
                ListTile(
                  dense: true,
                  title: Text(m['name'] as String, style: const TextStyle(color: Colors.white)),
                  subtitle: Text(
                    'зауваж. ${(m['summary'] as Map)['remarks']}/3 · догани ${(m['summary'] as Map)['reprimands']}/3',
                    style: const TextStyle(color: Colors.white54),
                  ),
                  onTap: () => setState(() {
                    _member = m;
                    _search.text = m['name'] as String;
                    _matches = [];
                  }),
                ),
              if (summary != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    'Зараз: зауважень ${summary['remarks']}/3, доган ${summary['reprimands']}/3'
                    '${(summary['unpaidFines'] as int? ?? 0) > 0 ? ', до сплати ${formatMoney(summary['unpaidFinesAmount'] as num?)}' : ''}',
                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                  ),
                ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final t in widget.types)
                    ChoiceChip(
                      label: Text('${t['emoji']} ${t['label']}'),
                      selected: _type == t['key'],
                      selectedColor: (penaltyColors[t['key']] ?? Colors.white).withValues(alpha: 0.25),
                      onSelected: (_) => setState(() => _type = t['key'] as String),
                    ),
                ],
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String?>(
                value: _ruleCode,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Пункт правил родини'),
                items: [
                  const DropdownMenuItem<String?>(value: null, child: Text('— без пункту —')),
                  for (final r in widget.rules)
                    DropdownMenuItem<String?>(
                      value: r['code'] as String,
                      child: Text('${r['code']} — ${r['text']}', maxLines: 1, overflow: TextOverflow.ellipsis),
                    ),
                ],
                onChanged: (v) => setState(() => _ruleCode = v),
              ),
              if (rule?['penalty'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text('За правилами: ${rule!['penalty']}',
                      style: const TextStyle(color: Color(0xCCFDE68A), fontSize: 12)),
                ),
              if (_type == 'fine') ...[
                const SizedBox(height: 14),
                TextField(
                  controller: _amount,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Сума штрафу, ₴', hintText: 'напр. 50000'),
                ),
              ],
              const SizedBox(height: 14),
              TextField(
                controller: _reason,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Причина (побачить учасник)'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                Text(_error!, style: const TextStyle(color: Color(0xFFF87171))),
              ],
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _saving ? null : _submit,
                child: Text(_saving ? 'Видаю…' : 'Видати'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
