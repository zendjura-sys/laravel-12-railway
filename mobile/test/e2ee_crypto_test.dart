import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:flutter_test/flutter_test.dart';

/// Перевіряє сам крипто-примітив (X25519 ECDH + HKDF + AES-256-GCM),
/// той самий, що використовує E2eeService — без мережевої частини
/// (публікація/отримання ключів через ApiClient), яку тут не підняти
/// без бекенда. Мета — впевнитись, що застосування API пакета
/// cryptography коректне: спільний секрет справді симетричний для обох
/// сторін, а зашифроване одним боком читається іншим.
Future<SecretKey> _deriveSharedKey(SimpleKeyPair myKeyPair, SimplePublicKey theirPublicKey) async {
  final algorithm = X25519();
  final rawShared = await algorithm.sharedSecretKey(keyPair: myKeyPair, remotePublicKey: theirPublicKey);
  final hkdf = Hkdf(hmac: Hmac.sha256(), outputLength: 32);
  return hkdf.deriveKey(secretKey: rawShared, info: utf8.encode('monsory-connect-e2ee-v1'));
}

void main() {
  test('X25519 ECDH shared secret is symmetric for both sides', () async {
    final algorithm = X25519();
    final alice = await algorithm.newKeyPair();
    final bob = await algorithm.newKeyPair();

    final aliceShared = await _deriveSharedKey(alice, await bob.extractPublicKey());
    final bobShared = await _deriveSharedKey(bob, await alice.extractPublicKey());

    expect(await aliceShared.extractBytes(), equals(await bobShared.extractBytes()));
  });

  test('AES-256-GCM round-trip: Bob decrypts what Alice encrypted', () async {
    final algorithm = X25519();
    final alice = await algorithm.newKeyPair();
    final bob = await algorithm.newKeyPair();

    final aliceShared = await _deriveSharedKey(alice, await bob.extractPublicKey());
    final bobShared = await _deriveSharedKey(bob, await alice.extractPublicKey());

    final aesGcm = AesGcm.with256bits();
    const plaintext = 'Привіт, це секретне повідомлення 🔒';

    final nonce = aesGcm.newNonce();
    final secretBox = await aesGcm.encrypt(utf8.encode(plaintext), secretKey: aliceShared, nonce: nonce);
    final packed = base64Encode([...secretBox.nonce, ...secretBox.mac.bytes, ...secretBox.cipherText]);

    // Розпакування точно так, як у E2eeService.decryptFrom.
    final bytes = base64Decode(packed);
    final unpackedNonce = bytes.sublist(0, 12);
    final unpackedMac = Mac(bytes.sublist(12, 28));
    final unpackedCipherText = bytes.sublist(28);

    final clear = await aesGcm.decrypt(
      SecretBox(unpackedCipherText, nonce: unpackedNonce, mac: unpackedMac),
      secretKey: bobShared,
    );

    expect(utf8.decode(clear), equals(plaintext));
  });

  test('AES-256-GCM decrypt fails with wrong key (tampered/foreign message)', () async {
    final algorithm = X25519();
    final alice = await algorithm.newKeyPair();
    final bob = await algorithm.newKeyPair();
    final mallory = await algorithm.newKeyPair();

    final aliceShared = await _deriveSharedKey(alice, await bob.extractPublicKey());
    final malloryShared = await _deriveSharedKey(mallory, await bob.extractPublicKey());

    final aesGcm = AesGcm.with256bits();
    final nonce = aesGcm.newNonce();
    final secretBox = await aesGcm.encrypt(utf8.encode('secret'), secretKey: aliceShared, nonce: nonce);

    expect(
      () => aesGcm.decrypt(secretBox, secretKey: malloryShared),
      throwsA(isA<SecretBoxAuthenticationError>()),
    );
  });

  test('newKeyPairFromSeed reproduces the same key pair (persisted private key)', () async {
    final algorithm = X25519();
    final original = await algorithm.newKeyPair();
    final seed = await original.extractPrivateKeyBytes();

    final restored = await algorithm.newKeyPairFromSeed(seed);

    final originalPublic = await original.extractPublicKey();
    final restoredPublic = await restored.extractPublicKey();
    expect(restoredPublic.bytes, equals(originalPublic.bytes));
  });
}
