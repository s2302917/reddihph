import 'dart:async';
import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:share_plus/share_plus.dart';

import '../../controllers/auth_controller.dart';
import '../../core/nav.dart';
import '../../core/philippine_time.dart';
import '../../core/theme.dart';
import '../../models/false_report.dart';
import '../../models/scene_arrival_alert.dart';
import '../../services/mobile_api_client.dart';
import '../widgets/common.dart';
import '../widgets/philippine_clock.dart';
import '../screens/sos_screen.dart';
import 'barangay_incident_detail.dart';
import 'barangay_admin_navigation.dart';

class BarangayAdminHome extends StatefulWidget {
  final VoidCallback onLogout;
  final MobileApiClient? apiClient;
  final Future<ShareResult> Function(ShareParams params)? shareLogs;

  const BarangayAdminHome({
    super.key,
    required this.onLogout,
    this.apiClient,
    this.shareLogs,
  });

  @override
  State<BarangayAdminHome> createState() => _BarangayAdminHomeState();
}

class _BarangayAdminHomeState extends State<BarangayAdminHome> {
  int _tab = 0;
  bool _showNotifications = false;
  final Set<String> _readNotificationIds = {};
  bool _loading = true;
  String? _error;
  List<FalseReportCase> _reports = const [];
  List<SceneArrivalAlert> _arrivalAlerts = const [];
  final Set<int> _reviewing = {};
  final Set<int> _reviewingSignups = {};
  final Set<int> _acknowledgingArrivals = {};
  Timer? _refreshTimer;
  bool _refreshing = false;
  bool _portalLoading = false;
  bool _logsLoading = false;
  bool _exportingLogs = false;
  String? _portalError;
  final TextEditingController _searchController = TextEditingController();
  Map<String, dynamic> _summary = const {};
  List<Map<String, dynamic>> _activity = const [];
  List<Map<String, dynamic>> _notifications = const [];
  List<Map<String, dynamic>> _approvals = const [];
  List<Map<String, dynamic>> _signupItems = const [];
  Map<String, dynamic>? _selectedSignup;
  Map<String, int> _signupCounts = const {
    'pending': 0,
    'approved': 0,
    'rejected': 0,
  };
  String _signupStatus = 'pending';
  bool _signupLoading = false;
  int _signupPage = 1;
  int _signupTotalPages = 1;
  int _signupRequestId = 0;
  List<Map<String, dynamic>> _logs = const [];
  int _logPage = 1;
  int _logTotalPages = 1;

  String get _token => AuthController.I.user?['token'] as String? ?? '';
  MobileApiClient get _apiClient => widget.apiClient ?? MobileApiClient.I;

  @override
  void initState() {
    super.initState();
    _loadQueues();
    _loadPortal();
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (!_loading && !_refreshing) {
        _refreshQueues();
        _loadPortal();
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadPortal() async {
    if (_portalLoading) return;
    setState(() {
      _portalLoading = true;
      _portalError = null;
    });
    try {
      final results = await Future.wait([
        _apiClient.barangayAdminPortal(token: _token, action: 'dashboard'),
        _apiClient.barangayAdminPortal(token: _token, action: 'notifications'),
        _apiClient.barangayAdminPortal(token: _token, action: 'approvals'),
        _apiClient.barangayAdminPortal(
          token: _token,
          action: 'logs',
          page: _logPage,
          search: _searchController.text.trim(),
        ),
      ]);
      final summary = results[0]['summary'];
      final activity = results[0]['activity'];
      final notifications = results[1]['notifications'];
      final approvals = results[2]['signups'];
      final signupCounts = results[2]['counts'];
      final logs = results[3]['items'];
      if (summary is! Map<String, dynamic> ||
          activity is! List ||
          notifications is! List ||
          approvals is! List ||
          logs is! List) {
        throw const MobileApiException(
          'The server returned incomplete Barangay Admin dashboard data.',
        );
      }
      if (!mounted) return;
      setState(() {
        _summary = summary;
        _activity = activity.whereType<Map<String, dynamic>>().toList();
        _notifications =
            notifications.whereType<Map<String, dynamic>>().toList();
        _approvals = approvals.whereType<Map<String, dynamic>>().toList();
        if (signupCounts is Map<String, dynamic>) {
          _signupCounts = {
            for (final status in ['pending', 'approved', 'rejected'])
              status: (signupCounts[status] as num?)?.toInt() ?? 0,
          };
        } else {
          _signupCounts = {
            ..._signupCounts,
            'pending': approvals.length,
          };
        }
        _logs = logs.whereType<Map<String, dynamic>>().toList();
        _logTotalPages = ((results[3]['total_pages'] as num?)?.toInt() ?? 1)
            .clamp(1, 100000)
            .toInt();
        _portalError = null;
      });
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _portalError = error.message);
    } finally {
      if (mounted) setState(() => _portalLoading = false);
    }
  }

  Future<void> _loadSignups({String? status, int? page}) async {
    if (status != null) {
      _signupStatus = status;
      _signupPage = 1;
      _selectedSignup = null;
    } else if (page != null) {
      _signupPage = page;
    }
    final requestId = ++_signupRequestId;
    setState(() => _signupLoading = true);
    try {
      final response = await _apiClient.barangayAdminPortal(
        token: _token,
        action: 'signup_list',
        status: _signupStatus,
        page: _signupPage,
      );
      final items = response['items'];
      if (response['success'] != true || items is! List) {
        throw const MobileApiException(
          'The server returned invalid signup records.',
        );
      }
      if (!mounted || requestId != _signupRequestId) return;
      setState(() {
        _signupItems = items.whereType<Map<String, dynamic>>().toList();
        final total = (response['total'] as num?)?.toInt();
        if (total != null) {
          _signupCounts = {..._signupCounts, _signupStatus: total};
        }
        _signupTotalPages = ((response['total_pages'] as num?)?.toInt() ?? 1)
            .clamp(1, 100000)
            .toInt();
        _portalError = null;
      });
    } on MobileApiException catch (error) {
      if (mounted && requestId == _signupRequestId) {
        setState(() => _portalError = error.message);
      }
    } finally {
      if (mounted && requestId == _signupRequestId) {
        setState(() => _signupLoading = false);
      }
    }
  }

  Future<void> _loadLogs({int? page}) async {
    if (page != null) _logPage = page;
    setState(() => _logsLoading = true);
    try {
      final response = await _apiClient.barangayAdminPortal(
        token: _token,
        action: 'logs',
        page: _logPage,
        search: _searchController.text.trim(),
      );
      final items = response['items'];
      if (response['success'] != true || items is! List) {
        throw const MobileApiException(
            'The server returned invalid incident logs.');
      }
      if (mounted) {
        setState(() {
          _logs = items.whereType<Map<String, dynamic>>().toList();
          _logTotalPages = ((response['total_pages'] as num?)?.toInt() ?? 1)
              .clamp(1, 100000)
              .toInt();
          _portalError = null;
        });
      }
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _portalError = error.message);
    } finally {
      if (mounted) setState(() => _logsLoading = false);
    }
  }

  Future<void> _openLogExport() async {
    final request = await showModalBottomSheet<_LogExportRequest>(
      context: context,
      isScrollControlled: true,
      enableDrag: false,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.transparent,
      builder: (sheetContext) => _LogExportOverlay(
        onDismiss: () => Navigator.of(sheetContext).pop(),
        onExport: (request) => Navigator.of(sheetContext).pop(request),
      ),
    );
    if (request != null) await _exportLogs(request);
  }

  Future<void> _exportLogs(_LogExportRequest request) async {
    setState(() => _exportingLogs = true);
    try {
      final response = await _apiClient.barangayAdminPortal(
        token: _token,
        action: 'logs_export',
        period: request.period,
        includes: request.includes.toList(),
      );
      final rawRecords = response['records'];
      if (response['success'] != true || rawRecords is! Map<String, dynamic>) {
        throw const MobileApiException(
          'The server returned invalid log export data.',
        );
      }
      final records = <String, List<Map<String, dynamic>>>{};
      for (final category in request.includes) {
        final rawCategory = rawRecords[category];
        if (rawCategory is! List ||
            rawCategory.any((item) => item is! Map<String, dynamic>)) {
          throw const MobileApiException(
            'The server returned incomplete log export data.',
          );
        }
        records[category] =
            rawCategory.whereType<Map<String, dynamic>>().toList();
      }

      final periodLabel = _exportPeriodLabel(request.period);
      final fileName =
          'reddiph-logs-${request.period}-${DateTime.now().toIso8601String().substring(0, 10)}';
      final files = <XFile>[];
      if (request.formats.contains('pdf')) {
        files.add(XFile.fromData(
          await _buildLogsPdf(records, periodLabel),
          mimeType: 'application/pdf',
          name: '$fileName.pdf',
        ));
      }
      if (request.formats.contains('csv')) {
        files.add(XFile.fromData(
          Uint8List.fromList(utf8.encode(_buildLogsCsv(records))),
          mimeType: 'text/csv',
          name: '$fileName.csv',
        ));
      }
      if (files.isEmpty) {
        throw const MobileApiException('Choose PDF or CSV before exporting.');
      }

      final shareParams = ShareParams(
        files: files,
        subject: 'Barangay incident logs - $periodLabel',
        title: 'Export logs',
      );
      final result = await (widget.shareLogs ?? SharePlus.instance.share)(
        shareParams,
      );
      if (result.status == ShareResultStatus.unavailable) {
        throw const MobileApiException(
          'File sharing is not available on this device.',
        );
      }
      if (result.status == ShareResultStatus.dismissed && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Log export canceled.')),
        );
      }
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } on PlatformException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Unable to share exported logs: '
              '${error.message ?? error.code}',
            ),
          ),
        );
      }
    } on MissingPluginException {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('File sharing is not configured on this device.'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _exportingLogs = false);
    }
  }

  Future<Uint8List> _buildLogsPdf(
    Map<String, List<Map<String, dynamic>>> records,
    String periodLabel,
  ) async {
    final document = pw.Document();
    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(28),
        build: (context) => [
          pw.Text(
            'Barangay Incident Logs',
            style: pw.TextStyle(
              fontSize: 18,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
          pw.SizedBox(height: 4),
          pw.Text('$periodLabel - Generated ${DateTime.now().toLocal()}'),
          pw.SizedBox(height: 14),
          for (final entry in records.entries) ...[
            pw.Text(
              _exportCategoryLabel(entry.key),
              style: pw.TextStyle(
                fontSize: 13,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.SizedBox(height: 5),
            if (entry.value.isEmpty)
              pw.Text('No records for this category in the selected period.')
            else
              _pdfExportTable(entry.key, entry.value),
            pw.SizedBox(height: 14),
          ],
        ],
      ),
    );
    return Uint8List.fromList(await document.save());
  }

  pw.Widget _pdfExportTable(
    String category,
    List<Map<String, dynamic>> records,
  ) {
    final columns = _pdfExportColumns(category);
    return pw.TableHelper.fromTextArray(
      headers: columns.map((column) => column.$1).toList(),
      data: records
          .map((record) =>
              columns.map((column) => _exportValue(record[column.$2])).toList())
          .toList(),
      headerStyle: pw.TextStyle(
        fontSize: 8,
        fontWeight: pw.FontWeight.bold,
        color: PdfColors.white,
      ),
      headerDecoration:
          const pw.BoxDecoration(color: PdfColor.fromInt(0xFF521E1E)),
      cellStyle: const pw.TextStyle(fontSize: 7),
      cellAlignment: pw.Alignment.centerLeft,
      cellPadding: const pw.EdgeInsets.all(4),
      border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
    );
  }

  List<(String, String)> _pdfExportColumns(String category) =>
      switch (category) {
        'sos' => [
            ('Date', 'occurred_at'),
            ('Case', 'incident_id'),
            ('Incident', 'incident_type'),
            ('Status', 'status'),
            ('Location', 'location'),
            ('Resident', 'reporter_name'),
            ('Responder', 'responder_name'),
            ('Hospital', 'hospital_name'),
          ],
        'hospital' => [
            ('Date', 'occurred_at'),
            ('Case', 'incident_id'),
            ('Status', 'status'),
            ('Location', 'location'),
            ('Hospital', 'hospital_name'),
            ('Sent', 'hospital_sent_at'),
            ('Arrived', 'hospital_arrived_at'),
            ('Responder', 'responder_name'),
          ],
        'volunteers' => [
            ('Date', 'occurred_at'),
            ('Volunteer', 'full_name'),
            ('Email', 'work_email'),
            ('Phone', 'phone_number'),
            ('Hospital', 'hospital_name'),
            ('Approval', 'approval_status'),
          ],
        'false_reports' => [
            ('Date', 'occurred_at'),
            ('Case', 'incident_id'),
            ('Resident', 'reporter_name'),
            ('Reason', 'reason'),
            ('Review', 'review_status'),
            ('Warnings', 'warning_number'),
            ('Responder', 'responder_name'),
          ],
        _ => const [('Date', 'occurred_at')],
      };

  String _buildLogsCsv(Map<String, List<Map<String, dynamic>>> records) {
    const headers = [
      'Category',
      'Date',
      'Case or User ID',
      'Incident or Name',
      'Status',
      'Location',
      'Reporter or Email',
      'Phone',
      'Responder',
      'Hospital',
      'Hospital Sent',
      'Hospital Arrived',
      'Reason',
      'Review Status',
      'Warnings',
      'Notes',
    ];
    final rows = <List<String>>[headers];
    for (final entry in records.entries) {
      for (final record in entry.value) {
        final isVolunteer = entry.key == 'volunteers';
        rows.add([
          _exportCategoryLabel(entry.key),
          _exportValue(record['occurred_at']),
          _exportValue(isVolunteer ? record['id'] : record['incident_id']),
          _exportValue(
              isVolunteer ? record['full_name'] : record['incident_type']),
          _exportValue(
              isVolunteer ? record['approval_status'] : record['status']),
          _exportValue(record['location']),
          _exportValue(
              isVolunteer ? record['work_email'] : record['reporter_name']),
          _exportValue(
              isVolunteer ? record['phone_number'] : record['reporter_phone']),
          _exportValue(record['responder_name']),
          _exportValue(record['hospital_name']),
          _exportValue(record['hospital_sent_at']),
          _exportValue(record['hospital_arrived_at']),
          _exportValue(record['reason']),
          _exportValue(record['review_status']),
          _exportValue(record['warning_number']),
          _exportValue(record['notes']),
        ]);
      }
    }
    return '\uFEFF${rows.map((row) => row.map(_csvCell).join(',')).join('\r\n')}';
  }

  String _csvCell(String value) {
    final safeValue =
        RegExp(r'^\s*[=+\-@]').hasMatch(value) ? "'$value" : value;
    return '"${safeValue.replaceAll('"', '""')}"';
  }

  String _exportValue(Object? value) => value?.toString() ?? '';

  String _exportCategoryLabel(String category) => switch (category) {
        'sos' => 'SOS reports',
        'hospital' => 'Hospital updates',
        'volunteers' => 'Volunteer sign-ups',
        'false_reports' => 'False reports',
        _ => category,
      };

  String _exportPeriodLabel(String period) => switch (period) {
        'today' => 'Today',
        'this_week' => 'This week',
        'this_month' => 'This month',
        _ => period,
      };

  Future<void> _reviewSignup(
    Map<String, dynamic> signup,
    String decision,
  ) async {
    final id = (signup['id'] as num?)?.toInt();
    if (id == null || id < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This applicant record has no valid ID.')),
      );
      return;
    }
    final currentStatus =
        signup['approval_status']?.toString() ?? _signupStatus;
    setState(() => _reviewingSignups.add(id));
    try {
      final response = await _apiClient.barangayAdminPortal(
        token: _token,
        action: 'review_signup',
        accountId: id,
        decision: decision,
        currentStatus: currentStatus,
      );
      if (response['success'] != true) {
        throw MobileApiException(
          response['message']?.toString() ??
              'The signup review could not be saved.',
        );
      }
      if (!mounted) return;
      setState(() {
        _selectedSignup = null;
        _approvals.removeWhere((item) => item['id'] == id);
        _signupItems.removeWhere((item) => item['id'] == id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(switch (decision) {
            'approve' when currentStatus == 'rejected' =>
              'Authorization restored. The account can now log in.',
            'approve' => 'Signup approved. The account can now log in.',
            'revoke' => 'Authorization revoked.',
            _ => 'Signup rejected.',
          }),
        ),
      );
      _loadPortal();
      _loadSignups(page: _signupPage);
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _reviewingSignups.remove(id));
    }
  }

  Future<void> _confirmSignupDecision(
    Map<String, dynamic> signup,
    String decision,
  ) async {
    final name = signup['full_name']?.toString() ?? 'this applicant';
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.transparent,
      builder: (sheetContext) => _SignupDecisionSheet(
        name: name,
        decision: decision,
        onCancel: () => Navigator.of(sheetContext).pop(false),
        onConfirm: () => Navigator.of(sheetContext).pop(true),
      ),
    );
    if (confirmed == true && mounted) {
      await _reviewSignup(signup, decision);
    }
  }

  void _openIncident(int id) {
    Nav.push(
      context,
      BarangayIncidentDetail(
        token: _token,
        incidentId: id,
        apiClient: _apiClient,
        onSos: _openSos,
        onNavigation: (index) {
          Navigator.of(context).pop();
          setState(() {
            _showNotifications = false;
            _tab = index;
          });
        },
      ),
    );
  }

  void _openNotifications() {
    setState(() => _showNotifications = true);
  }

  void _closeNotifications() {
    setState(() => _showNotifications = false);
  }

  void _openSos() {
    final role = AuthController.I.role;
    if (role == null) return;
    Nav.push(context, SosScreen(role: role));
  }

  Future<void> _loadQueues() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final reports = await _apiClient.barangayFalseReports(token: _token);
      final arrivals =
          await _apiClient.barangaySceneArrivalAlerts(token: _token);
      if (!mounted) return;
      setState(() {
        _reports = reports;
        _arrivalAlerts = arrivals;
        _loading = false;
      });
    } on MobileApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _refreshQueues() async {
    _refreshing = true;
    try {
      final reports = await _apiClient.barangayFalseReports(token: _token);
      final arrivals =
          await _apiClient.barangaySceneArrivalAlerts(token: _token);
      if (mounted) {
        setState(() {
          _reports = reports;
          _arrivalAlerts = arrivals;
          _error = null;
        });
      }
    } on MobileApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      _refreshing = false;
    }
  }

  Future<void> _acknowledgeArrival(SceneArrivalAlert alert) async {
    setState(() => _acknowledgingArrivals.add(alert.id));
    try {
      await _apiClient.acknowledgeBarangaySceneArrivalAlert(
        token: _token,
        alertId: alert.id,
      );
      if (!mounted) return;
      setState(() => _arrivalAlerts.removeWhere((item) => item.id == alert.id));
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _acknowledgingArrivals.remove(alert.id));
    }
  }

  Future<void> _review(
    FalseReportCase report, {
    required String decision,
    bool banFirstWarning = false,
    int? banDays,
  }) async {
    setState(() => _reviewing.add(report.id));
    try {
      final result = await _apiClient.reviewFalseReport(
        token: _token,
        falseReportId: report.id,
        decision: decision,
        banFirstWarning: banFirstWarning,
        banDays: banDays,
      );
      if (!mounted) return;
      setState(() => _reports.removeWhere((item) => item.id == report.id));
      final message = decision == 'dismiss'
          ? 'Report dismissed. No warning was added.'
          : result.automaticBan
              ? 'Confirmed. The resident was banned for ${result.banDays} days.'
              : result.banDays != null
                  ? 'Confirmed. A ${result.banDays}-day ban was applied.'
                  : 'Confirmed. Warning ${result.warningCount} was recorded.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    } on MobileApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _reviewing.remove(report.id));
    }
  }

  Future<void> _chooseReview(FalseReportCase report) async {
    final decision = await showDialog<_ReviewChoice>(
      context: context,
      builder: (context) => _ReviewDialog(report: report),
    );
    if (decision == null) return;
    await _review(
      report,
      decision: decision.decision,
      banFirstWarning: decision.banFirstWarning,
      banDays: decision.banDays,
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: _showNotifications
            ? AppBar(
                leading: IconButton(
                  tooltip: 'Back',
                  onPressed: _closeNotifications,
                  icon: const Icon(Icons.arrow_back_ios_new, size: 18),
                ),
                title: const Text(
                  'Notifications',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
                actions: [
                  TextButton(
                    onPressed: _markAllRead,
                    child: const Text(
                      'Mark all read',
                      style: TextStyle(
                        color: kBarangayAdminBrand,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              )
            : _tab == 1 || _tab == 2 || _tab == 0
                ? null
                : AppBar(
                    title: const Text(
                      'Profile',
                      style:
                          TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                  ),
        body: _showNotifications
            ? _buildNotifications()
            : switch (_tab) {
                0 => _buildDashboard(),
                1 => _buildApprovals(),
                2 => _buildLogs(),
                _ => _buildProfile(),
              },
        floatingActionButton: FloatingActionButton(
          heroTag: null,
          backgroundColor: kSos,
          elevation: 5,
          onPressed: _openSos,
          child: const Text(
            'SOS',
            style: TextStyle(
                color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13),
          ),
        ),
        bottomNavigationBar: BarangayAdminNavigation(
          selectedIndex: _showNotifications ? 0 : _tab,
          showApprovalBadge: (_signupCounts['pending'] ?? 0) > 0,
          onDestinationSelected: (index) {
            setState(() {
              _showNotifications = false;
              _tab = index;
            });
            if (index == 1) _loadSignups(status: _signupStatus);
          },
        ),
      );

  void _markAllRead() {
    setState(() {
      _readNotificationIds.addAll(
        _notifications.map((item) => item['id']?.toString() ?? ''),
      );
    });
  }

  Widget _errorMessage() {
    if (_portalError == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: Material(
        color: const Color(0xFFFFF1F0),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(children: [
            const Icon(Icons.error_outline, color: Colors.red, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(_portalError!,
                  style: const TextStyle(fontSize: 12, color: Colors.red)),
            ),
            IconButton(
              tooltip: 'Retry',
              visualDensity: VisualDensity.compact,
              onPressed: _loadPortal,
              icon: const Icon(Icons.refresh, size: 18),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _metricCard(
    String title,
    String key,
    IconData icon,
    Color iconColor,
  ) {
    final value = _summary[key] ?? 0;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFE8E5E5)),
      ),
      padding: const EdgeInsets.fromLTRB(10, 7, 10, 7),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 22,
            height: 22,
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(4),
            ),
            child: Icon(icon, color: iconColor, size: 15),
          ),
          const SizedBox(height: 3),
          Text('$value',
              style:
                  const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
          Text(title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 10, color: Colors.black54)),
        ],
      ),
    );
  }

  Widget _buildDashboard() {
    if (_portalLoading && _summary.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: _loadPortal,
      child: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 22, 18, 30),
          children: [
            _errorMessage(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(_error!,
                    style: const TextStyle(fontSize: 10, color: Colors.red)),
              ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      PhilippineClock(
                        builder: (_, time) => Text(
                          PhilippineTime.greeting(time),
                          style: const TextStyle(
                            fontSize: 12,
                            color: Colors.black87,
                          ),
                        ),
                      ),
                      Text(
                        AuthController.I.user?['barangay_name'] as String? ??
                            'Your Barangay',
                        style: const TextStyle(
                            fontSize: 19,
                            height: 1.15,
                            fontWeight: FontWeight.w800),
                      ),
                      Text(
                        '${AuthController.I.user?['full_name'] ?? 'Barangay Admin'} · Barangay Admin',
                        style: const TextStyle(
                          fontSize: 10,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Material(
                  color: const Color(0xFFF0EEEE),
                  borderRadius: BorderRadius.circular(5),
                  child: IconButton(
                    tooltip: 'Notifications',
                    visualDensity: VisualDensity.compact,
                    onPressed: _openNotifications,
                    icon: Badge(
                      isLabelVisible: _notifications.any((item) =>
                          !_readNotificationIds
                              .contains(item['id']?.toString() ?? '')),
                      smallSize: 6,
                      child: const Icon(Icons.notifications_active_outlined,
                          size: 17),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _approvalBanner(),
            const SizedBox(height: 8),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              childAspectRatio: 1.8,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
              children: [
                _metricCard('SOS Today', 'sos_today', Icons.emergency,
                    const Color(0xFFB35B5B)),
                _metricCard('In progress', 'in_progress', Icons.local_hospital,
                    const Color(0xFF526E9D)),
                _metricCard('Closed today', 'closed', Icons.check_box,
                    const Color(0xFF26764E)),
                _metricCard('False reports', 'false_reports', Icons.flag,
                    const Color(0xFF79766F)),
              ],
            ),
            const SizedBox(height: 12),
            Row(children: [
              const Expanded(
                child: Text('Latest activity',
                    style:
                        TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              ),
              TextButton(
                style: TextButton.styleFrom(
                    visualDensity: VisualDensity.compact,
                    padding: const EdgeInsets.symmetric(horizontal: 4)),
                onPressed: () => setState(() => _tab = 2),
                child: const Text('VIEW LOGS',
                    style: TextStyle(fontSize: 10, color: Colors.black87)),
              ),
            ]),
            if (_activity.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(
                    child: Text('No incidents have been reported yet.',
                        style: TextStyle(fontSize: 12))),
              )
            else
              _activityCard(_activity.take(3).toList()),
          ],
        ),
      ),
    );
  }

  Widget _approvalBanner() {
    final count = _signupCounts['pending'] ?? _approvals.length;
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: const ValueKey('approval-waiting-card'),
        borderRadius: BorderRadius.circular(10),
        onTap: () {
          setState(() {
            _showNotifications = false;
            _tab = 1;
          });
          _loadSignups(status: 'pending');
        },
        child: Ink(
          decoration: BoxDecoration(
            gradient: roleButtonGradient(kBarangayAdminBrand),
            borderRadius: BorderRadius.circular(10),
          ),
          child: SizedBox(
            height: 90,
            child: Stack(
              children: [
                Positioned(
                  right: 25,
                  top: -19,
                  child: Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.08),
                    ),
                  ),
                ),
                Positioned(
                  right: 25,
                  bottom: -24,
                  child: Container(
                    width: 78,
                    height: 78,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.08),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 9, 12, 9),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'NEEDS YOUR APPROVAL',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 8,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.2,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text.rich(
                        key: const ValueKey('approval-waiting-count'),
                        TextSpan(
                          children: [
                            TextSpan(
                              text: '$count ',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const TextSpan(
                              text: 'Approval waiting',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          height: 1.05,
                        ),
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Waiting to be authorized to help in your barangay',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 8,
                          height: 1.1,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Container(
                        height: 22,
                        padding: const EdgeInsets.symmetric(horizontal: 9),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE9E5E2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'REVIEW NOW',
                              style: TextStyle(
                                color: kBarangayAdminBrand,
                                fontSize: 8,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            SizedBox(width: 4),
                            Icon(
                              Icons.chevron_right,
                              color: kBarangayAdminBrand,
                              size: 13,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _activityCard(List<Map<String, dynamic>> activity) => Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: const Color(0xFFE8E5E5)),
          borderRadius: BorderRadius.circular(9),
        ),
        child: Column(
          children: [
            for (var index = 0; index < activity.length; index++)
              _activityRow(activity[index]),
          ],
        ),
      );

  Widget _activityRow(Map<String, dynamic> item) {
    final id = (item['incident_id'] as num?)?.toInt();
    final title = item['activity_title'] as String? ??
        '${item['incident_type'] ?? 'SOS'} received';
    return InkWell(
      onTap: id == null ? null : () => _openIncident(id),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
        child: Row(
          children: [
            Icon(
              title.toLowerCase().contains('hospital')
                  ? Icons.local_hospital
                  : title.toLowerCase().contains('responder')
                      ? Icons.person
                      : Icons.emergency,
              color: kBarangayAdminBrand,
              size: 16,
            ),
            const SizedBox(width: 7),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w700)),
                  Text(
                    item['message'] as String? ??
                        'Case #RS-${item['incident_id']} · ${item['location'] ?? ''}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 10, color: Colors.black54),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Text(
              _shortTime(item['occurred_at'] ?? item['reported_at']),
              style: const TextStyle(fontSize: 10, color: Colors.black54),
            ),
            const SizedBox(width: 3),
            const Icon(Icons.chevron_right, size: 16, color: Colors.black38),
          ],
        ),
      ),
    );
  }

  String _shortTime(Object? value) {
    final parsed =
        DateTime.tryParse((value ?? '').toString().replaceFirst(' ', 'T'));
    if (parsed == null) return '';
    final local = parsed.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    return '$hour:${local.minute.toString().padLeft(2, '0')} '
        '${local.hour >= 12 ? 'PM' : 'AM'}';
  }

  Widget _buildNotifications() {
    if (_portalLoading && _notifications.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: () async {
        await _refreshQueues();
        await _loadPortal();
      },
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 90),
        children: [
          _errorMessage(),
          if (_notifications.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 30),
              child: Center(
                child: Text('You are all caught up.',
                    style: TextStyle(fontSize: 11, color: Colors.black54)),
              ),
            )
          else
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border.all(color: const Color(0xFFE9E6E6)),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                children: [
                  for (var index = 0; index < _notifications.length; index++)
                    _notificationRow(
                      _notifications[index],
                      last: index == _notifications.length - 1,
                    ),
                ],
              ),
            ),
          if (_reports.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 14, bottom: 7),
              child: Text('False reports to review',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
            ),
            for (final report in _reports)
              _ReportCard(
                report: report,
                busy: _reviewing.contains(report.id),
                onReview: () => _chooseReview(report),
                onDismiss: () => _review(report, decision: 'dismiss'),
              ),
          ],
          if (_arrivalAlerts.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.only(top: 14, bottom: 7),
              child: Text('Responder scene arrivals',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
            ),
            for (final alert in _arrivalAlerts)
              _SceneArrivalCard(
                alert: alert,
                busy: _acknowledgingArrivals.contains(alert.id),
                onAcknowledge: () => _acknowledgeArrival(alert),
              ),
          ],
        ],
      ),
    );
  }

  Widget _notificationRow(Map<String, dynamic> item, {required bool last}) {
    final id = item['id']?.toString() ?? '';
    final read = _readNotificationIds.contains(id);
    final iconColor = switch (item['type']) {
      'signup' => const Color(0xFF49945F),
      'false_report' => const Color(0xFF858277),
      'arrival' => const Color(0xFF5378A9),
      _ => const Color(0xFF5378A9),
    };
    return InkWell(
      onTap: () {
        if (item['type'] == 'signup') {
          setState(() {
            _readNotificationIds.add(id);
            _showNotifications = false;
            _tab = 1;
          });
          _loadSignups(status: 'pending');
        } else if (item['type'] == 'false_report') {
          setState(() => _readNotificationIds.add(id));
        } else {
          setState(() => _readNotificationIds.add(id));
        }
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
        decoration: BoxDecoration(
          color: read ? Colors.white : const Color(0xFFFFFEFD),
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: Color(0xFFF0EEEE), width: 0.7),
                ),
        ),
        child: Row(
          children: [
            Container(
              width: 21,
              height: 21,
              decoration: BoxDecoration(
                color: iconColor.withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Icon(
                switch (item['type']) {
                  'signup' => Icons.person_add_alt_1,
                  'false_report' => Icons.flag,
                  'arrival' => Icons.location_on,
                  _ => Icons.calendar_today,
                },
                color: iconColor,
                size: 14,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${item['title'] ?? 'Update'}',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: read ? FontWeight.w500 : FontWeight.w700,
                      )),
                  Text('${item['message'] ?? ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style:
                          const TextStyle(fontSize: 9, color: Colors.black54)),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Text(_shortNotificationTime(item['created_at']),
                style: const TextStyle(fontSize: 9, color: Colors.black45)),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right, size: 16, color: Colors.black45),
          ],
        ),
      ),
    );
  }

  String _shortNotificationTime(Object? value) {
    final date =
        DateTime.tryParse((value ?? '').toString().replaceFirst(' ', 'T'));
    if (date == null) return '';
    if (DateUtils.isSameDay(date.toLocal(), DateTime.now())) return 'Today';
    final today = DateTime.now();
    final yesterday = today.subtract(const Duration(days: 1));
    if (DateUtils.isSameDay(date.toLocal(), yesterday)) return 'Yesterday';
    return '${date.month}/${date.day}';
  }

  Widget _buildApprovals() {
    final selectedSignup = _selectedSignup;
    if (selectedSignup != null) {
      return _buildSignupDetails(selectedSignup);
    }
    return RefreshIndicator(
      onRefresh: () => _loadSignups(status: _signupStatus),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 22, 18, 28),
        children: [
          _errorMessage(),
          const Text(
            'Volunteers and Responder',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 3),
          Text(
            'Volunteer sign-ups · ${AuthController.I.user?['barangay_name'] ?? 'Your Barangay'}',
            style: const TextStyle(fontSize: 11, color: Colors.black54),
          ),
          const SizedBox(height: 15),
          Row(
            children: [
              _signupFilter('pending', 'Pending'),
              _signupFilter('approved', 'Approved'),
              _signupFilter('rejected', 'Rejected'),
            ],
          ),
          const SizedBox(height: 12),
          if (_signupLoading)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: LinearProgressIndicator(minHeight: 2),
            ),
          if (_signupLoading && _signupItems.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 28),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_signupItems.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 35),
              child: Center(
                child: Text(
                  _emptySignupMessage(_signupStatus),
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 12, color: Colors.black54),
                ),
              ),
            )
          else
            for (final signup in _signupItems) _signupRow(signup),
          if (_signupTotalPages > 1) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                IconButton(
                  tooltip: 'Previous applicants',
                  onPressed: _signupPage > 1 && !_signupLoading
                      ? () => _loadSignups(page: _signupPage - 1)
                      : null,
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  '$_signupPage / $_signupTotalPages',
                  style: const TextStyle(fontSize: 11),
                ),
                IconButton(
                  tooltip: 'Next applicants',
                  onPressed: _signupPage < _signupTotalPages && !_signupLoading
                      ? () => _loadSignups(page: _signupPage + 1)
                      : null,
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _signupFilter(String status, String label) {
    final selected = _signupStatus == status;
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 3),
        child: Material(
          color: selected ? kBarangayAdminBrand : Colors.white,
          borderRadius: BorderRadius.circular(18),
          child: InkWell(
            key: ValueKey('signup-filter-$status'),
            borderRadius: BorderRadius.circular(18),
            onTap: selected ? null : () => _loadSignups(status: status),
            child: Container(
              height: 30,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                border: Border.all(
                  color:
                      selected ? kBarangayAdminBrand : const Color(0xFFE7E3E2),
                ),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Text(
                '$label ${_signupCounts[status] ?? 0}',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: selected ? Colors.white : Colors.black87,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _signupRow(Map<String, dynamic> signup) {
    final name = signup['full_name']?.toString() ?? 'New applicant';
    final role = signup['role']?.toString() ?? 'volunteer';
    final date = _shortTime(signup['created_at']);
    final isPending = _signupStatus == 'pending';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: Color(0xFFE9E6E5)),
      ),
      child: InkWell(
        key: ValueKey('signup-row-${signup['id']}'),
        borderRadius: BorderRadius.circular(14),
        onTap: () => setState(() => _selectedSignup = signup),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
          child: Row(
            children: [
              CircleAvatar(
                radius: 13,
                backgroundColor: const Color(0xFFEAEAEA),
                foregroundColor: const Color(0xFF383838),
                child: Text(
                  name.isEmpty ? '?' : name.characters.first.toUpperCase(),
                  style: const TextStyle(
                      fontSize: 10, fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '${_roleLabel(role)} · ${date.isEmpty ? 'Applicant' : date}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 9,
                        color: Colors.black54,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 5),
              if (isPending)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: kBarangayAdminBrand,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Text(
                    'PENDING',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 8,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                )
              else
                const Icon(Icons.chevron_right,
                    size: 18, color: Colors.black54),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSignupDetails(Map<String, dynamic> signup) {
    final name = signup['full_name']?.toString() ?? 'Applicant';
    final status = signup['approval_status']?.toString() ?? _signupStatus;
    final pending = status == 'pending';
    final approved = status == 'approved';
    final id = (signup['id'] as num?)?.toInt();
    final busy = id != null && _reviewingSignups.contains(id);
    final image = signup['profile_image']?.toString();
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 22),
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => setState(() => _selectedSignup = null),
              icon: const Icon(Icons.arrow_back_ios_new, size: 14),
              label: const Text('Approval'),
              style: TextButton.styleFrom(
                foregroundColor: Colors.black87,
                padding: EdgeInsets.zero,
                textStyle: const TextStyle(fontSize: 11),
              ),
            ),
          ),
          const SizedBox(height: 2),
          Center(
            child: CircleAvatar(
              radius: 28,
              backgroundColor: kBarangayAdminBrand,
              foregroundImage:
                  image == null || image.isEmpty ? null : NetworkImage(image),
              child: image == null || image.isEmpty
                  ? Text(
                      name.isEmpty ? '?' : name.characters.first.toUpperCase(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  : null,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            name,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Text(
            '${_roleLabel(signup['role']?.toString())} application · ${_shortTime(signup['created_at'])}',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 10, color: Colors.black54),
          ),
          const SizedBox(height: 7),
          Center(child: _signupStatusBadge(status)),
          const SizedBox(height: 14),
          _signupInfoCard([
            ('Email', signup['work_email']),
            ('Phone', signup['phone_number']),
            ('Age', signup['age']),
            ('Gender', _displayValue(signup['gender'])),
            ('Hospital', signup['hospital_name']),
            ('Barangay', signup['barangay_name']),
            if (signup['vehicle_type'] != null)
              ('Vehicle', signup['vehicle_type']),
            if (signup['license_number'] != null)
              ('License', signup['license_number']),
            ('Applied', signup['created_at']),
            if (signup['reviewed_at'] != null)
              ('Reviewed', signup['reviewed_at']),
            if (signup['reviewed_by_name'] != null)
              ('Reviewed by', signup['reviewed_by_name']),
          ]),
          if (pending) ...[
            const SizedBox(height: 14),
            const Text(
              'Review the applicant information before approving access.',
              style: TextStyle(fontSize: 11, color: Colors.black54),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 42,
              child: FilledButton(
                onPressed: busy
                    ? null
                    : () => _confirmSignupDecision(signup, 'approve'),
                style: FilledButton.styleFrom(
                  backgroundColor: kBarangayAdminBrand,
                  foregroundColor: Colors.white,
                  textStyle: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                child: busy
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('APPROVE'),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 40,
              child: OutlinedButton(
                onPressed: busy
                    ? null
                    : () => _confirmSignupDecision(signup, 'reject'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: kBarangayAdminBrand,
                  side: const BorderSide(color: kBarangayAdminBrand),
                  textStyle: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                child: const Text('REJECT'),
              ),
            ),
          ] else if (approved) ...[
            const SizedBox(height: 15),
            SizedBox(
              height: 40,
              child: OutlinedButton(
                onPressed: busy
                    ? null
                    : () => _confirmSignupDecision(signup, 'revoke'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: kBarangayAdminBrand,
                  side: const BorderSide(color: kBarangayAdminBrand),
                  textStyle: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                child: const Text('REVOKE AUTHORIZATION'),
              ),
            ),
          ] else ...[
            const SizedBox(height: 15),
            SizedBox(
              height: 42,
              child: FilledButton(
                onPressed: busy
                    ? null
                    : () => _confirmSignupDecision(signup, 'approve'),
                style: FilledButton.styleFrom(
                  backgroundColor: kBarangayAdminBrand,
                  foregroundColor: Colors.white,
                  textStyle: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                child: const Text('RESTORE AUTHORIZATION'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _signupInfoCard(List<(String, Object?)> rows) => Container(
        decoration: BoxDecoration(
          border: Border.all(color: const Color(0xFFE7E4E3)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            for (var index = 0; index < rows.length; index++)
              Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 76,
                      child: Text(
                        rows[index].$1,
                        style: const TextStyle(
                          fontSize: 10,
                          color: Colors.black54,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        _displayValue(rows[index].$2),
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      );

  Widget _signupStatusBadge(String status) {
    final color = switch (status) {
      'approved' => const Color(0xFF58A553),
      'rejected' => const Color(0xFF77716F),
      _ => const Color(0xFFB35B5B),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.17),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        status == 'approved' ? 'Authorized' : _roleLabel(status),
        style: TextStyle(
          color: color,
          fontSize: 9,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  String _roleLabel(String? role) => switch (role) {
        'responder' => 'Responder',
        'volunteer' => 'Volunteer',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
        _ => 'Volunteer',
      };

  String _emptySignupMessage(String status) => switch (status) {
        'approved' => 'No approved volunteers or responders yet.',
        'rejected' => 'No rejected sign-ups.',
        _ => 'No volunteer or responder sign-ups need approval.',
      };

  String _displayValue(Object? value) {
    if (value == null || value.toString().trim().isEmpty) return 'Not provided';
    return value.toString();
  }

  Widget _buildLogs() {
    return Column(
      children: [
        Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            color: kBarangayAdminBrand,
            borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
          ),
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  IconButton(
                    tooltip: 'Back to home',
                    onPressed: () => setState(() => _tab = 0),
                    visualDensity: VisualDensity.compact,
                    padding: EdgeInsets.zero,
                    alignment: Alignment.centerLeft,
                    icon: const Icon(Icons.arrow_back,
                        size: 19, color: Colors.white70),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Logs',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              '${AuthController.I.user?['barangay_name'] ?? 'Barangay'} · Everything that happens',
                              style: const TextStyle(
                                color: Colors.white70,
                                fontSize: 10,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Material(
                        color: Colors.white.withValues(alpha: 0.26),
                        borderRadius: BorderRadius.circular(5),
                        child: IconButton(
                          key: const ValueKey('export-logs-button'),
                          tooltip: 'Export logs',
                          onPressed: _exportingLogs ? null : _openLogExport,
                          visualDensity: VisualDensity.compact,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints.tightFor(
                            width: 38,
                            height: 38,
                          ),
                          icon: _exportingLogs
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(
                                  Icons.file_download_outlined,
                                  color: Colors.white,
                                  size: 21,
                                ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 34,
                    child: TextField(
                      controller: _searchController,
                      textInputAction: TextInputAction.search,
                      onSubmitted: (_) => _loadLogs(page: 1),
                      style: const TextStyle(fontSize: 12),
                      decoration: InputDecoration(
                        hintText: 'Search incident, name or place',
                        hintStyle: const TextStyle(fontSize: 11),
                        prefixIcon: const Icon(Icons.search, size: 18),
                        suffixIcon: IconButton(
                          tooltip: 'Search',
                          visualDensity: VisualDensity.compact,
                          onPressed: () => _loadLogs(page: 1),
                          icon: const Icon(Icons.arrow_forward, size: 17),
                        ),
                        filled: true,
                        fillColor: const Color(0xFFE8E4E3),
                        contentPadding: const EdgeInsets.symmetric(vertical: 7),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(18),
                          borderSide: BorderSide.none,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        _errorMessage(),
        Expanded(
          child: _logsLoading
              ? const Center(child: CircularProgressIndicator())
              : _logs.isEmpty
                  ? const Center(child: Text('No incidents match this search.'))
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                      itemCount: _logs.length + 1,
                      itemBuilder: (context, index) {
                        if (index == 0) {
                          return const Padding(
                            padding: EdgeInsets.only(left: 2, bottom: 7),
                            child: Text('Today',
                                style: TextStyle(
                                    fontSize: 10, fontWeight: FontWeight.w800)),
                          );
                        }
                        final log = _logs[index - 1];
                        return _logRow(
                          log,
                          first: index == 1,
                          last: index == _logs.length,
                        );
                      },
                    ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                TextButton.icon(
                  onPressed: _logPage > 1 && !_logsLoading
                      ? () => _loadLogs(page: _logPage - 1)
                      : null,
                  icon: const Icon(Icons.chevron_left),
                  label: const Text('Previous', style: TextStyle(fontSize: 11)),
                ),
                Text('$_logPage / $_logTotalPages',
                    style: const TextStyle(fontSize: 11)),
                TextButton.icon(
                  onPressed: _logPage < _logTotalPages && !_logsLoading
                      ? () => _loadLogs(page: _logPage + 1)
                      : null,
                  icon: const Icon(Icons.chevron_right),
                  label: const Text('Next', style: TextStyle(fontSize: 11)),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _logRow(
    Map<String, dynamic> item, {
    required bool first,
    required bool last,
  }) {
    final id = (item['incident_id'] as num?)?.toInt();
    final date = _shortTime(item['reported_at']);
    return Material(
      color: Colors.white,
      child: InkWell(
        onTap: id == null ? null : () => _openIncident(id),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            border: Border(
              left: const BorderSide(color: Color(0xFFE8E5E5)),
              right: const BorderSide(color: Color(0xFFE8E5E5)),
              top: first
                  ? const BorderSide(color: Color(0xFFE8E5E5))
                  : BorderSide.none,
              bottom: const BorderSide(color: Color(0xFFE8E5E5)),
            ),
            borderRadius: last
                ? const BorderRadius.vertical(bottom: Radius.circular(9))
                : null,
          ),
          child: Row(
            children: [
              const Icon(Icons.circle, size: 8, color: Color(0xFF1F2F29)),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${item['incident_type'] ?? 'Emergency incident'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontSize: 10, fontWeight: FontWeight.w800),
                    ),
                    Text(
                      'Case #RS-${item['incident_id']} · ${item['location'] ?? ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style:
                          const TextStyle(fontSize: 10, color: Colors.black54),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 7),
              Text(date,
                  style: const TextStyle(fontSize: 9, color: Colors.black54)),
              const SizedBox(width: 4),
              const Icon(Icons.chevron_right, size: 13, color: Colors.black45),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildProfile() => ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Icon(Icons.admin_panel_settings,
              size: 48, color: kBarangayAdminBrand),
          const SizedBox(height: 12),
          Text(
            'Welcome, ${AuthController.I.user?['full_name'] ?? 'Barangay Admin'}',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          const Text(
            'Review false-report flags submitted by responders in your assigned barangay. Dismissed reports remain in the audit history.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.black54),
          ),
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout),
            label: const Text('Log out'),
          ),
        ],
      );
}

class _ReportCard extends StatelessWidget {
  final FalseReportCase report;
  final bool busy;
  final VoidCallback onReview;
  final VoidCallback onDismiss;

  const _ReportCard({
    required this.report,
    required this.busy,
    required this.onReview,
    required this.onDismiss,
  });

  @override
  Widget build(BuildContext context) => Card(
        margin: EdgeInsets.zero,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Colors.black12),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Case #RS-${report.incidentId}',
                  style: const TextStyle(
                      fontSize: 15, fontWeight: FontWeight.w700)),
              const SizedBox(height: 3),
              Text(
                '${report.incidentType} · ${report.barangayName ?? report.location}',
                style: const TextStyle(fontSize: 12, color: Colors.black54),
              ),
              const Divider(height: 20),
              _InfoRow(label: 'Reason', value: report.reasonLabel),
              _InfoRow(label: 'Resident', value: report.reporterName),
              _InfoRow(
                  label: 'Email',
                  value: report.reporterEmail,
                  selectable: true),
              _InfoRow(
                label: 'Confirmed warnings',
                value: '${report.warningCount}',
              ),
              if (report.notes != null && report.notes!.isNotEmpty) ...[
                const SizedBox(height: 10),
                const Text('Responder notes',
                    style:
                        TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(height: 3),
                Text(report.notes!, style: const TextStyle(fontSize: 12)),
              ],
              const SizedBox(height: 12),
              Row(children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: busy ? null : onDismiss,
                    child: const Text('DISMISS'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: RoleColorButton(
                    color: kBarangayAdminBrand,
                    onPressed: busy ? null : onReview,
                    child: Text(report.warningCount == 0
                        ? 'REVIEW'
                        : 'REVIEW · AUTO 7-DAY BAN'),
                  ),
                ),
              ]),
              if (busy) const LinearProgressIndicator(minHeight: 2),
            ],
          ),
        ),
      );
}

class _SceneArrivalCard extends StatelessWidget {
  const _SceneArrivalCard({
    required this.alert,
    required this.busy,
    required this.onAcknowledge,
  });

  final SceneArrivalAlert alert;
  final bool busy;
  final VoidCallback onAcknowledge;

  @override
  Widget build(BuildContext context) => Card(
        margin: EdgeInsets.zero,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Colors.black12),
        ),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.location_on,
                      color: kBarangayAdminBrand, size: 19),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Responder arrived · Case #RS-${alert.incidentId}',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 5),
              Text(
                '${alert.incidentType} · ${alert.location}',
                style: const TextStyle(fontSize: 12),
              ),
              const SizedBox(height: 2),
              Text(
                'Responder: ${alert.responderName}',
                style: const TextStyle(color: Colors.black54, fontSize: 11),
              ),
              Text(
                alert.createdAt,
                style: const TextStyle(color: Colors.black54, fontSize: 10),
              ),
              const SizedBox(height: 6),
              SizedBox(
                width: double.infinity,
                height: 34,
                child: OutlinedButton(
                  onPressed: busy ? null : onAcknowledge,
                  style: OutlinedButton.styleFrom(
                    padding: EdgeInsets.zero,
                    visualDensity: VisualDensity.compact,
                  ),
                  child: busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('ACKNOWLEDGE'),
                ),
              ),
            ],
          ),
        ),
      );
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool selectable;

  const _InfoRow({
    required this.label,
    required this.value,
    this.selectable = false,
  });

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 5),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 138,
              child: Text(label,
                  style: const TextStyle(fontSize: 12, color: Colors.black54)),
            ),
            Expanded(
              child: selectable
                  ? SelectableText(value, style: const TextStyle(fontSize: 12))
                  : Text(value, style: const TextStyle(fontSize: 12)),
            ),
          ],
        ),
      );
}

class _ReviewChoice {
  final String decision;
  final bool banFirstWarning;
  final int? banDays;

  const _ReviewChoice.confirm({
    this.banFirstWarning = false,
    this.banDays,
  }) : decision = 'confirm';
  const _ReviewChoice.dismiss()
      : decision = 'dismiss',
        banFirstWarning = false,
        banDays = null;
}

class _ReviewDialog extends StatefulWidget {
  final FalseReportCase report;
  const _ReviewDialog({required this.report});

  @override
  State<_ReviewDialog> createState() => _ReviewDialogState();
}

class _ReviewDialogState extends State<_ReviewDialog> {
  final _daysController = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _daysController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final nextWarning = widget.report.warningCount + 1;
    final automaticBan = nextWarning >= 2;

    return AlertDialog(
      title: const Text('Review false report'),
      content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(automaticBan
                ? 'Confirming adds warning $nextWarning and bans the resident for the duration you choose.'
                : 'Confirming adds the resident’s first warning. You can optionally apply an account ban.'),
            const SizedBox(height: 12),
            TextField(
              controller: _daysController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Ban duration (days)',
                helperText: 'Enter 1–365 days',
                errorText: _error,
              ),
            ),
          ]),
      actions: [
        TextButton(
          onPressed: () =>
              Navigator.pop(context, const _ReviewChoice.dismiss()),
          child: const Text('Dismiss report'),
        ),
        if (!automaticBan)
          TextButton(
            onPressed: () => Navigator.pop(
                context, const _ReviewChoice.confirm(banFirstWarning: false)),
            child: const Text('Warning only'),
          ),
        FilledButton(
          onPressed: () {
            final days = int.tryParse(_daysController.text);
            if (days == null || days < 1 || days > 365) {
              setState(() => _error = 'Enter a number from 1 to 365.');
              return;
            }
            Navigator.pop(
              context,
              _ReviewChoice.confirm(
                banFirstWarning: !automaticBan,
                banDays: days,
              ),
            );
          },
          child: Text(automaticBan ? 'Confirm · ban' : 'Ban resident'),
        ),
      ],
    );
  }
}

class _LogExportRequest {
  const _LogExportRequest({
    required this.period,
    required this.includes,
    required this.formats,
  });

  final String period;
  final Set<String> includes;
  final Set<String> formats;
}

class _SignupDecisionSheet extends StatelessWidget {
  const _SignupDecisionSheet({
    required this.name,
    required this.decision,
    required this.onCancel,
    required this.onConfirm,
  });

  final String name;
  final String decision;
  final VoidCallback onCancel;
  final VoidCallback onConfirm;

  @override
  Widget build(BuildContext context) {
    final rejecting = decision == 'reject' || decision == 'revoke';
    final title = switch (decision) {
      'approve' => 'Approve this applicant?',
      'reject' => 'Reject this applicant?',
      'revoke' => 'Revoke authorization?',
      _ => 'Confirm authorization?',
    };
    final description = switch (decision) {
      'approve' =>
        '$name will be able to sign in after this application is approved.',
      'reject' => '$name will not be able to sign in to the system.',
      'revoke' => '$name will lose access to the system.',
      _ => '$name will regain access to the system.',
    };
    final actionLabel = switch (decision) {
      'approve' => 'APPROVE',
      'reject' => 'REJECT',
      'revoke' => 'REVOKE ACCESS',
      _ => 'RESTORE ACCESS',
    };

    return SizedBox.expand(
      child: Stack(
        children: [
          Positioned.fill(
            child: GestureDetector(
              onTap: onCancel,
              child: BackdropFilter(
                filter: ui.ImageFilter.blur(sigmaX: 7, sigmaY: 7),
                child: Container(color: Colors.black.withValues(alpha: 0.28)),
              ),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              top: false,
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(24, 18, 24, 20),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD8D6D6),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Icon(
                      rejecting
                          ? Icons.warning_amber_rounded
                          : Icons.verified_user_outlined,
                      color: rejecting
                          ? const Color(0xFFC62828)
                          : kBarangayAdminBrand,
                      size: 32,
                    ),
                    const SizedBox(height: 10),
                    Text(
                      title,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      description,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.4,
                        color: Colors.black54,
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      height: 46,
                      child: FilledButton(
                        key: const ValueKey('signup-decision-confirm'),
                        onPressed: onConfirm,
                        style: FilledButton.styleFrom(
                          backgroundColor: rejecting
                              ? const Color(0xFFC62828)
                              : kBarangayAdminBrand,
                          foregroundColor: Colors.white,
                          textStyle: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        child: Text(actionLabel),
                      ),
                    ),
                    const SizedBox(height: 7),
                    SizedBox(
                      width: double.infinity,
                      height: 42,
                      child: TextButton(
                        onPressed: onCancel,
                        child: const Text(
                          'CANCEL',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LogExportOverlay extends StatelessWidget {
  const _LogExportOverlay({
    required this.onDismiss,
    required this.onExport,
  });

  final VoidCallback onDismiss;
  final ValueChanged<_LogExportRequest> onExport;

  @override
  Widget build(BuildContext context) => SizedBox.expand(
        child: Stack(
          children: [
            Positioned.fill(
              child: GestureDetector(
                onTap: onDismiss,
                child: BackdropFilter(
                  filter: ui.ImageFilter.blur(sigmaX: 7, sigmaY: 7),
                  child: Container(
                    color: Colors.black.withValues(alpha: 0.28),
                  ),
                ),
              ),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: SafeArea(
                top: false,
                child: _LogExportSheet(onExport: onExport),
              ),
            ),
          ],
        ),
      );
}

class _LogExportSheet extends StatefulWidget {
  const _LogExportSheet({required this.onExport});

  final ValueChanged<_LogExportRequest> onExport;

  @override
  State<_LogExportSheet> createState() => _LogExportSheetState();
}

class _LogExportSheetState extends State<_LogExportSheet> {
  String _period = 'this_month';
  final Set<String> _includes = {
    'sos',
    'hospital',
    'volunteers',
    'false_reports',
  };
  final Set<String> _formats = {'pdf'};

  @override
  Widget build(BuildContext context) {
    final maxHeight = MediaQuery.sizeOf(context).height * 0.78;
    return Container(
      width: double.infinity,
      constraints: BoxConstraints(maxHeight: maxHeight),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(26, 20, 26, 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
              child: Container(
                width: 48,
                height: 5,
                decoration: BoxDecoration(
                  color: const Color(0xFFD8D6D6),
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
            const SizedBox(height: 22),
            const Text(
              'Export logs',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 2),
            const Text(
              'For reports and your records',
              style: TextStyle(fontSize: 12, color: Colors.black54),
            ),
            const SizedBox(height: 18),
            _sectionLabel('PERIOD'),
            const SizedBox(height: 7),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                _optionChip(
                  key: const ValueKey('export-period-today'),
                  label: 'Today',
                  selected: _period == 'today',
                  onTap: () => setState(() => _period = 'today'),
                ),
                _optionChip(
                  key: const ValueKey('export-period-this_week'),
                  label: 'This week',
                  selected: _period == 'this_week',
                  onTap: () => setState(() => _period = 'this_week'),
                ),
                _optionChip(
                  key: const ValueKey('export-period-this_month'),
                  label: 'This month',
                  selected: _period == 'this_month',
                  onTap: () => setState(() => _period = 'this_month'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _sectionLabel('INCLUDE'),
            const SizedBox(height: 7),
            Wrap(
              spacing: 6,
              runSpacing: 7,
              children: [
                _includeChip('sos', 'SOS'),
                _includeChip('hospital', 'Hospital'),
                _includeChip('volunteers', 'Volunteers'),
                _includeChip('false_reports', 'False Reports only'),
              ],
            ),
            const SizedBox(height: 16),
            _sectionLabel('FORMAT'),
            const SizedBox(height: 7),
            Wrap(
              spacing: 7,
              children: [
                _formatChip('pdf', 'PDF'),
                _formatChip('csv', 'CSV'),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: FilledButton(
                onPressed: _includes.isEmpty || _formats.isEmpty
                    ? null
                    : () => widget.onExport(
                          _LogExportRequest(
                            period: _period,
                            includes: Set.unmodifiable(_includes),
                            formats: Set.unmodifiable(_formats),
                          ),
                        ),
                style: FilledButton.styleFrom(
                  backgroundColor: kBarangayAdminBrand,
                  foregroundColor: Colors.white,
                  textStyle: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Text('EXPORT'),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              width: double.infinity,
              height: 40,
              child: TextButton(
                onPressed: () => Navigator.of(context).pop(),
                style: TextButton.styleFrom(
                  foregroundColor: Colors.black87,
                  backgroundColor: const Color(0xFFE4E4E4),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Text('Cancel'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionLabel(String text) => Text(
        text,
        style: const TextStyle(
          fontSize: 10,
          letterSpacing: 0.3,
          fontWeight: FontWeight.w800,
          color: Colors.black54,
        ),
      );

  Widget _includeChip(String value, String label) => _optionChip(
        key: ValueKey('export-include-$value'),
        label: label,
        selected: _includes.contains(value),
        onTap: () => setState(() {
          if (!_includes.add(value)) _includes.remove(value);
        }),
      );

  Widget _formatChip(String value, String label) => _optionChip(
        key: ValueKey('export-format-$value'),
        label: label,
        selected: _formats.contains(value),
        onTap: () => setState(() {
          if (!_formats.add(value)) _formats.remove(value);
        }),
      );

  Widget _optionChip({
    required Key key,
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) =>
      Semantics(
        key: key,
        button: true,
        selected: selected,
        child: Material(
          color: selected ? kBarangayAdminBrand : Colors.white,
          borderRadius: BorderRadius.circular(18),
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
              decoration: BoxDecoration(
                border: Border.all(
                  color:
                      selected ? kBarangayAdminBrand : const Color(0xFFBDB9B8),
                ),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  height: 1,
                  color: selected ? Colors.white : Colors.black87,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ),
          ),
        ),
      );
}
