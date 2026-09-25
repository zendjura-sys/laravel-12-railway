/// Номер збірки застосунку — CI (.github/workflows/mobile-build.yml)
/// підставляє сюди github.run_number через --dart-define=BUILD_NUMBER=...
/// на кожному релізі, а не хтось вручну редагує це число. "1" тут —
/// лише запасний варіант для локальної збірки без цього define (вона й
/// неможлива в цьому середовищі, але aналіз/тести все одно мають
/// компілюватись). Порівнюється з mobile_app_min_build (жорсткий блок) і
/// mobile_app_latest_build (м'яка пропозиція оновитись) із
/// Admin → Налаштування → Мобільний застосунок.
const int currentBuildNumber =
    int.fromEnvironment('BUILD_NUMBER', defaultValue: 1);
