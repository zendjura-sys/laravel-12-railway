import 'dart:async';
import 'package:flutter/foundation.dart';
import '../app_info.dart';
import '../update_prompt.dart';

/// Фонова перевірка нової версії — без перезапуску застосунку: одразу при
/// вході, далі раз на 5 хв і щоразу, коли застосунок повертається на екран.
/// Коли на сервері з'являється новіша збірка, верхня панель показує
/// оголошення (як закріплене повідомлення в Telegram), а не модальне вікно
/// посеред роботи.
class UpdateNotifier extends ChangeNotifier {
  UpdateNotifier._();
  static final instance = UpdateNotifier._();

  int? availableBuild;
  String? message;
  String? downloadUrl;

  int _configLatestBuild = 0;
  String? _configDownloadUrl;
  Timer? _timer;
  bool _checking = false;

  void start({int configLatestBuild = 0, String? configMessage, String? configDownloadUrl}) {
    _configLatestBuild = configLatestBuild;
    _configDownloadUrl = configDownloadUrl;
    message = configMessage;
    check();
    _timer ??= Timer.periodic(const Duration(minutes: 5), (_) => check());
  }

  Future<void> check() async {
    if (_checking) return;
    _checking = true;
    try {
      final remote = await fetchLatestBuild();
      var latest = _configLatestBuild;
      var url = _configDownloadUrl;
      if (remote != null && remote.build > latest) {
        latest = remote.build;
        url = remote.downloadUrl;
      }

      final dismissed = await dismissedUpdateBuild();
      final next = latest > currentBuildNumber && latest > dismissed ? latest : null;
      if (next != availableBuild || url != downloadUrl) {
        availableBuild = next;
        downloadUrl = url;
        notifyListeners();
      }
    } finally {
      _checking = false;
    }
  }

  Future<void> dismiss() async {
    final build = availableBuild;
    if (build == null) return;
    await dismissUpdateBuild(build);
    availableBuild = null;
    notifyListeners();
  }
}

/// Бейдж дзвіночка у верхній панелі: оновлює HomeShell (раз на хвилину,
/// при поверненні в застосунок і після закриття екрана сповіщень) і
/// Кабінет — при власному завантаженні.
class AppBadges {
  static final unreadNotifications = ValueNotifier<int>(0);
}
