import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../theme.dart';
import 'animated_counter.dart';

/// Мобільний варіант картки з сайту (MemberCard.vue): пропорції ID-1
/// (85.6×53.98мм), номер у 4 групи по 4 цифри, баланс по центру,
/// печатка Monsory у правому нижньому куті. Той самий дизайн-код, просто
/// у Flutter-віджетах замість Vue+CSS container queries.
class MemberCardWidget extends StatelessWidget {
  final String maskedNumber;
  final String name;
  final int balance;

  const MemberCardWidget(
      {super.key,
      required this.maskedNumber,
      required this.name,
      required this.balance});

  List<String> get _groups {
    final groups = <String>[];
    for (var i = 0; i < maskedNumber.length; i += 4) {
      groups.add(
          maskedNumber.substring(i, (i + 4).clamp(0, maskedNumber.length)));
    }
    return groups;
  }

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 85.6 / 53.98,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: glassPanelDecoration(radius: 18).copyWith(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.obsidian800,
              AppColors.obsidian900,
              AppColors.gold400.withValues(alpha: 0.06),
            ],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'MONSORY FINANCE',
                  style: TextStyle(
                    color: AppColors.gold200.withValues(alpha: 0.8),
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1.5,
                  ),
                ),
                const _SealIcon(),
              ],
            ),
            const Spacer(),
            Center(
              child: Wrap(
                spacing: 14,
                children: _groups
                    .map((g) => Text(
                          g,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace',
                            letterSpacing: 2,
                          ),
                        ))
                    .toList(),
              ),
            ),
            const SizedBox(height: 10),
            Center(
              child: AnimatedCounter(
                value: balance,
                formatter: (v) => '${NumberFormat.decimalPattern('uk').format(v.round())}₴',
                style: TextStyle(
                  color: AppColors.gold200,
                  fontSize: 26,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const Spacer(),
            Text(
              name,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.8),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SealIcon extends StatelessWidget {
  const _SealIcon();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.gold400, width: 1.5),
      ),
      child: Center(
        child: Text(
          'M',
          style: TextStyle(
              color: AppColors.gold300,
              fontWeight: FontWeight.bold,
              fontSize: 14),
        ),
      ),
    );
  }
}
