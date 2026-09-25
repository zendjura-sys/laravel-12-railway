import 'package:flutter/material.dart';
import '../theme.dart';

/// Кольори типів покарань — ті самі, що на сайті й у розділі «Правила».
const penaltyColors = <String, Color>{
  'remark': Color(0xFF7DD3FC),
  'fine': Color(0xFFFCD34D),
  'reprimand': Color(0xFFFB923C),
  'demotion': Color(0xFFE879F9),
  'kick': Color(0xFFF87171),
  'blacklist': Color(0xFFDC2626),
};

String formatMoney(num? amount) {
  final digits = (amount ?? 0).round().toString();
  final buf = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buf.write(' ');
    buf.write(digits[i]);
  }
  return '$buf₴';
}

String formatPenaltyDate(String? iso) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return '';
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(d.day)}.${two(d.month)}.${d.year} ${two(d.hour)}:${two(d.minute)}';
}

bool isOpenPenalty(Map<String, dynamic> p) => p['status'] == 'active' || p['status'] == 'overdue';

/// Картка покарання — для «Мої покарання» й адмінки. [actions] — кнопки внизу.
class PenaltyCard extends StatelessWidget {
  final Map<String, dynamic> penalty;
  final bool showMember;
  final List<Widget> actions;

  const PenaltyCard({super.key, required this.penalty, this.showMember = false, this.actions = const []});

  @override
  Widget build(BuildContext context) {
    final p = penalty;
    final type = p['type'] as String? ?? 'remark';
    final color = penaltyColors[type] ?? Colors.white70;
    final open = isOpenPenalty(p);
    final overdue = p['status'] == 'overdue';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 16).copyWith(
        border: Border.all(
          color: overdue ? const Color(0x99F87171) : Colors.white.withValues(alpha: open ? 0.12 : 0.06),
        ),
      ),
      child: Opacity(
        opacity: open ? 1 : 0.6,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: color.withValues(alpha: 0.45)),
                  ),
                  child: Text('${p['emoji'] ?? ''} ${p['typeLabel'] ?? ''}', style: TextStyle(color: color, fontSize: 12)),
                ),
                if (type == 'fine')
                  Text(formatMoney(p['amount'] as num?),
                      style: TextStyle(color: color, fontSize: 15, fontWeight: FontWeight.w600)),
                if (p['originalAmount'] != null)
                  Text(formatMoney(p['originalAmount'] as num?),
                      style: const TextStyle(color: Colors.white38, fontSize: 12, decoration: TextDecoration.lineThrough)),
                if (showMember && p['user'] != null)
                  Text((p['user'] as Map)['name'] as String? ?? '',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                if (p['ruleCode'] != null)
                  Text('п. ${p['ruleCode']}${p['ruleLabel'] != null ? ' · ${p['ruleLabel']}' : ''}',
                      style: TextStyle(color: AppColors.gold300, fontSize: 12)),
              ],
            ),
            const SizedBox(height: 8),
            Text(p['reason'] as String? ?? '', style: const TextStyle(color: Colors.white, fontSize: 14, height: 1.35)),
            const SizedBox(height: 6),
            Text(_meta(p), style: const TextStyle(color: Colors.white38, fontSize: 11.5, height: 1.35)),
            if (overdue)
              const Padding(
                padding: EdgeInsets.only(top: 6),
                child: Text('Строк оплати минув — сума подвоєна, видано догану.',
                    style: TextStyle(color: Color(0xFFFCA5A5), fontSize: 12)),
              ),
            if (actions.isNotEmpty) ...[
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: actions),
            ],
          ],
        ),
      ),
    );
  }

  static String _meta(Map<String, dynamic> p) {
    final parts = <String>[];
    final author = (p['author'] as Map?)?['name'] as String?;
    parts.add('${p['auto'] == true ? 'Автоматично' : (author ?? 'Керівництво')} · ${formatPenaltyDate(p['createdAt'] as String?)}');
    if (isOpenPenalty(p)) {
      if (p['type'] == 'fine' && p['dueAt'] != null) parts.add('сплатити до ${formatPenaltyDate(p['dueAt'] as String?)}');
      if (p['expiresAt'] != null) parts.add('згорить ${formatPenaltyDate(p['expiresAt'] as String?)}');
    } else {
      var status = p['statusLabel'] as String? ?? '';
      if (p['status'] == 'paid' && p['paidVia'] == 'bank') status += ' з рахунку';
      parts.add(status);
      if (p['resolutionNote'] != null) parts.add('«${p['resolutionNote']}»');
    }
    return parts.join(' · ');
  }
}
