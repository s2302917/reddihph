import 'package:flutter/material.dart';

import '../../models/nurse_portal.dart';
import '../widgets/common.dart';
import '../widgets/nurse_widgets.dart';

class NurseCaseHistoryDetail extends StatelessWidget {
  const NurseCaseHistoryDetail({super.key, required this.item});

  final NurseCaseHistoryRecord item;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Case history')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              color: kNurse,
              borderRadius: BorderRadius.all(Radius.circular(10)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${item.title} · Incident #${item.id}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${item.status.replaceAll('_', ' ').toUpperCase()} · '
                  '${item.triageCategoryLabel}',
                  style: const TextStyle(color: Colors.white70, fontSize: 10),
                ),
              ],
            ),
          ),
          const Section('Patient'),
          _details([
            ('Name', item.patientName),
            ('Age', item.patientAge?.toString() ?? 'N/A'),
            ('Gender', item.patientGender),
            ('Location', item.location),
          ]),
          const Section('Incident activity'),
          _details([
            ('Notified', item.notifiedAt),
            ('Reported', item.reportedAt),
            ('Last updated', item.updatedAt),
            ('Responder', item.responder),
            ('Transport unit', item.unit),
          ]),
          const Section('Patient assessment'),
          _details([
            (
              'Heart rate',
              item.heartRate == null ? 'N/A' : '${item.heartRate} bpm'
            ),
            ('SpO2', item.spo2 == null ? 'N/A' : '${item.spo2}%'),
            ('Blood pressure', item.bloodPressure ?? 'N/A'),
          ]),
          const Section('Clinical note'),
          _note(item.note),
          const Section('Incident notes'),
          _note(item.responderNotes),
        ],
      ),
    );
  }

  Widget _details(List<(String, String)> values) => Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              for (final (label, value) in values)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 5),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 100,
                        child: Text(
                          label,
                          style: const TextStyle(
                            fontSize: 9,
                            color: Colors.black54,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          value.isEmpty ? 'N/A' : value,
                          style: const TextStyle(fontSize: 10),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      );

  Widget _note(String value) => Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Text(
            value.trim().isEmpty ? 'No note recorded.' : value,
            style: const TextStyle(fontSize: 10),
          ),
        ),
      );
}
