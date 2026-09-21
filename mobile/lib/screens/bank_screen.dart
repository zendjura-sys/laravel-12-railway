import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/member_card_widget.dart';

class BankScreen extends StatefulWidget {
  const BankScreen({super.key});

  @override
  State<BankScreen> createState() => _BankScreenState();
}

class _BankScreenState extends State<BankScreen> {
  Map<String, dynamic>? _data;
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
      final data = await ApiClient.instance.bank();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити дані банку.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openTransferSheet() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.obsidian900,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => const _TransferSheet(),
    );
    if (ok == true) _load();
  }

  Future<void> _openDepositSheet() async {
    final settings = _data?['depositSettings'] as Map<String, dynamic>?;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.obsidian900,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _DepositSheet(settings: settings),
    );
    if (ok == true) _load();
  }

  Future<void> _openCashSheet() async {
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.obsidian900,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => const _CashRequestSheet(),
    );
    if (ok == true) _load();
  }

  Future<void> _withdraw(int depositId) async {
    try {
      await ApiClient.instance.withdrawDeposit(depositId);
      _load();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final card = _data?['card'] as Map<String, dynamic>?;
    final transactions = (_data?['transactions'] as List<dynamic>?) ?? [];
    final deposits = (_data?['deposits'] as List<dynamic>?) ?? [];

    return Scaffold(
      appBar: AppBar(title: const Text('Банк')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorView(message: _error!, onRetry: _load)
                : ListView(
                    // Знизу більше, ніж по інших краях — та сама причина,
                    // що в menu_screen.dart/dashboard_screen.dart: плаваюча
                    // нижня навігація лишала останній картці замало повітря
                    // над собою.
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 44),
                    children: [
                      if (card != null)
                        MemberCardWidget(
                          maskedNumber: card['number'] as String,
                          name: card['name'] as String,
                          balance: card['balance'] as int,
                        ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(
                              child: _ActionButton(
                                  label: 'Переказ',
                                  icon: Icons.send,
                                  onTap: _openTransferSheet)),
                          const SizedBox(width: 10),
                          Expanded(
                              child: _ActionButton(
                                  label: 'Депозит',
                                  icon: Icons.savings_outlined,
                                  onTap: _openDepositSheet)),
                          const SizedBox(width: 10),
                          Expanded(
                              child: _ActionButton(
                                  label: 'Готівка',
                                  icon: Icons.money,
                                  onTap: _openCashSheet)),
                        ],
                      ),
                      if (deposits.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        const _SectionTitle('Депозити'),
                        ...deposits.map((d) => _DepositTile(
                            deposit: d as Map<String, dynamic>,
                            onWithdraw: () => _withdraw(d['id'] as int))),
                      ],
                      const SizedBox(height: 24),
                      const _SectionTitle('Виписка по рахунку'),
                      if (transactions.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 16),
                          child: Text('Поки що порожньо',
                              style: TextStyle(color: Colors.white38)),
                        )
                      else
                        ...transactions.map((t) =>
                            _TransactionTile(tx: t as Map<String, dynamic>)),
                    ],
                  ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        text.toUpperCase(),
        style: TextStyle(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 12,
            letterSpacing: 1.2,
            fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  const _ActionButton(
      {required this.label, required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: glassPanelDecoration(radius: 14),
        child: Column(
          children: [
            Icon(icon, color: AppColors.gold300, size: 22),
            const SizedBox(height: 6),
            Text(label,
                style: const TextStyle(color: Colors.white, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class _TransactionTile extends StatelessWidget {
  final Map<String, dynamic> tx;
  const _TransactionTile({required this.tx});

  @override
  Widget build(BuildContext context) {
    final sign = tx['sign'] as String;
    final amount = tx['amount'] as int? ?? 0;
    final color = sign == '+'
        ? Colors.greenAccent.shade200
        : sign == '−'
            ? Colors.redAccent.shade100
            : Colors.white38;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx['label'] as String? ?? '',
                    style: const TextStyle(color: Colors.white, fontSize: 14)),
                if (tx['detail'] != null) ...[
                  const SizedBox(height: 2),
                  Text(tx['detail'] as String,
                      style:
                          const TextStyle(color: Colors.white38, fontSize: 12)),
                ],
              ],
            ),
          ),
          Text(
            '$sign${NumberFormat.decimalPattern('uk').format(amount)}₴',
            style: TextStyle(
                color: color, fontWeight: FontWeight.w600, fontSize: 14),
          ),
        ],
      ),
    );
  }
}

class _DepositTile extends StatelessWidget {
  final Map<String, dynamic> deposit;
  final VoidCallback onWithdraw;

  const _DepositTile({required this.deposit, required this.onWithdraw});

  @override
  Widget build(BuildContext context) {
    final active = deposit['status'] == 'active';
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                    '${NumberFormat.decimalPattern('uk').format(deposit['amount'])}₴ під ${deposit['interest_rate']}%',
                    style: const TextStyle(color: Colors.white, fontSize: 14)),
                const SizedBox(height: 2),
                Text(
                  active
                      ? 'Очікується: ${NumberFormat.decimalPattern('uk').format(deposit['projected_payout'])}₴'
                      : deposit['status'] as String,
                  style: const TextStyle(color: Colors.white38, fontSize: 12),
                ),
              ],
            ),
          ),
          if (active)
            TextButton(onPressed: onWithdraw, child: const Text('Зняти')),
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

// ---------------- Bottom sheets ----------------

class _TransferSheet extends StatefulWidget {
  const _TransferSheet();

  @override
  State<_TransferSheet> createState() => _TransferSheetState();
}

class _TransferSheetState extends State<_TransferSheet> {
  final _queryController = TextEditingController();
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();
  List<dynamic> _results = [];
  Map<String, dynamic>? _selected;
  bool _loading = false;
  String? _error;

  Future<void> _search(String q) async {
    if (q.trim().length < 2) {
      setState(() => _results = []);
      return;
    }
    try {
      final members = await ApiClient.instance.searchRecipients(q.trim());
      if (mounted) setState(() => _results = members);
    } catch (_) {}
  }

  Future<void> _submit() async {
    if (_selected == null || _amountController.text.trim().isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ApiClient.instance.transfer(
        recipientId: _selected!['id'] as int,
        amount: int.parse(_amountController.text.trim()),
        note: _noteController.text.trim(),
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 20,
          right: 20,
          top: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Переказ',
              style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w500)),
          const SizedBox(height: 16),
          if (_selected == null) ...[
            TextField(
              controller: _queryController,
              decoration: const InputDecoration(labelText: 'Кому (імʼя)'),
              onChanged: _search,
            ),
            if (_results.isNotEmpty)
              ..._results.map((m) => ListTile(
                    title: Text(m['name'] as String,
                        style: const TextStyle(color: Colors.white)),
                    onTap: () => setState(() {
                      _selected = m as Map<String, dynamic>;
                      _results = [];
                    }),
                  )),
          ] else
            ListTile(
              tileColor: AppColors.obsidian800,
              title: Text(_selected!['name'] as String,
                  style: const TextStyle(color: Colors.white)),
              trailing: IconButton(
                  icon: const Icon(Icons.close, color: Colors.white38),
                  onPressed: () => setState(() => _selected = null)),
            ),
          const SizedBox(height: 12),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Сума, ₴'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _noteController,
            decoration:
                const InputDecoration(labelText: 'Коментар (необовʼязково)'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: (_loading || _selected == null) ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('ПЕРЕКАЗАТИ'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _DepositSheet extends StatefulWidget {
  final Map<String, dynamic>? settings;
  const _DepositSheet({required this.settings});

  @override
  State<_DepositSheet> createState() => _DepositSheetState();
}

class _DepositSheetState extends State<_DepositSheet> {
  final _amountController = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    if (_amountController.text.trim().isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ApiClient.instance
          .openDeposit(int.parse(_amountController.text.trim()));
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final rate = widget.settings?['rate'];
    final termDays = widget.settings?['termDays'];
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 20,
          right: 20,
          top: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Відкрити депозит',
              style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w500)),
          if (rate != null) ...[
            const SizedBox(height: 6),
            Text('$rate% на $termDays днів',
                style: const TextStyle(color: Colors.white38, fontSize: 13)),
          ],
          const SizedBox(height: 16),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Сума, ₴'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('ВІДКРИТИ'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _CashRequestSheet extends StatefulWidget {
  const _CashRequestSheet();

  @override
  State<_CashRequestSheet> createState() => _CashRequestSheetState();
}

class _CashRequestSheetState extends State<_CashRequestSheet> {
  final _amountController = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    if (_amountController.text.trim().isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ApiClient.instance
          .requestCash(int.parse(_amountController.text.trim()));
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 20,
          right: 20,
          top: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Отримати готівку на руки',
              style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w500)),
          const SizedBox(height: 6),
          const Text(
            'Сума одразу заморожується. Керівництву прийде сповіщення для узгодження передачі.',
            style: TextStyle(color: Colors.white38, fontSize: 13),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Сума, ₴'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('ЗАПРОСИТИ'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}
