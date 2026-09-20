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

  testWidgets('App boots to the login screen when no token is stored',
      (WidgetTester tester) async {
    await tester.pumpWidget(const MonsoryConnectApp());
    await tester.pumpAndSettle();

    expect(find.text('Connect'), findsOneWidget);
    expect(find.widgetWithText(ElevatedButton, 'УВІЙТИ'), findsOneWidget);
  });
}
