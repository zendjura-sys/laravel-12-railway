import 'package:flutter/material.dart';
import 'island_top_bar.dart';

/// Нижній острівець режиму вибору (Мої звіти, Сповіщення): "Видалити всі"
/// і "Видалити (N)". З'являється лише після натискання кнопки видалення
/// у шапці — у звичайному режимі списку його немає.
class SelectionDeleteBar extends StatelessWidget {
  final int selectedCount;
  final VoidCallback? onDeleteAll;
  final VoidCallback? onDeleteSelected;
  final bool busy;

  const SelectionDeleteBar({
    super.key,
    required this.selectedCount,
    required this.onDeleteAll,
    required this.onDeleteSelected,
    this.busy = false,
  });

  static const _red = Color(0xFFF87171);

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      child: GlassIsland(
        borderRadius: BorderRadius.circular(36),
        padding: const EdgeInsets.all(10),
        child: Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: busy ? null : onDeleteAll,
                  icon: const Icon(Icons.delete_sweep_outlined),
                  label: const Text('Видалити всі'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _red,
                    side: BorderSide(color: _red.withValues(alpha: 0.5)),
                    shape: const StadiumBorder(),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: SizedBox(
                height: 52,
                child: FilledButton.icon(
                  onPressed: busy || selectedCount == 0 ? null : onDeleteSelected,
                  icon: busy
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.delete_outline),
                  label: Text(selectedCount == 0 ? 'Видалити' : 'Видалити ($selectedCount)'),
                  style: FilledButton.styleFrom(
                    backgroundColor: _red.withValues(alpha: 0.85),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.white.withValues(alpha: 0.06),
                    disabledForegroundColor: Colors.white38,
                    shape: const StadiumBorder(),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Підтвердження видалення — однакове для обох екранів.
Future<bool> confirmDeletion(BuildContext context, {required String title, required String message}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Скасувати')),
        TextButton(
          onPressed: () => Navigator.of(context).pop(true),
          child: const Text('Видалити', style: TextStyle(color: SelectionDeleteBar._red)),
        ),
      ],
    ),
  );
  return ok == true;
}

/// Кружечок вибору біля елемента списку в режимі вибору.
class SelectionMark extends StatelessWidget {
  final bool selected;
  final bool enabled;

  const SelectionMark({super.key, required this.selected, this.enabled = true});

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      width: 24,
      height: 24,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: selected ? SelectionDeleteBar._red : Colors.transparent,
        border: Border.all(
          color: !enabled
              ? Colors.white12
              : selected
                  ? SelectionDeleteBar._red
                  : Colors.white38,
          width: 2,
        ),
      ),
      child: selected ? const Icon(Icons.check, size: 16, color: Colors.white) : null,
    );
  }
}
