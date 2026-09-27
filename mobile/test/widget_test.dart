import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:monsory_connect/main.dart';

void main() {
  // flutter_secure_storage говорить із платформою через MethodChannel, якого
  // немає в тестовому середовищі (голий Dart VM) — без мока read() просто
  // висить, і pumpAndSettle ніколи не завершується.
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      channel,
      (MethodCall methodCall) async => null,
    );
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  // flutter_test підміняє HttpClient так, що будь-який запит повертає 400
  // (див. попередження "creates an HttpClient" у виводі test-раннера) — тож
  // /api/app-config тут завжди "падає" і застосунок стартує з екрана
  // "немає звʼязку", а не з логіну. Це чесно перевіряє саме цю гілку
  // _StartupGate; повний happy-path (логін після успішного app-config)
  // вимагав би мокати сам HttpClient, а не лише secure storage.
  testWidgets(
      'App shows a connection-error screen when /api/app-config is unreachable',
      (WidgetTester tester) async {
    await tester.pumpWidget(const MonsoryConnectApp());
    await tester.pumpAndSettle();

    expect(find.text('Немає звʼязку з сервером'), findsOneWidget);
    expect(
        find.widgetWithText(TextButton, 'Перевірити ще раз'), findsOneWidget);
  });
}
