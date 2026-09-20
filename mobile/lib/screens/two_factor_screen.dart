import 'package:flutter/material.dart';
import '../api_client.dart';
import 'dashboard_screen.dart';

class TwoFactorScreen extends StatefulWidget {
  final String challengeToken;
  final String deviceName;

  const TwoFactorScreen(
      {super.key, required this.challengeToken, required this.deviceName});

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _codeController = TextEditingController();
  bool _useRecoveryCode = false;
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ApiClient.instance.loginTwoFactor(
        challengeToken: widget.challengeToken,
        code: _useRecoveryCode ? null : _codeController.text.trim(),
        recoveryCode: _useRecoveryCode ? _codeController.text.trim() : null,
        deviceName: widget.deviceName,
      );
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const DashboardScreen()),
        (route) => false,
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося зʼєднатися з сервером.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Двофакторний захист')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _useRecoveryCode
                    ? 'Введіть один із запасних кодів.'
                    : 'Введіть 6-значний код із застосунку-автентифікатора.',
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium
                    ?.copyWith(color: Colors.white70),
              ),
              const SizedBox(height: 24),
              TextField(
                controller: _codeController,
                autofocus: true,
                keyboardType: _useRecoveryCode
                    ? TextInputType.text
                    : TextInputType.number,
                decoration: InputDecoration(
                    labelText: _useRecoveryCode ? 'Запасний код' : 'Код'),
                onSubmitted: (_) => _submit(),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: Colors.redAccent)),
              ],
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('ПІДТВЕРДИТИ'),
                ),
              ),
              TextButton(
                onPressed: () => setState(() {
                  _useRecoveryCode = !_useRecoveryCode;
                  _codeController.clear();
                  _error = null;
                }),
                child: Text(_useRecoveryCode
                    ? 'Ввести код із застосунку'
                    : 'Використати запасний код'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
