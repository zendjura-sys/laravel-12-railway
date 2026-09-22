import 'package:flutter/material.dart';

/// Статус учасника з сервера (App\\Support\\Presence::payload):
/// {online, emoji: 🟢/🔴, label: "у мережі" / "був(-ла) у мережі …"}.
/// Текст готує сервер — однаково на сайті й у застосунку, з правильним
/// родом ("був"/"була") і часом за Києвом.
class PresenceLabel extends StatelessWidget {
  final Map<String, dynamic>? presence;
  final double fontSize;
  final bool showEmoji;

  const PresenceLabel({super.key, required this.presence, this.fontSize = 12, this.showEmoji = true});

  static String emojiOf(Map<String, dynamic>? presence) => presence?['online'] == true ? '🟢' : '🔴';

  @override
  Widget build(BuildContext context) {
    final online = presence?['online'] == true;
    final label = presence?['label'] as String? ?? 'не в мережі';
    return Text(
      showEmoji ? '${emojiOf(presence)} $label' : label,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        fontSize: fontSize,
        color: online ? const Color(0xFF34D399) : Colors.white54,
        fontWeight: FontWeight.w400,
      ),
    );
  }
}
