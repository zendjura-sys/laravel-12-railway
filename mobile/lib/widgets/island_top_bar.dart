import 'dart:ui';
import 'package:flutter/material.dart';
import '../theme.dart';

/// Скло "острівця" — те саме, що в нижньої навігації (HomeShell): легкий
/// напівпрозорий obsidian950 поверх розмитого фону й тонка золота обводка.
/// Трохи щільніше за нижню панель — під верхньою прокручується текст.
class GlassIsland extends StatelessWidget {
  final Widget child;
  final BorderRadius borderRadius;
  final EdgeInsetsGeometry? padding;

  const GlassIsland({super.key, required this.child, required this.borderRadius, this.padding});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: borderRadius,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            color: AppColors.obsidian950.withValues(alpha: 0.45),
            borderRadius: borderRadius,
            border: Border.all(color: AppColors.gold400.withValues(alpha: 0.14)),
          ),
          child: child,
        ),
      ),
    );
  }
}

/// Кругла кнопка-острівець (52×52) — "назад"/дзвіночок ліворуч, ⋮ праворуч.
class IslandCircleButton extends StatelessWidget {
  final Widget icon;
  final VoidCallback? onTap;
  final int badge;
  final String? tooltip;

  const IslandCircleButton({super.key, required this.icon, this.onTap, this.badge = 0, this.tooltip});

  static const double size = 52;

  @override
  Widget build(BuildContext context) {
    final button = SizedBox(
      width: size,
      height: size,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          GlassIsland(
            borderRadius: BorderRadius.circular(size / 2),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: onTap,
                child: SizedBox.expand(
                  child: IconTheme(data: const IconThemeData(color: Colors.white, size: 24), child: icon),
                ),
              ),
            ),
          ),
          if (badge > 0)
            Positioned(
              right: 2,
              top: 2,
              child: IgnorePointer(
                child: TweenAnimationBuilder<double>(
                  key: ValueKey(badge),
                  tween: Tween(begin: 0.4, end: 1),
                  duration: const Duration(milliseconds: 260),
                  curve: Curves.elasticOut,
                  builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                    decoration: BoxDecoration(
                      color: AppColors.gold400,
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: AppColors.obsidian950, width: 1.5),
                    ),
                    child: Text(
                      badge > 99 ? '99+' : '$badge',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.obsidian950, fontSize: 10, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
    return tooltip == null ? button : Tooltip(message: tooltip!, child: button);
  }
}

/// Центральна пілюля: логотип родини + "MONSORY" + підзаголовок (розділ).
class IslandTitlePill extends StatelessWidget {
  final String title;
  final String subtitle;

  const IslandTitlePill({super.key, required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return GlassIsland(
      borderRadius: BorderRadius.circular(IslandCircleButton.size / 2),
      padding: const EdgeInsets.fromLTRB(5, 5, 18, 5),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.gold400.withValues(alpha: 0.45)),
              image: const DecorationImage(image: AssetImage('assets/icon/icon.png'), fit: BoxFit.cover),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 5,
                    height: 1.1,
                  ),
                ),
                const SizedBox(height: 2),
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  child: Text(
                    subtitle,
                    key: ValueKey(subtitle),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white54, fontSize: 13, height: 1.1),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Оголошення про нову версію — острівець під верхньою панеллю, силует
/// "закріпленого повідомлення" з Telegram: вертикальні сегменти-акценти
/// ліворуч, кольоровий заголовок, підпис, кнопка закриття праворуч.
class UpdateBannerIsland extends StatelessWidget {
  final int buildNumber;
  final VoidCallback onTap;
  final VoidCallback onDismiss;

  const UpdateBannerIsland({super.key, required this.buildNumber, required this.onTap, required this.onDismiss});

  static const double height = 60;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      child: GlassIsland(
        borderRadius: BorderRadius.circular(22),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            child: Row(
              children: [
                const SizedBox(width: 14),
                Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 0; i < 4; i++)
                      Container(
                        width: 3,
                        height: 7,
                        margin: const EdgeInsets.symmetric(vertical: 1.5),
                        decoration: BoxDecoration(
                          color: AppColors.gold300.withValues(alpha: i == 3 ? 1 : 0.35),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        'Доступна нова версія',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: AppColors.gold300, fontSize: 14.5, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Monsory Connect · збірка $buildNumber — натисніть, щоб оновити',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Colors.white60, fontSize: 13),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Приховати',
                  onPressed: onDismiss,
                  icon: const Icon(Icons.close_rounded, color: Colors.white54, size: 22),
                ),
                const SizedBox(width: 4),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Пілюля з назвою розділу (без логотипа) — центральний острівець шапки
/// вкладених екранів. Тягнеться на всю вільну ширину: де праворуч немає
/// кнопки дії, вона займає і її місце.
class IslandSectionPill extends StatelessWidget {
  final String title;
  final Widget? subtitle;
  final VoidCallback? onTap;

  const IslandSectionPill({super.key, required this.title, this.subtitle, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GlassIsland(
      borderRadius: BorderRadius.circular(IslandCircleButton.size / 2),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w500, height: 1.15),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  DefaultTextStyle.merge(
                    style: const TextStyle(fontSize: 12.5, color: Colors.white54, height: 1.1),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    child: subtitle!,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Шапка вкладених екранів у концепції острівців (як на Кабінеті):
/// ( ← ) ( Назва розділу ) ( дія ). Дії — IslandCircleButton; немає дій —
/// пілюля з назвою розтягується на їхнє місце. [bottom] — напр. TabBar.
class IslandAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final Widget? subtitle;
  final VoidCallback? onTitleTap;
  final List<Widget> actions;
  final PreferredSizeWidget? bottom;
  final bool showBack;

  const IslandAppBar({
    super.key,
    required this.title,
    this.subtitle,
    this.onTitleTap,
    this.actions = const [],
    this.bottom,
    this.showBack = true,
  });

  static const double _rowHeight = 8 + IslandCircleButton.size + 8;

  @override
  Size get preferredSize => Size.fromHeight(_rowHeight + (bottom?.preferredSize.height ?? 0));

  @override
  Widget build(BuildContext context) {
    final canPop = showBack && (ModalRoute.of(context)?.canPop ?? false);
    return SafeArea(
      bottom: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SizedBox(
              height: IslandCircleButton.size,
              child: Row(
                children: [
                  if (canPop) ...[
                    IslandCircleButton(
                      icon: const Icon(Icons.arrow_back_rounded),
                      tooltip: 'Назад',
                      onTap: () => Navigator.of(context).maybePop(),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Expanded(child: IslandSectionPill(title: title, subtitle: subtitle, onTap: onTitleTap)),
                  for (final action in actions) ...[
                    const SizedBox(width: 8),
                    action,
                  ],
                ],
              ),
            ),
          ),
          if (bottom != null) bottom!,
        ],
      ),
    );
  }
}
