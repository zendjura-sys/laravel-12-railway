import 'package:flutter/material.dart';

/// Плавна поява елемента списку — fade + невеликий зсув знизу вгору,
/// з опційним stagger-зсувом за індексом (кожен наступний елемент
/// стартує трохи пізніше за попередній). Один спільний віджет для
/// всіх списків застосунку, щоб анімація виглядала однаково всюди.
class FadeSlideIn extends StatelessWidget {
  final Widget child;
  final int index;
  final Duration duration;
  final Duration stagger;

  const FadeSlideIn({
    super.key,
    required this.child,
    this.index = 0,
    this.duration = const Duration(milliseconds: 320),
    this.stagger = const Duration(milliseconds: 35),
  });

  @override
  Widget build(BuildContext context) {
    final delay = stagger * index;
    return TweenAnimationBuilder<double>(
      key: ValueKey(index),
      tween: Tween(begin: 0, end: 1),
      duration: duration + delay,
      curve: Interval(
        (delay.inMilliseconds / (duration + delay).inMilliseconds).clamp(0, 1),
        1,
        curve: Curves.easeOutCubic,
      ),
      builder: (context, t, child) {
        return Opacity(
          opacity: t,
          child: Transform.translate(offset: Offset(0, (1 - t) * 14), child: child),
        );
      },
      child: child,
    );
  }
}
