import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/physics.dart';
import 'package:google_fonts/google_fonts.dart';

/// Та сама палітра, що й на сайті (tailwind.config.js): obsidian — фон,
/// gold — акцент. Значення взяті буквально з CSS-змінних сайту, щоб
/// застосунок виглядав як продовження одного бренду, а не окремий проєкт.
///
/// gold-* НЕ const: якщо адмін змінить акцентний колір сайту (Admin →
/// Дизайн → Оформлення), /api/app-config віддає ті самі відтінки, і
/// [applyAccentShades] перезаписує їх при старті застосунку — далі кожен
/// віджет, що читає AppColors.gold*, побудований уже з новим кольором.
/// obsidian/ember — поза акцентом сайту, лишаються фіксованими.
class AppColors {
  static const obsidian950 = Color(0xFF060605);
  static const obsidian900 = Color(0xFF0B0A09);
  static const obsidian800 = Color(0xFF141210);
  static const obsidian700 = Color(0xFF1E1B18);

  static Color gold200 = const Color(0xFFF1E2B8);
  static Color gold300 = const Color(0xFFE4CD8F);
  static Color gold400 = const Color(0xFFD4AF37);
  static Color gold500 = const Color(0xFFC9A24B);
  static Color gold600 = const Color(0xFFA9822F);

  static const ember500 = Color(0xFFC4381F);

  /// Розбирає {'200': '#F1E2B8', '300': '#..', ...} з /api/app-config і
  /// перезаписує відповідні gold-поля. Повертає true, якщо хоч один
  /// відтінок вдалось розпізнати (виклику знадобиться перебудувати тему).
  static bool applyAccentShades(Map<String, dynamic> shades) {
    var changed = false;

    void apply(String key, void Function(Color) set) {
      final raw = shades[key];
      if (raw is! String) return;
      final hex = raw.replaceFirst('#', '');
      if (hex.length != 6) return;
      final value = int.tryParse(hex, radix: 16);
      if (value == null) return;
      set(Color(0xFF000000 | value));
      changed = true;
    }

    apply('200', (c) => gold200 = c);
    apply('300', (c) => gold300 = c);
    apply('400', (c) => gold400 = c);
    apply('500', (c) => gold500 = c);
    apply('600', (c) => gold600 = c);

    return changed;
  }
}

ThemeData buildAppTheme() {
  final displayFont = GoogleFonts.montserratTextTheme();
  final sansFont = GoogleFonts.manropeTextTheme();

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    // Прозорий, а не суцільний obsidian950 — щоб AuroraBackground
    // (підключений один раз у MaterialApp.builder) було видно крізь
    // кожен екран, а не лише на самому нижньому шарі.
    scaffoldBackgroundColor: Colors.transparent,
    colorScheme: ColorScheme.dark(
      surface: AppColors.obsidian900,
      primary: AppColors.gold400,
      secondary: AppColors.gold300,
      error: AppColors.ember500,
    ),
    textTheme: sansFont.apply(
      bodyColor: Colors.white.withValues(alpha: 0.85),
      displayColor: Colors.white,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.obsidian900.withValues(alpha: 0.6),
      elevation: 0,
      centerTitle: false,
      titleTextStyle: displayFont.titleLarge?.copyWith(
        color: Colors.white,
        fontWeight: FontWeight.w300,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.obsidian800,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.08)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.08)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: AppColors.gold400, width: 1.5),
      ),
      labelStyle: TextStyle(color: Colors.white.withValues(alpha: 0.5)),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.gold400,
        foregroundColor: AppColors.obsidian950,
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: displayFont.labelLarge?.copyWith(
          fontWeight: FontWeight.w600,
          letterSpacing: 0.5,
        ),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: AppColors.gold300),
    ),
    // Плавний fade+slide замість стандартного платформного переходу
    // (різкий на Android) — однаковий, передбачуваний рух між екранами
    // на обох платформах.
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {
        TargetPlatform.android: _FadeSlidePageTransitionsBuilder(),
        TargetPlatform.iOS: _FadeSlidePageTransitionsBuilder(),
      },
    ),
  );
}

/// Фізика пружини (SpringSimulation), не звичайна tween-крива — та сама
/// "тактильна" властивість, через яку преміальні застосунки (Telegram,
/// iOS-навігація) відчуваються "живими": рух ледь-ледь проскакує ціль і
/// плавно осідає, замість монотонного уповільнення easeOutCubic.
/// stiffness/damping підібрані під критично недодемпфовану пружину —
/// overshoot помітний, але без "желейного" бовтання (як у Curves.elasticOut).
class _SpringCurve extends Curve {
  const _SpringCurve();

  static final SpringSimulation _simulation = SpringSimulation(
    const SpringDescription(mass: 1, stiffness: 500, damping: 32),
    0,
    1,
    0,
  );

  @override
  double transformInternal(double t) => _simulation.x(t);
}

class _FadeSlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const _FadeSlidePageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    // Дві окремі криві: Opacity в Flutter суворо вимагає значення в
    // [0.0, 1.0] (assert), а недодемпфована пружина навмисно трохи
    // проскакує за 1.0 — тому spring-крива йде лише під зсув (Offset не
    // має такого обмеження), а прозорість веде звичайний монотонний easeOut.
    final fadeCurved = CurvedAnimation(parent: animation, curve: Curves.easeOut);
    final slideCurved = CurvedAnimation(parent: animation, curve: const _SpringCurve());
    return FadeTransition(
      opacity: fadeCurved,
      child: SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, 0.05), end: Offset.zero).animate(slideCurved),
        child: child,
      ),
    );
  }
}

/// Стиль "скляної панелі" з сайту (.glass-panel) — тонка золота обвідка,
/// напівпрозорий темний фон, м'яка тінь. М'якіша й "рідкіша" за
/// задумом: панель сидить поверх AuroraBackground, тому trasnparency
/// тут реально показує кольорове світіння знизу, а не просто чорноту.
BoxDecoration glassPanelDecoration({double radius = 20}) {
  return BoxDecoration(
    color: AppColors.obsidian900.withValues(alpha: 0.55),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
    boxShadow: [
      BoxShadow(
        color: AppColors.gold400.withValues(alpha: 0.10),
        blurRadius: 48,
        offset: const Offset(0, 14),
      ),
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.25),
        blurRadius: 20,
        offset: const Offset(0, 4),
      ),
    ],
  );
}

/// Справжній "рідке скло" — BackdropFilter-блюр того, що позаду
/// (аврора-фон, контент під час скролу), а не лише напівпрозорий колір.
/// Використовується там, де ефект найпомітніший: нижня навігація,
/// картка вітання на Кабінеті.
class LiquidGlass extends StatelessWidget {
  final Widget child;
  final double radius;
  final double blur;

  const LiquidGlass({super.key, required this.child, this.radius = 20, this.blur = 24});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          decoration: glassPanelDecoration(radius: radius),
          child: child,
        ),
      ),
    );
  }
}
