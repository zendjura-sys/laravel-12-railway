import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../theme.dart';

/// Один прямокутний "плейсхолдер" для skeleton-екрана — сама форма без
/// анімації, блиск додає обгортка [ShimmerLoader] зверху.
class ShimmerBox extends StatelessWidget {
  final double? width;
  final double height;
  final double radius;

  const ShimmerBox({super.key, this.width, this.height = 14, this.radius = 8});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

/// Один прогін глянцевого відблиску по всьому переданому skeleton'у —
/// заміна CircularProgressIndicator на час першого завантаження. На
/// відміну від крутилки, одразу показує СИЛУЕТ майбутнього контенту
/// (текст/картки на своїх місцях), тож зникнення "стрибка" при появі
/// реальних даних майже непомітне.
class ShimmerLoader extends StatelessWidget {
  final Widget child;

  const ShimmerLoader({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.white.withValues(alpha: 0.06),
      highlightColor: AppColors.gold300.withValues(alpha: 0.16),
      period: const Duration(milliseconds: 1400),
      child: child,
    );
  }
}

/// Готовий skeleton для рядка списку "аватар + два рядки тексту + дрібний
/// текст праворуч" — форма спільна для списку розмов, рейтингу,
/// сповіщень тощо, тому один віджет замість дублювання в кожному екрані.
class ShimmerListTile extends StatelessWidget {
  const ShimmerListTile({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: glassPanelDecoration(radius: 16),
      child: Row(
        children: [
          const ShimmerBox(width: 44, height: 44, radius: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                ShimmerBox(width: 140, height: 13),
                SizedBox(height: 8),
                ShimmerBox(width: 90, height: 11),
              ],
            ),
          ),
          const SizedBox(width: 12),
          const ShimmerBox(width: 30, height: 11),
        ],
      ),
    );
  }
}

/// Кілька [ShimmerListTile] під одним відблиском — типовий вигляд
/// "список ще вантажиться" (розмови, рейтинг, сповіщення).
class ShimmerListSkeleton extends StatelessWidget {
  final int count;

  const ShimmerListSkeleton({super.key, this.count = 6});

  @override
  Widget build(BuildContext context) {
    return ShimmerLoader(
      child: Column(children: List.generate(count, (_) => const ShimmerListTile())),
    );
  }
}

/// Skeleton для квадратної сітки (галерея) — той самий 3-колонковий
/// GridView, лише з прямокутниками замість фото.
class ShimmerGridSkeleton extends StatelessWidget {
  final int count;

  const ShimmerGridSkeleton({super.key, this.count = 9});

  @override
  Widget build(BuildContext context) {
    return ShimmerLoader(
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 3,
          crossAxisSpacing: 8,
          mainAxisSpacing: 8,
        ),
        itemCount: count,
        itemBuilder: (context, i) => const ShimmerBox(height: double.infinity, radius: 10),
      ),
    );
  }
}
