import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

const _kaptTimes = [
  '10:00',
  '11:00',
  '12:00',
  '13:00',
  '14:00',
  '15:00',
  '16:00',
  '17:00',
  '18:00',
  '19:00',
  '20:00',
  '21:00',
  '22:00',
];

class ReportSubmitScreen extends StatefulWidget {
  const ReportSubmitScreen({super.key});

  @override
  State<ReportSubmitScreen> createState() => _ReportSubmitScreenState();
}

class _ReportSubmitScreenState extends State<ReportSubmitScreen> {
  String _type = 'bizwar';
  DateTime _reportDate = DateTime.now();
  final _winsController = TextEditingController(text: '0');
  final _lossesController = TextEditingController(text: '0');
  final Set<String> _selectedKaptTimes = {};
  final _lightController = TextEditingController(text: '0');
  final _mediumController = TextEditingController(text: '0');
  final _heavyController = TextEditingController(text: '0');
  final _amountController = TextEditingController();
  final _descriptionController = TextEditingController();
  final List<XFile> _photos = [];

  bool _loading = false;
  String? _error;

  Future<void> _pickPhotos() async {
    final picked = await ImagePicker().pickMultiImage(imageQuality: 90);
    if (picked.isNotEmpty) setState(() => _photos.addAll(picked));
  }

  Future<void> _takePhoto() async {
    final shot = await ImagePicker()
        .pickImage(source: ImageSource.camera, imageQuality: 90);
    if (shot != null) setState(() => _photos.add(shot));
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _reportDate,
      firstDate: DateTime.now().subtract(const Duration(days: 60)),
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _reportDate = picked);
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final fields = <String, String>{'type': _type};
      final dateStr =
          '${_reportDate.year.toString().padLeft(4, '0')}-${_reportDate.month.toString().padLeft(2, '0')}-${_reportDate.day.toString().padLeft(2, '0')}';

      switch (_type) {
        case 'bizwar':
          fields['report_date'] = dateStr;
          fields['wins_count'] = _winsController.text.trim().isEmpty
              ? '0'
              : _winsController.text.trim();
          fields['losses_count'] = _lossesController.text.trim().isEmpty
              ? '0'
              : _lossesController.text.trim();
          break;
        case 'contract':
          fields['report_date'] = dateStr;
          fields['light_count'] = _lightController.text.trim().isEmpty
              ? '0'
              : _lightController.text.trim();
          fields['medium_count'] = _mediumController.text.trim().isEmpty
              ? '0'
              : _mediumController.text.trim();
          fields['heavy_count'] = _heavyController.text.trim().isEmpty
              ? '0'
              : _heavyController.text.trim();
          break;
        case 'investment':
          fields['amount'] = _amountController.text.trim().isEmpty
              ? '0'
              : _amountController.text.trim();
          break;
      }
      if (_descriptionController.text.trim().isNotEmpty) {
        fields['description'] = _descriptionController.text.trim();
      }

      await ApiClient.instance.submitReport(
        fields: fields,
        kaptTimes: _type == 'bizwar' ? _selectedKaptTimes.toList() : const [],
        photos: _photos,
      );

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося надіслати звіт.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const IslandAppBar(title: 'Новий звіт'),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Wrap(
            spacing: 8,
            children: [
              _TypeChip(
                  label: 'Бізвар',
                  value: 'bizwar',
                  groupValue: _type,
                  onSelected: (v) => setState(() => _type = v)),
              _TypeChip(
                  label: 'Контракт',
                  value: 'contract',
                  groupValue: _type,
                  onSelected: (v) => setState(() => _type = v)),
              _TypeChip(
                  label: 'Інвестиції',
                  value: 'investment',
                  groupValue: _type,
                  onSelected: (v) => setState(() => _type = v)),
              _TypeChip(
                  label: 'Інше',
                  value: 'other',
                  groupValue: _type,
                  onSelected: (v) => setState(() => _type = v)),
            ],
          ),
          const SizedBox(height: 20),
          if (_type == 'bizwar' || _type == 'contract') ...[
            InkWell(
              onTap: _pickDate,
              child: InputDecorator(
                decoration: const InputDecoration(labelText: 'Дата'),
                child: Text(
                  '${_reportDate.day.toString().padLeft(2, '0')}.${_reportDate.month.toString().padLeft(2, '0')}.${_reportDate.year}',
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          if (_type == 'bizwar') ...[
            Row(
              children: [
                Expanded(
                    child: TextField(
                        controller: _winsController,
                        keyboardType: TextInputType.number,
                        decoration:
                            const InputDecoration(labelText: 'Перемоги'))),
                const SizedBox(width: 12),
                Expanded(
                    child: TextField(
                        controller: _lossesController,
                        keyboardType: TextInputType.number,
                        decoration:
                            const InputDecoration(labelText: 'Поразки'))),
              ],
            ),
            const SizedBox(height: 16),
            Text('Час капта',
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.5), fontSize: 12)),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: _kaptTimes.map((t) {
                final selected = _selectedKaptTimes.contains(t);
                return ChoiceChip(
                  label: Text(t),
                  selected: selected,
                  onSelected: (_) => setState(() => selected
                      ? _selectedKaptTimes.remove(t)
                      : _selectedKaptTimes.add(t)),
                  selectedColor: AppColors.gold400.withValues(alpha: 0.2),
                  labelStyle: TextStyle(
                      color: selected ? AppColors.gold200 : Colors.white54,
                      fontSize: 12),
                  backgroundColor: AppColors.obsidian800,
                );
              }).toList(),
            ),
          ],
          if (_type == 'contract') ...[
            Row(
              children: [
                Expanded(
                    child: TextField(
                        controller: _lightController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'Легкі'))),
                const SizedBox(width: 8),
                Expanded(
                    child: TextField(
                        controller: _mediumController,
                        keyboardType: TextInputType.number,
                        decoration:
                            const InputDecoration(labelText: 'Середні'))),
                const SizedBox(width: 8),
                Expanded(
                    child: TextField(
                        controller: _heavyController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'Важкі'))),
              ],
            ),
          ],
          if (_type == 'investment')
            TextField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Сума, ₴')),
          const SizedBox(height: 16),
          TextField(
            controller: _descriptionController,
            maxLines: 3,
            decoration:
                const InputDecoration(labelText: 'Опис (необовʼязково)'),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              OutlinedButton.icon(
                  onPressed: _pickPhotos,
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('Галерея')),
              const SizedBox(width: 12),
              OutlinedButton.icon(
                  onPressed: _takePhoto,
                  icon: const Icon(Icons.camera_alt_outlined),
                  label: const Text('Камера')),
            ],
          ),
          if (_photos.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text('${_photos.length} фото додано',
                style: const TextStyle(color: Colors.white54, fontSize: 13)),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('ПОДАТИ ЗВІТ'),
          ),
        ],
      ),
    );
  }
}

class _TypeChip extends StatelessWidget {
  final String label;
  final String value;
  final String groupValue;
  final ValueChanged<String> onSelected;

  const _TypeChip(
      {required this.label,
      required this.value,
      required this.groupValue,
      required this.onSelected});

  @override
  Widget build(BuildContext context) {
    final selected = value == groupValue;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onSelected(value),
      selectedColor: AppColors.gold400.withValues(alpha: 0.2),
      labelStyle:
          TextStyle(color: selected ? AppColors.gold200 : Colors.white54),
      backgroundColor: AppColors.obsidian800,
      side: BorderSide(color: selected ? AppColors.gold400 : Colors.white12),
    );
  }
}
