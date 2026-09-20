import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Та сама палітра, що й на сайті (tailwind.config.js): obsidian — фон,
/// gold — акцент. Значення взяті буквально з CSS-змінних сайту, щоб
/// застосунок виглядав як продовження одного бренду, а не окремий проєкт.
class AppColors {
  static const obsidian950 = Color(0xFF060605);
  static const obsidian900 = Color(0xFF0B0A09);
  static const obsidian800 = Color(0xFF141210);
  static const obsidian700 = Color(0xFF1E1B18);

  static const gold200 = Color(0xFFF1E2B8);
  static const gold300 = Color(0xFFE4CD8F);
  static const gold400 = Color(0xFFD4AF37);
  static const gold500 = Color(0xFFC9A24B);
  static const gold600 = Color(0xFFA9822F);

  static const ember500 = Color(0xFFC4381F);
}

ThemeData buildAppTheme() {
  final displayFont = GoogleFonts.montserratTextTheme();
  final sansFont = GoogleFonts.manropeTextTheme();

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: AppColors.obsidian950,
    colorScheme: const ColorScheme.dark(
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
        borderSide: const BorderSide(color: AppColors.gold400, width: 1.5),
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
  );
}

/// Стиль "скляної панелі" з сайту (.glass-panel) — тонка золота обвідка,
/// напівпрозорий темний фон, м'яка тінь.
BoxDecoration glassPanelDecoration({double radius = 20}) {
  return BoxDecoration(
    color: AppColors.obsidian900.withValues(alpha: 0.6),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
    boxShadow: [
      BoxShadow(
        color: AppColors.gold400.withValues(alpha: 0.08),
        blurRadius: 40,
        offset: const Offset(0, 10),
      ),
    ],
  );
}
