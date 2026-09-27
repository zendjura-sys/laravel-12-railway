import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

/// Редагування учасника з мобільної адмінки — ім'я/email, посада, ролі
/// доступу, скидання пароля й видалення акаунту. Ті самі write-методи
/// Admin\UserController, що вже давно працюють на сайті (Адмін →
/// Учасники), просто викликані напряму через Sanctum-API.
class AdminMemberEditScreen extends StatefulWidget {
  final Map<String, dynamic> user;

  const AdminMemberEditScreen({super.key, required this.user});

  @override
  State<AdminMemberEditScreen> createState() => _AdminMemberEditScreenState();
}

class _AdminMemberEditScreenState extends State<AdminMemberEditScreen> {
  late final _firstNameController =
      TextEditingController(text: widget.user['firstName'] as String? ?? '');
  late final _lastNameController =
      TextEditingController(text: widget.user['lastName'] as String? ?? '');
  late final _emailController = TextEditingController(text: widget.user['email'] as String? ?? '');

  List<String> _allRoles = [];
  List<dynamic> _allPositions = [];
  final Set<String> _selectedRoles = {};
  String? _positionKey;

  bool _loadingOptions = true;
  bool _savingProfile = false;
  bool _savingRoles = false;
  bool _savingPosition = false;
  bool _resettingPassword = false;
  bool _deleting = false;
  String? _error;
  bool _deleted = false;

  @override
  void initState() {
    super.initState();
    _positionKey = widget.user['positionKey'] as String?;
    _selectedRoles.addAll((widget.user['roles'] as List).cast<String>());
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    try {
      final data = await ApiClient.instance.adminUserOptions();
      if (mounted) {
        setState(() {
          _allRoles = (data['roles'] as List).cast<String>();
          _allPositions = data['positions'] as List<dynamic>;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Не вдалося завантажити довідники.');
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  int get _userId => widget.user['id'] as int;

  Future<void> _saveProfile() async {
    setState(() => _savingProfile = true);
    try {
      await ApiClient.instance.adminUpdateUser(
        _userId,
        firstName: _firstNameController.text.trim(),
        lastName: _lastNameController.text.trim(),
        email: _emailController.text.trim(),
      );
      if (mounted) _toast('Дані оновлено.');
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) _toast('Не вдалося зберегти.', error: true);
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _saveRoles() async {
    setState(() => _savingRoles = true);
    try {
      await ApiClient.instance.adminUpdateUserRoles(_userId, _selectedRoles.toList());
      if (mounted) _toast('Ролі оновлено.');
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) _toast('Не вдалося зберегти ролі.', error: true);
    } finally {
      if (mounted) setState(() => _savingRoles = false);
    }
  }

  Future<void> _savePosition(String? key) async {
    setState(() {
      _positionKey = key;
      _savingPosition = true;
    });
    try {
      await ApiClient.instance.adminUpdateUserPosition(_userId, key);
      if (mounted) _toast('Посаду оновлено.');
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) _toast('Не вдалося зберегти посаду.', error: true);
    } finally {
      if (mounted) setState(() => _savingPosition = false);
    }
  }

  Future<void> _resetPassword() async {
    setState(() => _resettingPassword = true);
    try {
      final password = await ApiClient.instance.adminResetUserPassword(_userId);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: AppColors.obsidian900,
          title: const Text('Новий пароль', style: TextStyle(color: Colors.white)),
          content: SelectableText(password, style: TextStyle(color: AppColors.gold300, fontSize: 16)),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Закрити')),
          ],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) _toast('Не вдалося згенерувати пароль.', error: true);
    } finally {
      if (mounted) setState(() => _resettingPassword = false);
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.obsidian900,
        title: const Text('Видалити акаунт?', style: TextStyle(color: Colors.white)),
        content: Text('${widget.user['name']} — цю дію не можна скасувати.',
            style: const TextStyle(color: Colors.white54)),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Скасувати')),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Видалити', style: TextStyle(color: Colors.redAccent)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _deleting = true);
    try {
      await ApiClient.instance.adminDeleteUser(_userId);
      _deleted = true;
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted) _toast('Не вдалося видалити акаунт.', error: true);
    } finally {
      if (mounted && !_deleted) setState(() => _deleting = false);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? Colors.redAccent.withValues(alpha: 0.9) : null,
    ));
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: IslandAppBar(title: widget.user['name'] as String? ?? 'Учасник'),
      body: _loadingOptions
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: Colors.white70)))
              : ListView(
                  padding: islandInsets(context, const EdgeInsets.all(20)),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Профіль',
                              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                          const SizedBox(height: 14),
                          TextField(
                            controller: _firstNameController,
                            decoration: const InputDecoration(labelText: "Ім'я"),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: _lastNameController,
                            decoration: const InputDecoration(labelText: 'Прізвище'),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: _emailController,
                            decoration: const InputDecoration(labelText: 'Email'),
                            keyboardType: TextInputType.emailAddress,
                          ),
                          const SizedBox(height: 14),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton(
                              onPressed: _savingProfile ? null : _saveProfile,
                              child: _savingProfile
                                  ? const SizedBox(
                                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Зберегти профіль'),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Посада',
                              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                          const SizedBox(height: 10),
                          DropdownButtonFormField<String?>(
                            value: _positionKey,
                            dropdownColor: AppColors.obsidian800,
                            style: const TextStyle(color: Colors.white),
                            items: [
                              const DropdownMenuItem<String?>(value: null, child: Text('Без посади')),
                              ..._allPositions.map((p) {
                                final pos = p as Map<String, dynamic>;
                                return DropdownMenuItem<String?>(
                                  value: pos['key'] as String,
                                  child: Text(pos['title'] as String),
                                );
                              }),
                            ],
                            onChanged: _savingPosition ? null : _savePosition,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Ролі доступу',
                              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: _allRoles.map((role) {
                              final selected = _selectedRoles.contains(role);
                              return FilterChip(
                                label: Text(role),
                                selected: selected,
                                onSelected: (v) => setState(() {
                                  v ? _selectedRoles.add(role) : _selectedRoles.remove(role);
                                }),
                                selectedColor: AppColors.gold400.withValues(alpha: 0.2),
                                checkmarkColor: AppColors.gold300,
                                labelStyle: TextStyle(color: selected ? AppColors.gold300 : Colors.white54),
                                backgroundColor: Colors.white.withValues(alpha: 0.05),
                                side: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
                              );
                            }).toList(),
                          ),
                          const SizedBox(height: 14),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              onPressed: _savingRoles ? null : _saveRoles,
                              child: _savingRoles
                                  ? const SizedBox(
                                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Зберегти ролі'),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: glassPanelDecoration(radius: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Небезпечна зона',
                              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w500)),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              onPressed: _resettingPassword ? null : _resetPassword,
                              child: _resettingPassword
                                  ? const SizedBox(
                                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Скинути пароль'),
                            ),
                          ),
                          const SizedBox(height: 10),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.redAccent,
                                side: const BorderSide(color: Colors.redAccent),
                              ),
                              onPressed: _deleting ? null : _delete,
                              child: _deleting
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.redAccent))
                                  : const Text('Видалити акаунт'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}
