import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../../core/theme.dart';
import '../../services/mobile_api_client.dart';
import 'barangay_admin_navigation.dart';

class BarangayIncidentDetail extends StatefulWidget {
  const BarangayIncidentDetail({
    super.key,
    required this.token,
    required this.incidentId,
    this.apiClient,
    this.onNavigation,
    this.onSos,
  });

  final String token;
  final int incidentId;
  final MobileApiClient? apiClient;
  final ValueChanged<int>? onNavigation;
  final VoidCallback? onSos;

  @override
  State<BarangayIncidentDetail> createState() => _BarangayIncidentDetailState();
}

class _BarangayIncidentDetailState extends State<BarangayIncidentDetail> {
  Map<String, dynamic>? _incident;
  String? _error;
  bool _loading = true;
  bool _exporting = false;

  MobileApiClient get _api => widget.apiClient ?? MobileApiClient.I;

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
      final response = await _api.barangayAdminPortal(
        token: widget.token,
        action: 'incident_detail',
        incidentId: widget.incidentId,
      );
      final incident = response['incident'];
      if (response['success'] != true || incident is! Map<String, dynamic>) {
        throw const MobileApiException(
            'The server returned invalid incident details.');
      }
      if (mounted) setState(() => _incident = incident);
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _value(Object? value) {
    if (value == null || value == '') return 'Not recorded';
    return value.toString();
  }

  String _displayTime(Object? value) {
    final parsed =
        DateTime.tryParse((value ?? '').toString().replaceFirst(' ', 'T'));
    if (parsed == null) return 'Pending';
    final local = parsed.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    return '$hour:${local.minute.toString().padLeft(2, '0')}';
  }

  Widget _timelineEntry({
    required String title,
    required Object? time,
    String? detail,
    bool last = false,
  }) {
    final complete = time != null && time != '';
    return SizedBox(
      height: 40,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 18,
            child: Column(
              children: [
                Icon(
                  complete ? Icons.check_circle : Icons.radio_button_unchecked,
                  size: 12,
                  color: complete ? const Color(0xFF469D43) : Colors.black26,
                ),
                if (!last)
                  Expanded(
                    child: Container(
                      width: 1,
                      color:
                          complete ? const Color(0xFFB7D8B5) : Colors.black12,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 5),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: complete ? Colors.black87 : Colors.black45,
                    )),
                if (detail != null && detail.isNotEmpty)
                  Text(detail,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style:
                          const TextStyle(fontSize: 9, color: Colors.black54)),
              ],
            ),
          ),
          const SizedBox(width: 7),
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Text(_displayTime(time),
                style: const TextStyle(fontSize: 9, color: Colors.black54)),
          ),
        ],
      ),
    );
  }

  Widget _dataRow(String label, Object? value, {bool last = false}) =>
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
        decoration: BoxDecoration(
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: Color(0xFFECEAEA), width: 0.7),
                ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(label,
                  style: const TextStyle(fontSize: 9, color: Colors.black54)),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(_value(value),
                  textAlign: TextAlign.right,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );

  Future<void> _exportPdf() async {
    final incident = _incident;
    if (incident == null) return;
    setState(() => _exporting = true);
    try {
      final pdf = pw.Document();
      final reporter = incident['reporter'] as Map<String, dynamic>? ?? {};
      final responder = incident['responder'] as Map<String, dynamic>? ?? {};
      final patientSummary =
          incident['patient_summary'] as Map<String, dynamic>? ?? {};
      final transport = incident['transport'] as Map<String, dynamic>? ?? {};
      final preparation =
          incident['nurse_preparation'] as Map<String, dynamic>? ?? {};
      final patients = incident['patients'] is List
          ? (incident['patients'] as List).whereType<Map<String, dynamic>>()
          : const <Map<String, dynamic>>[];
      pdf.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat.a4,
          build: (_) => [
            pw.Text(
              'REDDIPH · INCIDENT REPORT',
              style: pw.TextStyle(
                color: PdfColor.fromHex('#491717'),
                fontSize: 20,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.SizedBox(height: 8),
            pw.Text('Case #RS-${incident['incident_id']}',
                style:
                    pw.TextStyle(fontSize: 15, fontWeight: pw.FontWeight.bold)),
            pw.Divider(),
            _pdfSection('Incident', [
              'Type: ${_value(incident['incident_type'])}',
              'Status: ${_value(incident['status'])}',
              'Location: ${_value(incident['location'])}',
              'Barangay: ${_value(incident['barangay'])}',
              'ETA: ${_value(patientSummary['eta_minutes'])} minutes',
              'Reported: ${_value(incident['reported_at'])}',
              'Responder arrived: ${_value(incident['responder_arrived_at'])}',
              'Hospital sent: ${_value(incident['hospital_sent_at'])}',
              'Patient picked up: ${_value(incident['patient_picked_up_at'])}',
              'Arrived at hospital: ${_value(incident['hospital_arrived_at'])}',
              'Resolved: ${_value(incident['resolved_at'])}',
            ]),
            _pdfSection('Reporter', [
              'Name: ${_value(reporter['name'])}',
              'Email: ${_value(reporter['email'])}',
              'Phone: ${_value(reporter['phone'])}',
            ]),
            _pdfSection('Responder', [
              'Name: ${_value(responder['name'])}',
              'Email: ${_value(responder['email'])}',
              'Phone: ${_value(responder['phone'])}',
            ]),
            _pdfSection('Hospital and transport', [
              'Hospital: ${_value(incident['hospital'])}',
              'Hospital address: ${_value(incident['hospital_address'])}',
              'Transport: ${_value(transport['name'])}',
              'Type: ${_value(transport['type'])}',
              'Driver: ${_value(transport['driver'])}',
            ]),
            _pdfSection('Hospital preparation', [
              'Prepared: ${preparation['completed'] == true ? 'Yes' : 'No'}',
              'Prepared by: ${_value(preparation['nurse_name'])}',
              'Prepared at: ${_value(preparation['completed_at'])}',
              'Heart rate: ${_value(preparation['heart_rate'])}',
              'Blood pressure: ${_value(preparation['blood_pressure'])}',
              'SpO2: ${_value(preparation['spo2'])}',
              'GCS: ${_value(preparation['gcs'])}',
              'Notes: ${_value(preparation['notes'])}',
            ]),
            pw.Text('Patient information',
                style:
                    pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold)),
            if (patients.isEmpty)
              pw.Text('No patient records were attached.')
            else
              ...patients.map((patient) => pw.Padding(
                    padding: const pw.EdgeInsets.only(top: 6),
                    child: pw.Text(
                      '${_value(patient['patient_name'])} · age ${_value(patient['patient_age'])} · '
                      '${_value(patient['patient_gender'])}\n'
                      'Heart rate: ${_value(patient['heart_rate'])} · SpO₂: ${_value(patient['spo2'])} · '
                      'Blood pressure: ${_value(patient['nurse_blood_pressure'])}\n'
                      'Conditions: ${_value((patient['conditions'] as List?)?.join(', '))}\n'
                      'Notes: ${_value(patient['notes'])}',
                    ),
                  )),
            pw.SizedBox(height: 10),
            _pdfSection('Incident notes', [_value(incident['notes'])]),
            if (incident['false_report'] is Map<String, dynamic>)
              _pdfSection('False-report review', [
                'Reason: ${_value((incident['false_report'] as Map<String, dynamic>)['reason'])}',
                'Status: ${_value((incident['false_report'] as Map<String, dynamic>)['review_status'])}',
                'Notes: ${_value((incident['false_report'] as Map<String, dynamic>)['notes'])}',
              ]),
          ],
        ),
      );
      await Printing.sharePdf(
        bytes: await pdf.save(),
        filename: 'reddiph-case-${incident['incident_id']}.pdf',
      );
    } on Exception catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Unable to export PDF: $error')));
      }
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  pw.Widget _pdfSection(String title, List<String> lines) => pw.Padding(
        padding: const pw.EdgeInsets.symmetric(vertical: 8),
        child: pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            pw.Text(title,
                style:
                    pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold)),
            pw.SizedBox(height: 4),
            ...lines.map((line) => pw.Padding(
                  padding: const pw.EdgeInsets.only(bottom: 2),
                  child: pw.Text(line),
                )),
          ],
        ),
      );

  @override
  Widget build(BuildContext context) {
    final incident = _incident;
    final responder = incident?['responder'] as Map<String, dynamic>? ?? {};
    final patientSummary =
        incident?['patient_summary'] as Map<String, dynamic>? ?? {};
    final patients = incident?['patients'] is List
        ? incident!['patients'] as List
        : const [];
    final patient =
        patients.isNotEmpty && patients.first is Map<String, dynamic>
            ? patients.first as Map<String, dynamic>
            : const <String, dynamic>{};
    final casePatient = patientSummary['name'] ?? patient['patient_name'];
    final conditions = patient['conditions'] is List
        ? (patient['conditions'] as List).join(', ')
        : 'Not recorded';
    final note = patient['notes'] ?? incident?['notes'];
    final eta = patientSummary['eta_minutes'];
    return Scaffold(
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        OutlinedButton(
                            onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : SafeArea(
                  child: ListView(
                  padding: const EdgeInsets.fromLTRB(18, 5, 18, 18),
                  children: [
                    Align(
                      alignment: Alignment.centerLeft,
                      child: IconButton(
                        tooltip: 'Back',
                        onPressed: () => Navigator.of(context).maybePop(),
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.zero,
                        alignment: Alignment.centerLeft,
                        icon: const Icon(Icons.arrow_back_ios_new, size: 15),
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(_value(incident?['incident_type']),
                        style: const TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(
                      'Case #RS-${incident?['incident_id']} · '
                      '${_value(incident?['location'])}, ${_value(incident?['barangay'])}',
                      style:
                          const TextStyle(fontSize: 10, color: Colors.black54),
                    ),
                    const SizedBox(height: 20),
                    _timelineEntry(
                      title: 'SOS received',
                      time: incident?['reported_at'],
                    ),
                    _timelineEntry(
                      title: 'Opened by ${responder['name'] ?? 'responder'}',
                      time: incident?['responder_opened_at'],
                    ),
                    _timelineEntry(
                      title: 'Verified real on scene',
                      time: incident?['responder_arrived_at'],
                    ),
                    _timelineEntry(
                      title:
                          'Sent to hospital${eta == null ? '' : ' · ETA $eta min'}',
                      time: incident?['hospital_sent_at'],
                      last: incident?['hospital_arrived_at'] == null,
                    ),
                    if (incident?['hospital_arrived_at'] != null)
                      _timelineEntry(
                        title: 'Arrived at the hospital',
                        time: incident?['hospital_arrived_at'],
                        last: true,
                      ),
                    const SizedBox(height: 4),
                    const Padding(
                      padding: EdgeInsets.only(bottom: 5),
                      child: Text('PATIENT DETAILS',
                          style: TextStyle(
                              color: Color(0xFF4779AD),
                              fontSize: 9,
                              fontWeight: FontWeight.w800)),
                    ),
                    Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: const Color(0xFFE8E5E5)),
                        borderRadius: BorderRadius.circular(7),
                      ),
                      child: Column(
                        children: [
                          _dataRow(
                            'Patient',
                            '${_value(casePatient)} · ${_value(patientSummary['age'] ?? patient['patient_age'])}, '
                                '${_value(patientSummary['gender'] ?? patient['patient_gender'])}',
                          ),
                          _dataRow('Condition', conditions),
                          _dataRow('Notes', note, last: true),
                        ],
                      ),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      width: double.infinity,
                      height: 34,
                      decoration: BoxDecoration(
                        border: Border.all(color: const Color(0xFFE8E5E5)),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: TextButton(
                        onPressed: _exporting ? null : _exportPdf,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          foregroundColor: Colors.black87,
                          textStyle: const TextStyle(
                              fontSize: 10, fontWeight: FontWeight.w800),
                        ),
                        child: _exporting
                            ? const SizedBox(
                                width: 14,
                                height: 14,
                                child:
                                    CircularProgressIndicator(strokeWidth: 1.5),
                              )
                            : const Text('EXPORT THIS CASE'),
                      ),
                    ),
                    if (incident?['false_report'] is Map<String, dynamic>)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'False report: ${_value((incident!['false_report'] as Map)['review_status'])}',
                          style: const TextStyle(fontSize: 8),
                        ),
                      ),
                  ],
                )),
      floatingActionButton: FloatingActionButton(
        heroTag: null,
        backgroundColor: kSos,
        onPressed: widget.onSos,
        child: const Text('SOS',
            style: TextStyle(
                color: Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w800)),
      ),
      bottomNavigationBar: BarangayAdminNavigation(
        selectedIndex: 2,
        onDestinationSelected: (index) => widget.onNavigation?.call(index),
      ),
    );
  }
}
