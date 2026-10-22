import 'package:flutter/material.dart';

import 'responder_widgets.dart';

const incidentTypeOptions = ['Vehicular', 'Fall', 'Medical', 'Fire', 'Other'];

class IncidentTypeSelector extends StatelessWidget {
  const IncidentTypeSelector({
    super.key,
    required this.selectedType,
    required this.onChanged,
    required this.otherTypeController,
    this.enabled = true,
    this.label = 'Incident type',
    this.color = kResponderBrown,
  });

  final String selectedType;
  final ValueChanged<String> onChanged;
  final TextEditingController otherTypeController;
  final bool enabled;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style:
                  const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
          const SizedBox(height: 9),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              for (final type in incidentTypeOptions)
                ChoiceChip(
                  label: Text(type),
                  selected: selectedType == type,
                  onSelected: enabled ? (_) => onChanged(type) : null,
                  selectedColor: color,
                  labelStyle: TextStyle(
                    color: selectedType == type ? Colors.white : Colors.black87,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
            ],
          ),
          if (selectedType == 'Other') ...[
            const SizedBox(height: 8),
            TextFormField(
              controller: otherTypeController,
              enabled: enabled,
              maxLength: 120,
              textCapitalization: TextCapitalization.sentences,
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Enter the incident type.'
                  : null,
              decoration: const InputDecoration(
                labelText: 'Specify incident type',
                hintText: 'Describe the emergency',
                border: OutlineInputBorder(),
                isDense: true,
              ),
            ),
          ],
        ],
      );
}
