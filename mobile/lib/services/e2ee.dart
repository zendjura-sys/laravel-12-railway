import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api_client.dart';

/// Наскрізне шифрування — Фаза 1: лише особисті (direct) чати,
/// мобільний-мобільний. X25519 (статичний ключ на пристрій, без
/// повноцінного Double Ratchet, як у Signal) + AES-256-GCM на кожне
/// повідомлення. Сервер бачить лише base64(nonce+mac+ciphertext) у
/// полі body при type=text_e2ee — розшифрувати не може ніхто, крім
/// двох сторін розмови.
///
/// Приватний ключ ніколи не покидає пристрій (flutter_secure_storage).
/// Перевстановлення застосунку чи вхід на новому пристрої генерує нову
/// пару й перезаписує публічний ключ на сервері — стара історія
/// особистих чатів після цього нечитабельна. Це свідомий компроміс
/// Фази 1 (без окремого бекапу ключів) — так само явно узгоджений
/// заздалегідь, як і межі Фази 1 (сімейний чат і чат заступників не
/// шифруються, веб-клієнт теж).
class E2eeService {
  E2eeService._();
  static final E2eeService instance = E2eeService._();

  static const _privateKeyStorageKey = 'e2ee_x25519_private_key';

  final _storage = const FlutterSecureStorage();
  final _algorithm = X25519();
  final _aesGcm = AesGcm.with256bits();

  SimpleKeyPair? _keyPair;
  final Map<int, String?> _peerPublicKeys = {};
  final Map<int, SecretKey> _sharedKeys = {};

  /// Готує власну пару ключів (генерує за потреби) і публікує публічний
  /// ключ на сервері. Викликається один раз при вході — тиха невдача
  /// (мережа підвела) не повинна ламати решту застосунку: наступний
  /// відкритий особистий чат просто спробує ще раз.
  Future<void> ensureReady() async {
    try {
      final keyPair = await _loadOrCreateKeyPair();
      final publicKey = await keyPair.extractPublicKey();
      await ApiClient.instance.publishIdentityKey(base64Encode(publicKey.bytes));
    } catch (_) {
      // Немає мережі саме зараз чи щось інше — спробуємо знову наступного
      // ensureReady() (наступний запуск/вхід).
    }
  }

  Future<SimpleKeyPair> _loadOrCreateKeyPair() async {
    if (_keyPair != null) return _keyPair!;

    final stored = await _storage.read(key: _privateKeyStorageKey);
    if (stored != null) {
      final seed = base64Decode(stored);
      _keyPair = await _algorithm.newKeyPairFromSeed(seed);
      return _keyPair!;
    }

    final keyPair = await _algorithm.newKeyPair();
    final seed = await keyPair.extractPrivateKeyBytes();
    await _storage.write(key: _privateKeyStorageKey, value: base64Encode(seed));
    _keyPair = keyPair;
    return keyPair;
  }

  /// Спільний AES-ключ для розмови з конкретним користувачем — виводиться
  /// один раз (ECDH статика-статика, той самий результат для обох сторін
  /// незалежно від напрямку) і кешується в пам'яті на сесію застосунку.
  /// null, якщо співрозмовник ще не опублікував свій ключ (старіша версія
  /// застосунку чи він ще жодного разу не заходив у месенджер) — тоді
  /// виклик сам вирішує, що робити (типово — надіслати нешифрованим).
  Future<SecretKey?> _sharedKeyWith(int otherUserId) async {
    final cached = _sharedKeys[otherUserId];
    if (cached != null) return cached;

    if (!_peerPublicKeys.containsKey(otherUserId)) {
      try {
        _peerPublicKeys[otherUserId] = await ApiClient.instance.fetchIdentityKey(otherUserId);
      } catch (_) {
        _peerPublicKeys[otherUserId] = null;
      }
    }
    final peerKeyBase64 = _peerPublicKeys[otherUserId];
    if (peerKeyBase64 == null) return null;

    final keyPair = await _loadOrCreateKeyPair();
    final peerPublicKey = SimplePublicKey(base64Decode(peerKeyBase64), type: KeyPairType.x25519);
    final rawSharedSecret = await _algorithm.sharedSecretKey(keyPair: keyPair, remotePublicKey: peerPublicKey);

    final hkdf = Hkdf(hmac: Hmac.sha256(), outputLength: 32);
    final derived = await hkdf.deriveKey(
      secretKey: rawSharedSecret,
      info: utf8.encode('monsory-connect-e2ee-v1'),
    );

    _sharedKeys[otherUserId] = derived;
    return derived;
  }

  /// Скидає кеш для користувача — на випадок, якщо він перевстановив
  /// застосунок і опублікував новий публічний ключ; наступна спроба
  /// шифрування/дешифрування підтягне його заново.
  void invalidate(int otherUserId) {
    _peerPublicKeys.remove(otherUserId);
    _sharedKeys.remove(otherUserId);
  }

  /// null — коли ключ співрозмовника недоступний: викликач тоді сам
  /// вирішує, надсилати нешифрованим чи показати помилку.
  Future<String?> encryptFor(int otherUserId, String plaintext) async {
    final key = await _sharedKeyWith(otherUserId);
    if (key == null) return null;

    final nonce = _aesGcm.newNonce();
    final secretBox = await _aesGcm.encrypt(utf8.encode(plaintext), secretKey: key, nonce: nonce);

    return base64Encode([...secretBox.nonce, ...secretBox.mac.bytes, ...secretBox.cipherText]);
  }

  /// null — не вдалось розшифрувати (чужий/застарілий ключ, пошкоджені
  /// дані) — UI показує заглушку замість падіння.
  Future<String?> decryptFrom(int otherUserId, String packedBase64) async {
    try {
      final key = await _sharedKeyWith(otherUserId);
      if (key == null) return null;

      final bytes = base64Decode(packedBase64);
      if (bytes.length < 12 + 16) return null;
      final nonce = bytes.sublist(0, 12);
      final mac = Mac(bytes.sublist(12, 28));
      final cipherText = bytes.sublist(28);

      final clear = await _aesGcm.decrypt(
        SecretBox(cipherText, nonce: nonce, mac: mac),
        secretKey: key,
      );
      return utf8.decode(clear);
    } catch (_) {
      return null;
    }
  }
}
