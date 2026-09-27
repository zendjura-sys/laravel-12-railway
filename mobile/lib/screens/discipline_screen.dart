import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';
import '../widgets/penalty_card.dart';
import 'rules_screen.dart';

/// «Мої покарання» — стан (зауваження N/3, догани N/3, до сплати) і список;
/// штраф можна сплатити з рахунку в банку родини. Той самий вміст, що й
/// сторінка /discipline на сайті.
class DisciplineScreen extends StatefulWidget {
  const DisciplineScreen({super.key});

  @override
  State<DisciplineScreen> createState() => _DisciplineScreenState();
}

class _DisciplineScreenState extends State<DisciplineScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;
  int? _paying;

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
      final data = await ApiClient.instance.discipline();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити покарання.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pay(Map<String, dynamic> p) async {
    final amount = formatMoney(p['amount'] as num?);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Сплатити штраф?'),
        content: Text('З вашого рахунку в банку родини буде списано $amount.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Скасувати')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text('Сплатити $amount')),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _paying = p['id'] as int);
    try {
      final message = await ApiClient.instance.payPenalty(p['id'] as int);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Не вдалося сплатити штраф.')));
      }
    } finally {
      if (mounted) setState(() => _paying = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = (_data?['summary'] as Map<String, dynamic>?) ?? {};
    final penalties = ((_data?['penalties'] as List<dynamic>?) ?? []).cast<Map<String, dynamic>>();
    final open = penalties.where(isOpenPenalty).toList();
    final history = penalties.where((p) => !isOpenPenalty(p)).toList();
    final bankAvailable = _data?['bankAvailable'] == true;

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: IslandAppBar(
        title: 'Мої покарання',
        actions: [
          IslandCircleButton(
            icon: const Icon(Icons.gavel_outlined),
            tooltip: 'Правила',
            onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RulesScreen())),
          ),
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
                      Row(
                        children: [
                          Expanded(
                            child: _CounterTile(
                              label: 'Зауваження',
                              count: summary['remarks'] as int? ?? 0,
                              limit: summary['remarksLimit'] as int? ?? 3,
                              color: penaltyColors['remark']!,
                              hint: '3 = догана',
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _CounterTile(
                              label: 'Догани',
                              count: summary['reprimands'] as int? ?? 0,
                              limit: summary['reprimandsLimit'] as int? ?? 3,
                              color: penaltyColors['reprimand']!,
                              hint: summary['bonusHalved'] == true ? 'премія 50%' : '3/3 — виключення',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: glassPanelDecoration(radius: 16),
                        child: Row(
                          children: [
                            const Icon(Icons.receipt_long_outlined, color: Color(0xFFFCD34D)),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                (summary['unpaidFines'] as int? ?? 0) > 0
                                    ? 'До сплати: ${formatMoney(summary['unpaidFinesAmount'] as num?)} (штрафів: ${summary['unpaidFines']})'
                                    : 'Несплачених штрафів немає',
                                style: const TextStyle(color: Colors.white, fontSize: 14),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text('Діють зараз', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 10),
                      if (open.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24),
                          child: Center(
                              child: Text('Активних покарань немає — так тримати 👑',
                                  style: TextStyle(color: Colors.white54))),
                        ),
                      for (final p in open)
                        PenaltyCard(
                          penalty: p,
                          actions: [
                            if (p['type'] == 'fine' && bankAvailable)
                              FilledButton.icon(
                                onPressed: _paying == p['id'] ? null : () => _pay(p),
                                icon: _paying == p['id']
                                    ? const SizedBox(
                                        width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                    : const Icon(Icons.account_balance_wallet_outlined, size: 18),
                                label: Text('Сплатити ${formatMoney(p['amount'] as num?)}'),
                              ),
                          ],
                        ),
                      if (history.isNotEmpty) ...[
                        const SizedBox(height: 14),
                        const Text('Історія', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 10),
                        for (final p in history) PenaltyCard(penalty: p),
                      ],
                      const SizedBox(height: 8),
                      const Text(
                        'Оскаржити покарання можна протягом 48 годин особисто Директору. Шкала покарань — у розділі «Правила».',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white38, fontSize: 12),
                      ),
                    ],
                  ),
      ),
    );
  }
}

class _CounterTile extends StatelessWidget {
  final String label;
  final int count;
  final int limit;
  final Color color;
  final String hint;

  const _CounterTile({required this.label, required this.count, required this.limit, required this.color, required this.hint});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: Colors.white54, fontSize: 12)),
          const SizedBox(height: 8),
          Row(
            children: [
              for (var i = 0; i < limit; i++)
                Container(
                  width: 12,
                  height: 12,
                  margin: const EdgeInsets.only(right: 5),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: i < count ? color : Colors.transparent,
                    border: Border.all(color: color.withValues(alpha: 0.6)),
                  ),
                ),
              const SizedBox(width: 4),
              Text('$count/$limit', style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.w600)),
            ],
          ),
          const SizedBox(height: 6),
          Text(hint, style: const TextStyle(color: Colors.white38, fontSize: 11.5)),
        ],
      ),
    );
  }
}
