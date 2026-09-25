import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../api_client.dart';
import '../theme.dart';
import '../widgets/island_top_bar.dart';

class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key});

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  List<dynamic> _events = [];
  bool _loading = true;
  String? _error;

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
      final events = await ApiClient.instance.events();
      if (mounted) setState(() => _events = events);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Не вдалося завантажити події.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _rsvp(Map<String, dynamic> event, String status) async {
    try {
      final myRsvp = await ApiClient.instance.rsvpEvent(event['id'] as int, status);
      if (mounted) setState(() => event['myRsvp'] = myRsvp);
      // Точна кількість "прийдуть" — з повного перезавантаження нижче,
      // без спроб порахувати дельту тут.
      _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final formatter = DateFormat('dd.MM.yyyy HH:mm');

    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: const IslandAppBar(title: 'Події родини'),
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
                : _events.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 80),
                          Center(
                            child: Text('Найближчих подій поки немає.',
                                style: TextStyle(color: Colors.white38)),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: islandInsets(context, const EdgeInsets.all(20)),
                        itemCount: _events.length,
                        itemBuilder: (context, i) {
                          final event = _events[i] as Map<String, dynamic>;
                          final startsAt = DateTime.parse(event['startsAt'] as String).toLocal();
                          final myRsvp = event['myRsvp'] as String?;

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: glassPanelDecoration(radius: 16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(event['title'] as String,
                                    style: const TextStyle(
                                        color: Colors.white, fontSize: 16, fontWeight: FontWeight.w500)),
                                const SizedBox(height: 4),
                                Text(formatter.format(startsAt),
                                    style: TextStyle(color: AppColors.gold300, fontSize: 13)),
                                if (event['location'] != null) ...[
                                  const SizedBox(height: 2),
                                  Text('📍 ${event['location']}',
                                      style: const TextStyle(color: Colors.white54, fontSize: 13)),
                                ],
                                if (event['description'] != null) ...[
                                  const SizedBox(height: 8),
                                  Text(event['description'] as String,
                                      style: const TextStyle(color: Colors.white70, fontSize: 13)),
                                ],
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor:
                                              myRsvp == 'going' ? AppColors.obsidian950 : const Color(0xFF6EE7B7),
                                          backgroundColor: myRsvp == 'going'
                                              ? const Color(0xFF6EE7B7)
                                              : Colors.transparent,
                                          side: const BorderSide(color: Color(0xFF6EE7B7)),
                                        ),
                                        onPressed: () => _rsvp(event, 'going'),
                                        child: const Text('✓ Прийду'),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor:
                                              myRsvp == 'not_going' ? AppColors.obsidian950 : const Color(0xFFF87171),
                                          backgroundColor: myRsvp == 'not_going'
                                              ? const Color(0xFFF87171)
                                              : Colors.transparent,
                                          side: const BorderSide(color: Color(0xFFF87171)),
                                        ),
                                        onPressed: () => _rsvp(event, 'not_going'),
                                        child: const Text('✕ Не прийду'),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text('✓ ${event['goingCount']} прийдуть',
                                    style: const TextStyle(color: Colors.white38, fontSize: 12)),
                              ],
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}
