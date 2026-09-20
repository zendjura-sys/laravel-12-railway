import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

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

  Future<Map<String, dynamic>> dashboard() async {
    final response =
        await http.get(_uri('/dashboard'), headers: await _headers(auth: true));
    return _decode(response) as Map<String, dynamic>;
  }
}
