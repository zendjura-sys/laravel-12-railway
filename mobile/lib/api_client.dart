import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

/// Помилка API з людяним повідомленням (ValidationException з Laravel
/// приходить як {message, errors} — саме message і показуємо користувачу).
class ApiException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, dynamic>? body;

  ApiException(this.statusCode, this.message, [this.body]);

  @override
  String toString() => message;
}

/// Базова адреса бекенду. У релізі підмінюється через
/// --dart-define=API_BASE_URL=https://monsory.net (див. GitHub Actions
/// workflow) — за замовчуванням продакшн-домен, щоб debug-збірка теж
/// одразу працювала проти реального сайту.
const _defaultBaseUrl = 'https://monsory.net';

class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _defaultBaseUrl,
  );

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auth_token';

  Future<String?> get token => _storage.read(key: _tokenKey);

  Future<void> _saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Uri _uri(String path) => Uri.parse('$baseUrl/api$path');

  Future<Map<String, String>> _headers({bool auth = false}) async {
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (auth) {
      final t = await token;
      if (t != null) headers['Authorization'] = 'Bearer $t';
    }
    return headers;
  }

  dynamic _decode(http.Response response) {
    final body = response.body.isEmpty ? {} : jsonDecode(response.body);
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }
    final message = (body is Map && body['message'] is String)
        ? body['message'] as String
        : 'Помилка сервера (${response.statusCode})';
    throw ApiException(response.statusCode, message,
        body is Map<String, dynamic> ? body : null);
  }

  /// Реєстрація "за себе" — той самий /api/register, що описаний вище.
  /// Тіньовий акаунт (звіт "за друга" до реєстрації) тут не підхоплюється —
  /// бекенд поверне помилку з проханням завершити реєстрацію на сайті.
  Future<Map<String, dynamic>> register({
    required String firstName,
    String? lastName,
    required String email,
    required String password,
    required String passwordConfirmation,
    required String deviceName,
  }) async {
    final response = await http.post(
      _uri('/register'),
      headers: await _headers(),
      body: jsonEncode({
        'first_name': firstName,
        if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'device_name': deviceName,
      }),
    );
    final data = _decode(response) as Map<String, dynamic>;
    if (data['token'] != null) {
      await _saveToken(data['token'] as String);
    }
    return data;
  }

  /// Крок 1 логіну. Якщо на акаунті увімкнено 2FA — повертає
  /// {requiresTwoFactor: true, challengeToken}, інакше одразу токен.
  Future<Map<String, dynamic>> login(
      String email, String password, String deviceName) async {
    final response = await http.post(
      _uri('/login'),
      headers: await _headers(),
      body: jsonEncode(
          {'email': email, 'password': password, 'device_name': deviceName}),
    );
    final data = _decode(response) as Map<String, dynamic>;
    if (data['token'] != null) {
      await _saveToken(data['token'] as String);
    }
    return data;
  }

  Future<Map<String, dynamic>> loginTwoFactor({
    required String challengeToken,
    String? code,
    String? recoveryCode,
    required String deviceName,
  }) async {
    final response = await http.post(
      _uri('/login/two-factor'),
      headers: await _headers(),
      body: jsonEncode({
        'challenge_token': challengeToken,
        if (code != null) 'code': code,
        if (recoveryCode != null) 'recovery_code': recoveryCode,
        'device_name': deviceName,
      }),
    );
    final data = _decode(response) as Map<String, dynamic>;
    await _saveToken(data['token'] as String);
    return data;
  }

  Future<void> logout() async {
    try {
      final response =
          await http.post(_uri('/logout'), headers: await _headers(auth: true));
      _decode(response);
    } finally {
      await clearToken();
    }
  }

  Future<Map<String, dynamic>> me() async {
    final response =
        await http.get(_uri('/me'), headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }

  /// Публічний (без токена) конфіг застосунку — режим обслуговування,
  /// примусове оновлення, які вкладки показувати. Читається ДО логіну.
  Future<Map<String, dynamic>> appConfig() async {
    final response =
        await http.get(_uri('/app-config'), headers: await _headers());
    return _decode(response) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> dashboard() async {
    final response =
        await http.get(_uri('/dashboard'), headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }

  // ---------------- Банк (модуль Bonuses) ----------------

  Future<Map<String, dynamic>> bank() async {
    final response =
        await http.get(_uri('/bank'), headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }

  Future<List<dynamic>> searchRecipients(String query) async {
    final response = await http.get(
      _uri('/bank/recipients/search').replace(queryParameters: {'q': query}),
      headers: await _headers(auth: true),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return data['members'] as List<dynamic>;
  }

  Future<void> transfer(
      {required int recipientId, required int amount, String? note}) async {
    final response = await http.post(
      _uri('/bank/transfer'),
      headers: await _headers(auth: true),
      body: jsonEncode({
        'recipient_id': recipientId,
        'amount': amount,
        if (note != null && note.isNotEmpty) 'note': note
      }),
    );
    _decode(response);
  }

  Future<void> openDeposit(int amount) async {
    final response = await http.post(
      _uri('/bank/deposits'),
      headers: await _headers(auth: true),
      body: jsonEncode({'amount': amount}),
    );
    _decode(response);
  }

  Future<void> withdrawDeposit(int depositId) async {
    final response = await http.post(
      _uri('/bank/deposits/$depositId/withdraw'),
      headers: await _headers(auth: true),
    );
    _decode(response);
  }

  Future<void> requestCash(int amount) async {
    final response = await http.post(
      _uri('/bank/cash-requests'),
      headers: await _headers(auth: true),
      body: jsonEncode({'amount': amount}),
    );
    _decode(response);
  }

  // ---------------- Прогрес і рейтинг (модуль Progression) ----------------

  Future<Map<String, dynamic>> progress() async {
    final response =
        await http.get(_uri('/progress'), headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> leaderboard({String? category}) async {
    final uri = category != null
        ? _uri('/leaderboard').replace(queryParameters: {'category': category})
        : _uri('/leaderboard');
    final response = await http.get(uri, headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }

  // ---------------- Звіти (модуль Reports) ----------------

  Future<List<dynamic>> reports() async {
    final response =
        await http.get(_uri('/reports'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['reports'] as List<dynamic>;
  }

  /// multipart/form-data — те саме подання "за себе", що й на сайті,
  /// просто без "за друга" (v1 застосунку). [fields] містить лише
  /// непорожні значення (порожній 'report_date' на бізварі/контракті
  /// зламав би валідацію 'required_if').
  Future<void> submitReport({
    required Map<String, String> fields,
    List<String> kaptTimes = const [],
    List<XFile> photos = const [],
  }) async {
    final token = await this.token;
    final request = http.MultipartRequest('POST', _uri('/reports'))
      ..headers['Accept'] = 'application/json'
      ..headers['Authorization'] = 'Bearer $token'
      ..fields.addAll(fields);

    for (var i = 0; i < kaptTimes.length; i++) {
      request.fields['kapt_times[$i]'] = kaptTimes[i];
    }
    for (var i = 0; i < photos.length; i++) {
      request.files
          .add(await http.MultipartFile.fromPath('photos[$i]', photos[i].path));
    }

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    _decode(response);
  }

  // ---------------- Telegram (модуль Telegram Bot) ----------------

  Future<Map<String, dynamic>> telegramStatus() async {
    final response = await http.get(_uri('/telegram/status'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['data'] as Map<String, dynamic>;
  }

  /// Повертає {code, bot_username} — застосунок сам будує
  /// https://t.me/{bot}?start={code} і відкриває його url_launcher'ом.
  Future<Map<String, dynamic>> telegramGenerateCode() async {
    final response = await http.post(_uri('/telegram/generate-code'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['data'] as Map<String, dynamic>;
  }

  Future<void> telegramUnlink() async {
    final response = await http.post(_uri('/telegram/unlink'), headers: await _headers(auth: true));
    _decode(response);
  }

  // ---------------- Адмінка (лише ядро, без аддонів) ----------------

  Future<List<dynamic>> adminStats() async {
    final response = await http.get(_uri('/admin/stats'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['stats'] as List<dynamic>;
  }

  Future<List<dynamic>> adminUsers({String query = ''}) async {
    final uri = query.isEmpty
        ? _uri('/admin/users')
        : _uri('/admin/users').replace(queryParameters: {'q': query});
    final response = await http.get(uri, headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['users'] as List<dynamic>;
  }

  // ---------------- Месенджер (модуль Messenger) ----------------

  Future<List<dynamic>> messengerConversations() async {
    final response = await http.get(_uri('/messenger'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['conversations'] as List<dynamic>;
  }

  Future<Map<String, dynamic>> messengerConversation(int id) async {
    final response = await http.get(_uri('/messenger/$id'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return data['data'] as Map<String, dynamic>;
  }

  Future<List<dynamic>> messengerMessagesSince(int conversationId, int afterId) async {
    final response = await http.get(
      _uri('/messenger/$conversationId/messages').replace(queryParameters: {'after_id': '$afterId'}),
      headers: await _headers(auth: true),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['messages'] as List<dynamic>;
  }

  Future<Map<String, dynamic>> sendMessengerText(int conversationId, String body) async {
    final response = await http.post(
      _uri('/messenger/$conversationId/messages'),
      headers: await _headers(auth: true),
      body: jsonEncode({'type': 'text', 'body': body}),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['message'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> sendMessengerGif(int conversationId, String gifUrl) async {
    final response = await http.post(
      _uri('/messenger/$conversationId/messages'),
      headers: await _headers(auth: true),
      body: jsonEncode({'type': 'gif', 'gif_url': gifUrl}),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['message'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> sendMessengerSticker(int conversationId, int stickerId) async {
    final response = await http.post(
      _uri('/messenger/$conversationId/messages'),
      headers: await _headers(auth: true),
      body: jsonEncode({'type': 'sticker', 'sticker_id': stickerId}),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['message'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> sendMessengerPhoto(int conversationId, XFile photo, {String? caption}) async {
    final token = await this.token;
    final request = http.MultipartRequest('POST', _uri('/messenger/$conversationId/messages'))
      ..headers['Accept'] = 'application/json'
      ..headers['Authorization'] = 'Bearer $token'
      ..fields['type'] = 'photo';
    if (caption != null && caption.isNotEmpty) request.fields['body'] = caption;
    request.files.add(await http.MultipartFile.fromPath('photo', photo.path));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['message'] as Map<String, dynamic>;
  }

  Future<List<dynamic>> searchMessengerMembers(String query) async {
    final response = await http.get(
      _uri('/messenger/members/search').replace(queryParameters: {'q': query}),
      headers: await _headers(auth: true),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['members'] as List<dynamic>;
  }

  Future<int> startMessengerDirect(int targetUserId) async {
    final response = await http.post(_uri('/messenger/direct/$targetUserId'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['conversationId'] as int;
  }

  Future<List<dynamic>> messengerStickers() async {
    final response = await http.get(_uri('/messenger/stickers'), headers: await _headers(auth: true));
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['stickers'] as List<dynamic>;
  }

  Future<Map<String, dynamic>> uploadMessengerSticker(XFile image) async {
    final token = await this.token;
    final request = http.MultipartRequest('POST', _uri('/messenger/stickers'))
      ..headers['Accept'] = 'application/json'
      ..headers['Authorization'] = 'Bearer $token';
    request.files.add(await http.MultipartFile.fromPath('image', image.path));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['sticker'] as Map<String, dynamic>;
  }

  Future<void> deleteMessengerSticker(int id) async {
    final response = await http.delete(_uri('/messenger/stickers/$id'), headers: await _headers(auth: true));
    _decode(response);
  }

  Future<List<dynamic>> searchGifs(String query) async {
    final response = await http.get(
      _uri('/messenger/gifs/search').replace(queryParameters: {'q': query}),
      headers: await _headers(auth: true),
    );
    final data = _decode(response) as Map<String, dynamic>;
    return (data['data'] as Map<String, dynamic>)['gifs'] as List<dynamic>;
  }
}
