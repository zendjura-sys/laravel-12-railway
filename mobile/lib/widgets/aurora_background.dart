import 'dart:ui';
import 'package:flutter/material.dart';
import '../theme.dart';

/// Живий фон під усім застосунком — м'які розмиті плями в тій самій
/// золото-обсидіановій палітрі, що й решта бренду (жодних чужих
/// відтінків: справжня "аврора" з фіолетовим/бірюзовим зламала б
/// фірмовий стиль, який навмисно скопійований із CSS-змінних сайту).
/// Підключається один раз через MaterialApp.builder — не треба
/// вставляти в кожен екран окремо.
class AuroraBackground extends StatelessWidget {
  const AuroraBackground({super.key});

  @override
  Widget build(BuildContext context) {
    return Positioned.fill(
      child: ColoredBox(
        color: AppColors.obsidian950,
        child: Stack(
          children: [
            _Blob(top: -120, left: -80, color: AppColors.gold400, size: 340),
            _Blob(top: 160, right: -140, color: AppColors.ember500, size: 300),
            _Blob(bottom: -160, left: -60, color: AppColors.gold500, size: 380),
            // Один прохід блюру над усіма плямами разом — дешевше й
            // м'якіше, ніж блюрити кожну окремо.
            BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 90, sigmaY: 90),
              child: const SizedBox.expand(),
            ),
          ],
        ),
      ),
    );
  }
}

class _Blob extends StatelessWidget {
  final double? top;
  final double? left;
  final double? right;
  final double? bottom;
  final Color color;
  final double size;

  const _Blob({
    this.top,
    this.left,
    this.right,
    this.bottom,
    required this.color,
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: top,
      left: left,
      right: right,
      bottom: bottom,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color.withValues(alpha: 0.28), color.withValues(alpha: 0.0)],
          ),
        ),
      ),
    );
  }
}
