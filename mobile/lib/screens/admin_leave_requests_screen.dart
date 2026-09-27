import 'package:flutter/material.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

class AdminLeaveRequestsScreen extends StatefulWidget {
  const AdminLeaveRequestsScreen({super.key});

  @override
  State<AdminLeaveRequestsScreen> createState() => _AdminLeaveRequestsScreenState();
}

class _AdminLeaveRequestsScreenState extends State<AdminLeaveRequestsScreen> {
  List<dynamic> _requests = [];
  bool _loading = true;
  String? _error;
  int? _busyId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final requests = await ApiClient.instance.adminPendingLeaveRequests();
      if (mounted) setState(() => _requests = requests);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити заявки.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _review(Map<String, dynamic> request, bool approve) async {
    setState(() => _busyId = request['id'] as int);
    try {
      if (approve) {
        await ApiClient.instance.adminApproveLeave(request['id'] as int);
      } else {
        await ApiClient.instance.adminRejectLeave(request['id'] as int);
      }
      setState(() => _requests = _requests.where((r) => r['id'] != request['id']).toList());
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: const IslandAppBar(title: 'Заявки на відпустку'),
      body: RefreshIndicator(
        onRefresh: _load,
        edgeOffset: islandTopInset(context),
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, style: const TextStyle(color: Colors.white70)),
                        const SizedBox(height: 12),
                        TextButton(onPressed: _load, child: const Text('Повторити')),
                      ],
                    ),
                  )
                : _requests.isEmpty
                    ? const Center(
                        child: Text('Немає заявок на розгляді.', style: TextStyle(color: Colors.white38)))
                    : ListView.builder(
                        padding: islandInsets(context, const EdgeInsets.all(16)),
                        itemCount: _requests.length,
                        itemBuilder: (context, i) {
                          final r = _requests[i] as Map<String, dynamic>;
                          final busy = _busyId == r['id'];
                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(14),
                            decoration: glassPanelDecoration(radius: 14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(r['userName'] as String? ?? '',
                                    style: const TextStyle(
                                        color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                                const SizedBox(height: 4),
                                Text('${r['startsOn']} — ${r['endsOn']}',
                                    style: TextStyle(color: AppColors.gold300, fontSize: 12)),
                                if (r['reason'] != null) ...[
                                  const SizedBox(height: 6),
                                  Text(r['reason'] as String,
                                      style: const TextStyle(color: Colors.white54, fontSize: 13)),
                                ],
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        onPressed: busy ? null : () => _review(r, false),
                                        child: const Text('Відхилити'),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: ElevatedButton(
                                        onPressed: busy ? null : () => _review(r, true),
                                        child: busy
                                            ? const SizedBox(
                                                width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                                            : const Text('Затвердити'),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}
