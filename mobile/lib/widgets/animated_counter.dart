import 'package:flutter/material.dart';

/// Цифри "прокручуються" від старого значення до нового замість миттєвої
/// заміни — той самий TweenAnimationBuilder-патерн, що вже стоїть під
/// бейджем непрочитаних сповіщень, лише як переюзабельний віджет: кожен
/// новий build() з іншим [value] анімує САМЕ ВІД поточного відображеного
/// числа (TweenAnimationBuilder сам підміняє begin на останнє анімоване
/// значення), а не від 0 щоразу.
class AnimatedCounter extends StatelessWidget {
  final num value;
  final TextStyle? style;
  final String Function(num) formatter;
  final Duration duration;
  final TextAlign? textAlign;

  const AnimatedCounter({
    super.key,
    required this.value,
    required this.formatter,
    this.style,
    this.duration = const Duration(milliseconds: 700),
    this.textAlign,
  });

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: value.toDouble()),
      duration: duration,
      curve: Curves.easeOutCubic,
      builder: (context, v, child) => Text(formatter(v), style: style, textAlign: textAlign),
    );
  }
}
