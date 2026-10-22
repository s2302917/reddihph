import 'package:flutter/material.dart';
import '../../models/volunteer_data.dart';
import '../widgets/doctor_widgets.dart';
import '../widgets/volunteer_widgets.dart';

/// Opens the "Post appreciation" bottom sheet. Returns the new wall entry,
/// or null if the user cancelled.
Future<AppreciationEntry?> showPostAppreciationSheet(BuildContext context) =>
    showModalBottomSheet<AppreciationEntry>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => const _PostSheet(),
    );

class _PostSheet extends StatefulWidget {
  const _PostSheet();

  @override
  State<_PostSheet> createState() => _PostSheetState();
}

class _PostSheetState extends State<_PostSheet> {
  static const _tags = ['On time', 'Helpful', 'Careful driving', 'Calm'];
  final _message = TextEditingController();
  final Set<String> _picked = {'On time', 'Helpful'};
  int _person = 0;
  int _rating = 4;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  void _post() {
    final p = VolunteerData.people[_person];
    final text = _message.text.trim();
    Navigator.pop(
      context,
      AppreciationEntry(
        name: p.name,
        role: 'Volunteer · Vehicle transport',
        quote:
            text.isEmpty ? 'Maraming salamat po sa mabilis na tulong.' : text,
        from: 'From you',
        when: 'Today',
        stars: _rating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 10, 24, 24),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Center(
            child: Container(
                width: 56,
                height: 5,
                decoration: BoxDecoration(
                    color: Colors.black26,
                    borderRadius: BorderRadius.circular(3))),
          ),
          const SizedBox(height: 18),
          const Text('Post appreciation',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
          const Text('Recognize a volunteer who helped',
              style: TextStyle(fontSize: 8, color: Colors.black54)),
          const SizedBox(height: 14),
          Row(children: [
            for (int i = 0; i < VolunteerData.people.length; i++) ...[
              if (i > 0) const SizedBox(width: 8),
              Expanded(child: _person_(i)),
            ],
          ]),
          const SizedBox(height: 18),
          const Text('Rating',
              style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Center(
            child: Stars(_rating,
                size: 22, onChanged: (v) => setState(() => _rating = v)),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final t in _tags)
              GestureDetector(
                onTap: () => setState(() {
                  _picked.contains(t) ? _picked.remove(t) : _picked.add(t);
                }),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                    color: _picked.contains(t) ? kNavy : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                        color: _picked.contains(t) ? kNavy : Colors.black54),
                  ),
                  child: Text(t,
                      style: TextStyle(
                          fontSize: 8,
                          fontWeight: FontWeight.w700,
                          color: _picked.contains(t)
                              ? Colors.white
                              : Colors.black87)),
                ),
              ),
          ]),
          const SizedBox(height: 16),
          const Text('Message',
              style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          TextField(
            controller: _message,
            minLines: 2,
            maxLines: 4,
            style: const TextStyle(fontSize: 10),
            decoration: InputDecoration(
              hintText: 'Maraming salamat po sa mabilis na tulong.',
              hintStyle: const TextStyle(fontSize: 9, color: Colors.black38),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(6),
                  borderSide: const BorderSide(color: Colors.black26)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(6),
                  borderSide: const BorderSide(color: Colors.black26)),
            ),
          ),
          const SizedBox(height: 12),
          NavyButton('POST TO THE WALL', onTap: _post),
          const SizedBox(height: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(40),
                foregroundColor: Colors.black,
                side: const BorderSide(color: Colors.black26, width: 1.5),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(6))),
            onPressed: () => Navigator.pop(context),
            child: const Text('CANCEL',
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800)),
          ),
        ]),
      ),
    );
  }

  Widget _person_(int i) {
    final p = VolunteerData.people[i];
    final sel = i == _person;
    return GestureDetector(
      onTap: () => setState(() => _person = i),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: sel ? const Color(0xFF8A8DB0) : Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.black26),
        ),
        child: Column(children: [
          CircleAvatar(
            radius: 8,
            backgroundColor: kNavy,
            child: Text(p.initial,
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 8,
                    fontWeight: FontWeight.bold)),
          ),
          const SizedBox(height: 4),
          Text(p.name,
              style: const TextStyle(fontSize: 8, fontWeight: FontWeight.w800)),
        ]),
      ),
    );
  }
}
