# Бизнес Магнат — Android (.NET, C#)

Версия игры без Unity: нативное Android-приложение на C# (.NET 10 for Android). APK собирается в GitHub Actions без лицензий, аккаунтов и секретов.

## Как получить APK

1. Любой push в эту папку (или ручной запуск: **Actions → Android APK (.NET) → Run workflow**) запускает сборку.
2. Через ~10 минут APK появится:
   - в разделе **Releases → «Бизнес Магнат — последняя сборка»** (тег `apk-latest`). Удобнее всего открыть эту страницу прямо с телефона и скачать `BusinessTycoon.apk`;
   - во вкладке **Actions → запуск → Artifacts → BusinessTycoon-apk** (zip-архив).
3. Установите APK. Android попросит разрешить установку из неизвестных источников.

Требования: Android 8.0 и новее.

## Устройство проекта

```
BusinessTycoonAndroid/
├─ BusinessTycoon.Android.csproj  — проект .NET for Android (net10.0-android)
├─ MainActivity.cs                — игровой цикл, сохранение, офлайн-доход, кнопка «Назад»
├─ SaveStore.cs                   — сохранение в SharedPreferences (JSON)
├─ UI/                            — интерфейс из кода: Ui (палитра, 3D-кнопки), Shell, 6 экранов
└─ Resources/                     — тема, цвета, иконка приложения (векторная)
```

Игровая логика (экономика, биржа, опционы, компании, банк) общая с Unity-версией: файлы
`BusinessTycoon/Assets/Scripts/Core/*.cs` подключаются в проект ссылкой, копий нет.

## Локальная сборка (по желанию)

```bash
dotnet workload install android
dotnet publish BusinessTycoonAndroid/BusinessTycoon.Android.csproj -c Release -f net10.0-android -o out
# готовый файл: out/com.tycoongames.bizmagnat-Signed.apk
```
