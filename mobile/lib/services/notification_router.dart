import 'package:flutter/material.dart';
import '../screens/admin_leave_requests_screen.dart';
import '../screens/admin_reports_screen.dart';
import '../screens/messenger/conversation_screen.dart';

/// Куди веде url зі сповіщення (push-даних чи запису в "дзвіночку") —
/// той самий рядок, що сервер кладе під кнопку в Telegram
/// (NotificationService::notify $telegramButton['url']) чи в url
/// мобільного месенджер-push, тепер розпізнається й тут. Сервер завжди
/// пише ШЛЯХ сайту (може бути повним URL з доменом — парсимо тільки
/// path), тому зіставлення береться з нього, а не з домену.
///
/// Повертає true, якщо перехід відбувся — інакше виклик має самостійно
/// вирішити запасний варіант (наприклад, відкрити список сповіщень).
bool _matches(String path, String prefix) => path == prefix || path.startsWith('$prefix/');

Future<bool> openNotificationTarget(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return false;

  final path = Uri.tryParse(url)?.path ?? url;

  if (_matches(path, '/messenger')) {
    final id = int.tryParse(path.replaceFirst('/messenger/', ''));
    if (id != null) {
      await Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => ConversationScreen(conversationId: id)));
      return true;
    }
    return false;
  }

  if (_matches(path, '/admin/reports')) {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AdminReportsScreen()));
    return true;
  }

  // /admin/members — та сама сторінка на сайті, де адмін бачить і
  // заявки на відпустку; у застосунку для цього є окремий, зручніший
  // екран (список саме заявок на розгляді), тож ведемо туди.
  if (_matches(path, '/admin/members')) {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AdminLeaveRequestsScreen()));
    return true;
  }

  return false;
}
