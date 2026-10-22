import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:reddiph/models/incident_patient.dart';
import 'package:reddiph/services/mobile_api_client.dart';

void main() {
  group('MobileApiClient.volunteersForIncident', () {
    test('reports a missing deployed roster endpoint', () async {
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient(
            (_) async => http.Response('<html>Not found</html>', 404)),
      );

      await expectLater(
        client.volunteersForIncident(
          token: 'test-token',
          latitude: 10,
          longitude: 122,
        ),
        throwsA(
          isA<MobileApiException>().having(
            (error) => error.message,
            'message',
            allOf(
              contains('HTTP 404'),
              contains('/Reddiph_mobile_api/api/volunteers/roster.php'),
            ),
          ),
        ),
      );
    });
  });

  group('MobileApiClient.signupOptions', () {
    test('loads hospitals and their barangays', () async {
      late http.Request capturedRequest;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          capturedRequest = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'hospitals': [
                {
                  'id': 2,
                  'name': 'District Hospital',
                  'barangays': [
                    {'id': 21, 'name': 'Barangay A'},
                    {'id': 22, 'name': 'Barangay B'},
                  ],
                },
              ],
              'genders': ['male', 'female', 'other'],
            }),
            200,
          );
        }),
      );

      final options = await client.signupOptions();
      final hospitals = options.hospitals;

      expect(
        capturedRequest.url.toString(),
        'https://example.test/Reddiph_mobile_api/api/auth/signup_options.php',
      );
      expect(capturedRequest.method, 'GET');
      expect(hospitals.single.id, 2);
      expect(hospitals.single.barangays.last.name, 'Barangay B');
      expect(options.genders, ['male', 'female', 'other']);
    });
  });

  group('MobileApiClient.createAccount', () {
    test('posts signup fields and returns the new user id', () async {
      late http.Request capturedRequest;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          capturedRequest = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'user_id': 44,
              'message': 'Account created.',
            }),
            201,
          );
        }),
      );

      final responderId = await client.createAccount(
        role: 'responder',
        fullName: ' Test Responder ',
        workEmail: ' r@gmail.com ',
        phoneNumber: ' 09123456789 ',
        age: 30,
        gender: 'female',
        password: 'password123',
        confirmPassword: 'password123',
        hospitalId: 2,
        barangayId: 21,
      );

      expect(
        capturedRequest.url.toString(),
        'https://example.test/Reddiph_mobile_api/api/auth/signup.php',
      );
      expect(
        jsonDecode(capturedRequest.body),
        {
          'role': 'responder',
          'full_name': 'Test Responder',
          'work_email': 'r@gmail.com',
          'phone_number': '09123456789',
          'age': 30,
          'gender': 'female',
          'password': 'password123',
          'confirm_password': 'password123',
          'hospital_id': 2,
          'barangay_id': 21,
        },
      );
      expect(responderId, 44);
    });
  });

  group('MobileApiClient.login', () {
    test('posts email, role and password and returns the authenticated user',
        () async {
      late http.Request capturedRequest;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          capturedRequest = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'user': {
                'id': 17,
                'role': 'responder',
                'full_name': 'Test Responder',
                'barangay_name': 'San Isidro',
              },
              'token': '17.9999999999.signature',
            }),
            200,
          );
        }),
      );

      final user = await client.login(
        role: 'responder',
        email: ' r@gmail.com ',
        password: 'secret',
      );

      expect(
        capturedRequest.url.toString(),
        'https://example.test/Reddiph_mobile_api/api/auth/login.php',
      );
      expect(
        jsonDecode(capturedRequest.body),
        {'role': 'responder', 'email': 'r@gmail.com', 'password': 'secret'},
      );
      expect(user['role'], 'responder');
      expect(user['id'], 17);
      expect(user['barangay_name'], 'San Isidro');
      expect(user['token'], '17.9999999999.signature');
    });

    test('surfaces a server authentication failure', () async {
      final client = MobileApiClient(
        baseUrl: 'https://example.test/mobile_api/',
        client: MockClient((_) async => http.Response(
              jsonEncode({
                'success': false,
                'message': 'Invalid email or password.',
              }),
              401,
            )),
      );

      await expectLater(
        client.login(
            role: 'responder', email: 'r@gmail.com', password: 'wrong'),
        throwsA(
          isA<MobileApiException>().having(
            (error) => error.message,
            'message',
            'Invalid email or password.',
          ),
        ),
      );
    });

    test('requires an HTTPS API URL', () async {
      final client = MobileApiClient(
        baseUrl: 'http://example.test/mobile_api/',
        client: MockClient((_) async => http.Response('{}', 200)),
      );

      await expectLater(
        client.login(
            role: 'responder', email: 'r@gmail.com', password: 'secret'),
        throwsA(isA<MobileApiException>()),
      );
    });
  });

  group('MobileApiClient password reset', () {
    test('forgotPassword posts role and email', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
              jsonEncode({'success': true, 'message': 'Sent.'}), 200);
        }),
      );

      final message =
          await client.forgotPassword(role: 'nurse', email: ' n@gmail.com ');

      expect(captured.url.path,
          '/Reddiph_mobile_api/api/auth/forgot_password.php');
      expect(
          jsonDecode(captured.body), {'role': 'nurse', 'email': 'n@gmail.com'});
      expect(message, 'Sent.');
    });

    test('changePassword posts the token and both passwords', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(jsonEncode({'success': true}), 200);
        }),
      );

      await client.changePassword(
          token: 't', password: 'newpass12', confirmPassword: 'newpass12');

      expect(captured.url.path,
          '/Reddiph_mobile_api/api/auth/change_password.php');
      expect(jsonDecode(captured.body), {
        'token': 't',
        'password': 'newpass12',
        'confirm_password': 'newpass12',
      });
    });
  });

  group('MobileApiClient.incidentDetails', () {
    test('reads the dispatch details and builds the labels', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'incident': {
                'incident_type': 'SOS emergency alert',
                'location': 'Barangay A',
                'transport_name': 'RB-04',
                'transport_type': 'Ambulance',
                'eta_minutes': 6,
                'distance_km': 2.44,
                'sent_to_hospital': 1,
              },
            }),
            200,
          );
        }),
      );

      final details = await client.incidentDetails(incidentId: 7, token: 't');

      expect(captured.url.path, endsWith('api/incidents/status.php'));
      expect(jsonDecode(captured.body), {'incident_id': 7, 'token': 't'});
      expect(details.vehicleLabel, 'Ambulance · RB-04');
      expect(details.distanceLabel, '2.4 km away · ETA 6 min');
      expect(details.sentToHospital, isTrue);
    });
  });

  group('MobileApiClient.submitResidentReport', () {
    test('sends someone else report through the SOS responder-dispatch API',
        () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({'success': true, 'incident_id': 31}),
            201,
          );
        }),
      );

      final incidentId = await client.submitResidentReport(
        token: 'resident-token',
        latitude: 10.42,
        longitude: 122.92,
        incidentType: 'Medical',
        status: 'urgent',
        patientName: 'Alex Santos',
        patientAge: 28,
        patientGender: 'male',
        notes: 'Needs assistance',
        heartRate: 82,
        spo2: 97,
        bloodPressure: '120/80',
        sceneConditions: ['conscious', 'bleeding'],
      );

      expect(captured.url.path, endsWith('api/incidents/sos.php'));
      expect(captured.method, 'POST');
      expect(
        captured.headers['content-type'],
        startsWith('multipart/form-data; boundary='),
      );
      for (final value in [
        'resident-token',
        '10.42',
        '122.92',
        'Medical',
        'urgent',
        'resident_sos',
        'request_another_vehicle',
        '"patient_name":"Alex Santos"',
        '"patient_age":28',
        '"patient_gender":"male"',
        '"heart_rate":82',
        '"spo2":97',
        '"blood_pressure":"120/80"',
        '"notes":"Needs assistance"',
        '"scene_conditions":["conscious","bleeding"]',
      ]) {
        expect(captured.body, contains(value));
      }
      for (final field in [
        'token',
        'latitude',
        'longitude',
        'incident_type',
        'status',
        'reported_by',
        'request_another_vehicle',
        'patients',
      ]) {
        expect(captured.body, contains('name="$field"'));
      }
      expect(incidentId, 31);
    });

    test('sends multiple patients and the vehicle request option', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({'success': true, 'incident_id': 32}),
            201,
          );
        }),
      );

      await client.submitResidentReport(
        token: 'resident-token',
        latitude: 10.42,
        longitude: 122.92,
        incidentType: 'Vehicular',
        status: 'critical',
        patientName: 'Alex Santos',
        patientAge: 28,
        patientGender: 'male',
        notes: '',
        requestAnotherVehicle: true,
        reportedBy: 'on_scene',
        patients: const [
          DraftIncidentPatient(
            clientId: 'p1',
            name: 'Alex Santos',
            age: 28,
            gender: 'male',
            sceneConditions: ['conscious'],
          ),
          DraftIncidentPatient(
            clientId: 'p2',
            name: 'Jamie Cruz',
            age: 41,
            gender: 'female',
            sceneConditions: ['bleeding'],
          ),
        ],
      );

      expect(captured.body, contains('name="request_another_vehicle"'));
      expect(captured.body, contains('"request_another_vehicle"'));
      expect(captured.body, contains('name="reported_by"'));
      expect(captured.body, contains('on_scene'));
      expect(captured.body, contains('"patient_name":"Alex Santos"'));
      expect(captured.body, contains('"patient_name":"Jamie Cruz"'));
      expect(captured.body, contains('"scene_conditions":["bleeding"]'));
    });
  });

  group('MobileApiClient.activeIncident', () {
    test('returns the current authenticated resident incident', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'incident': {
                'incident_id': 18,
                'incident_type': 'Medical',
                'location': 'Purok 2',
                'barangay': 'San Isidro',
                'hospital': 'District Hospital',
                'responder_name': 'Responder One',
                'responder_user_id': 7,
                'latitude': 10.5,
                'longitude': 122.9,
                'responder_latitude': 10.4,
                'responder_longitude': 122.8,
                'sent_to_hospital': true,
              },
            }),
            200,
          );
        }),
      );

      final incident = await client.activeIncident(token: 'resident-token');

      expect(captured.url.path, endsWith('api/incidents/active.php'));
      expect(jsonDecode(captured.body), {'token': 'resident-token'});
      expect(incident?.incidentId, 18);
      expect(incident?.barangay, 'San Isidro');
      expect(incident?.responderUserId, 7);
      expect(incident?.responderLatitude, 10.4);
      expect(incident?.sentToHospital, isTrue);
    });

    test('returns null when the resident has no active case', () async {
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({'success': true, 'incident': null}),
            200,
          ),
        ),
      );

      expect(await client.activeIncident(token: 'resident-token'), isNull);
    });
  });

  group('MobileApiClient.responderAlerts', () {
    test('loads pending alert details and acknowledges the selected alert',
        () async {
      final requests = <http.Request>[];
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          requests.add(request);
          return http.Response(
            jsonEncode({
              'success': true,
              'alerts': [
                {
                  'alert_id': 3,
                  'incident_id': 18,
                  'incident_type': 'Medical',
                  'location': 'Purok 2',
                  'barangay': 'San Isidro',
                  'eta_minutes': 5,
                },
              ],
            }),
            200,
          );
        }),
      );

      final alerts = await client.responderAlerts(token: 'responder-token');
      await client.acknowledgeResponderAlert(
        token: 'responder-token',
        alertId: alerts.single.alertId,
      );

      expect(alerts.single.incidentId, 18);
      expect(alerts.single.barangay, 'San Isidro');
      expect(requests.first.url.path,
          endsWith('api/incidents/responder_alerts.php'));
      expect(jsonDecode(requests.first.body), {
        'token': 'responder-token',
        'action': 'queue',
      });
      expect(jsonDecode(requests.last.body), {
        'token': 'responder-token',
        'action': 'acknowledge',
        'alert_id': 3,
      });
    });
  });

  group('MobileApiClient.createResponderReport', () {
    test('previews the assigned hospital and estimated arrival', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'hospital': {
                'id': 4,
                'name': 'District Hospital',
                'latitude': 10.5,
                'longitude': 122.9,
              },
              'estimated_road_distance_km': 3.1,
              'eta_minutes': 6,
            }),
            200,
          );
        }),
      );

      final preview = await client.previewResponderReport(
        token: 'responder-token',
        latitude: 10.4,
        longitude: 122.8,
      );

      expect(captured.url.path, endsWith('api/responder/report.php'));
      expect(captured.url.queryParameters, {'action': 'preview'});
      expect(jsonDecode(captured.body), {
        'token': 'responder-token',
        'latitude': 10.4,
        'longitude': 122.8,
      });
      expect(preview.hospitalId, 4);
      expect(preview.hospitalName, 'District Hospital');
      expect(preview.etaMinutes, 6);
      expect(preview.estimatedRoadDistanceKm, 3.1);
    });

    test('submits incident and patient drafts as one multipart request',
        () async {
      late http.MultipartRequest captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: _CaptureClient((request) async {
          captured = request as http.MultipartRequest;
          return http.StreamedResponse(
            Stream.value(
                utf8.encode(jsonEncode({'success': true, 'incident_id': 91}))),
            201,
            headers: {'content-type': 'application/json'},
          );
        }),
      );

      final id = await client.createResponderReport(
        token: 'responder-token',
        incidentType: 'Vehicle collision',
        status: 'urgent',
        reportedBy: 'on_scene',
        location: 'GPS 10.123456, 122.123456',
        latitude: 10.123456,
        longitude: 122.123456,
        requestAnotherVehicle: true,
        patients: [
          const DraftIncidentPatient(
            clientId: 'draft-0',
            name: 'Patient A',
            age: 29,
            gender: 'female',
            heartRate: 84,
            spo2: 98,
            bloodPressure: '120/80',
            notes: 'Alert',
            sceneConditions: ['conscious', 'breathing_difficulty'],
          ),
        ],
      );

      expect(captured.url.path, '/Reddiph_mobile_api/api/responder/report.php');
      expect(captured.method, 'POST');
      expect(captured.fields, {
        'token': 'responder-token',
        'incident_type': 'Vehicle collision',
        'status': 'urgent',
        'reported_by': 'on_scene',
        'location': 'GPS 10.123456, 122.123456',
        'latitude': '10.123456',
        'longitude': '122.123456',
        'request_another_vehicle': '1',
        'patients': jsonEncode([
          {
            'patient_name': 'Patient A',
            'patient_age': 29,
            'patient_gender': 'female',
            'heart_rate': 84,
            'spo2': 98,
            'blood_pressure': '120/80',
            'notes': 'Alert',
            'scene_conditions': ['conscious', 'breathing_difficulty'],
          },
        ]),
      });
      expect(id, 91);
    });

    test('attaches patient photos using their matching patient index',
        () async {
      late http.MultipartRequest captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: _CaptureClient((request) async {
          captured = request as http.MultipartRequest;
          return http.StreamedResponse(
            Stream.value(
                utf8.encode(jsonEncode({'success': true, 'incident_id': 92}))),
            201,
            headers: {'content-type': 'application/json'},
          );
        }),
      );

      await client.createResponderReport(
        token: 'responder-token',
        incidentType: 'Medical',
        status: 'critical',
        reportedBy: 'on_scene',
        location: 'GPS 10, 122',
        latitude: 10,
        longitude: 122,
        requestAnotherVehicle: false,
        patients: [
          DraftIncidentPatient(
            clientId: 'draft-0',
            name: 'Patient A',
            age: 29,
            gender: 'female',
            photo: XFile.fromData(Uint8List.fromList([1, 2, 3]),
                name: 'patient.jpg'),
          ),
        ],
      );

      expect(captured.files, hasLength(1));
      expect(captured.files.single.field, 'photo_0');
      expect(captured.files.single.length, 3);
    });
  });

  group('MobileApiClient responder incident workflow', () {
    test('loads saved patient records', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'patients': [
                {
                  'id': 4,
                  'patient_name': 'Patient A',
                  'patient_age': 29,
                  'patient_gender': 'female',
                  'heart_rate': 84,
                  'spo2': 98,
                  'nurse_blood_pressure': '120/80',
                  'notes': 'Stable.',
                  'scene_conditions': ['conscious', 'bleeding'],
                  'has_photo': true,
                },
              ],
            }),
            200,
          );
        }),
      );

      final patients = await client.responderIncidentPatients(
        token: 'token',
        incidentId: 17,
      );

      expect(captured.url.path, endsWith('api/responder/patients.php'));
      expect(jsonDecode(captured.body), {
        'token': 'token',
        'incident_id': 17,
        'action': 'list',
      });
      expect(patients.single.name, 'Patient A');
      expect(patients.single.bloodPressure, '120/80');
      expect(patients.single.sceneConditions, ['conscious', 'bleeding']);
      expect(patients.single.hasPhoto, isTrue);
    });

    test('uploads patient fields and the selected photo', () async {
      late http.Request captured;
      late http.MultipartRequest multipart;
      final transport = _CaptureClient((request) async {
        multipart = request as http.MultipartRequest;
        captured = http.Request('POST', request.url)
          ..headers.addAll(request.headers);
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode({
            'success': true,
            'patient': {
              'id': 9,
              'patient_name': 'Patient B',
              'patient_age': 34,
              'patient_gender': 'male',
              'heart_rate': 72,
              'spo2': 99,
              'nurse_blood_pressure': '118/78',
              'notes': 'Alert.',
              'scene_conditions': ['unconscious'],
              'has_photo': true,
            },
          }))),
          201,
          headers: {'content-type': 'application/json'},
        );
      });
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: transport,
      );

      final patient = await client.saveResponderPatient(
        token: 'token',
        incidentId: 17,
        name: 'Patient B',
        age: 34,
        gender: 'male',
        heartRate: 72,
        spo2: 99,
        bloodPressure: '118/78',
        notes: 'Alert.',
        sceneConditions: ['unconscious'],
        photo:
            XFile.fromData(Uint8List.fromList([1, 2, 3]), name: 'patient.jpg'),
      );

      expect(captured.url.path, endsWith('api/responder/patient.php'));
      expect(multipart.fields['patient_name'], 'Patient B');
      expect(multipart.fields['blood_pressure'], '118/78');
      expect(
        multipart.fields['scene_conditions'],
        jsonEncode(['unconscious']),
      );
      expect(multipart.files, hasLength(1));
      expect(multipart.files.single.length, 3);
      expect(patient.hasPhoto, isTrue);
    });

    test('posts incident actions', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(jsonEncode({'success': true}), 200);
        }),
      );

      await client.responderIncidentAction(
        token: 'token',
        incidentId: 17,
        action: 'request_vehicle',
      );

      expect(captured.url.path, endsWith('api/responder/incident_action.php'));
      expect(jsonDecode(captured.body), {
        'token': 'token',
        'incident_id': 17,
        'action': 'request_vehicle',
      });
    });

    test('posts hospital submission with condition and volunteer selection',
        () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(jsonEncode({'success': true}), 200);
        }),
      );

      await client.responderIncidentAction(
        token: 'token',
        incidentId: 17,
        action: 'send_to_hospital',
        status: 'urgent',
        volunteerUserId: 28,
      );

      expect(jsonDecode(captured.body), {
        'token': 'token',
        'incident_id': 17,
        'action': 'send_to_hospital',
        'status': 'urgent',
        'volunteer_user_id': 28,
      });
    });

    test('updates responder GPS presence through the dashboard endpoint',
        () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(jsonEncode({'success': true}), 200);
        }),
      );

      await client.updateResponderPresence(
        token: 'token',
        latitude: 10.42,
        longitude: 122.92,
      );

      expect(captured.url.path, endsWith('api/responder/dashboard.php'));
      expect(jsonDecode(captured.body), {
        'token': 'token',
        'page': 1,
        'latitude': 10.42,
        'longitude': 122.92,
      });
    });

    test('posts the separate scene-arrival action', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({'success': true, 'scene_arrived': true}),
            200,
          );
        }),
      );

      final result = await client.responderIncidentAction(
        token: 'token',
        incidentId: 17,
        action: 'mark_scene_arrived',
      );

      expect(jsonDecode(captured.body), {
        'token': 'token',
        'incident_id': 17,
        'action': 'mark_scene_arrived',
      });
      expect(result['scene_arrived'], isTrue);
    });

    test('posts responder GPS coordinates and returns arrival status',
        () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({'success': true, 'arrived': true, 'status': 'arrived'}),
            200,
          );
        }),
      );

      final result = await client.responderIncidentAction(
        token: 'token',
        incidentId: 17,
        action: 'update_transport_location',
        latitude: 10.4228,
        longitude: 122.9138,
      );

      expect(jsonDecode(captured.body), {
        'token': 'token',
        'incident_id': 17,
        'action': 'update_transport_location',
        'latitude': 10.4228,
        'longitude': 122.9138,
      });
      expect(result['arrived'], isTrue);
      expect(result['status'], 'arrived');
    });
  });

  group('MobileApiClient false-report moderation', () {
    test('submits the selected false-report reason and notes', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({'success': true, 'false_report_id': 8}),
            201,
          );
        }),
      );

      await client.submitFalseReport(
        token: 'responder-token',
        incidentId: 91,
        reason: 'wrong_location',
        notes: 'The address does not match.',
      );

      expect(captured.url.path, endsWith('api/responder/false_report.php'));
      expect(jsonDecode(captured.body), {
        'token': 'responder-token',
        'incident_id': 91,
        'reason': 'wrong_location',
        'notes': 'The address does not match.',
      });
    });

    test('loads a barangay-scoped false-report queue', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'reports': [
                {
                  'id': 8,
                  'incident_id': 91,
                  'reason': 'wrong_location',
                  'notes': 'Address mismatch.',
                  'created_at': '2026-10-17 12:00:00',
                  'incident_type': 'SOS emergency alert',
                  'location': 'Brgy A',
                  'barangay_name': 'Brgy A',
                  'reporter_name': 'Jamie Cruz',
                  'reporter_email': 'jamie@example.test',
                  'warning_count': 1,
                  'banned_until': null,
                },
              ],
            }),
            200,
          );
        }),
      );

      final reports = await client.barangayFalseReports(token: 'admin-token');

      expect(captured.url.queryParameters, {'action': 'queue'});
      expect(jsonDecode(captured.body), {'token': 'admin-token'});
      expect(reports.single.id, 8);
      expect(reports.single.reasonLabel, 'Wrong location');
      expect(reports.single.warningCount, 1);
    });

    test('posts admin decision and reads warning/ban outcome', () async {
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': true,
              'warning_count': 2,
              'banned_until': '2026-10-24 12:00:00',
              'ban_days': 7,
              'automatic_ban': true,
            }),
            200,
          );
        }),
      );

      final result = await client.reviewFalseReport(
        token: 'admin-token',
        falseReportId: 8,
        decision: 'confirm',
      );

      expect(captured.url.queryParameters, {'action': 'review'});
      expect(jsonDecode(captured.body), {
        'token': 'admin-token',
        'false_report_id': 8,
        'decision': 'confirm',
        'ban_first_warning': false,
      });
      expect(result.warningCount, 2);
      expect(result.automaticBan, isTrue);
      expect(result.bannedUntil, '2026-10-24 12:00:00');
      expect(result.banDays, 7);
    });
  });

  test('loads and acknowledges Barangay Admin scene arrival alerts', () async {
    final requests = <http.Request>[];
    final client = MobileApiClient(
      baseUrl: 'https://example.test/Reddiph_mobile_api/',
      client: MockClient((request) async {
        requests.add(request);
        if (jsonDecode(request.body)['action'] == 'queue') {
          return http.Response(
            jsonEncode({
              'success': true,
              'alerts': [
                {
                  'id': 3,
                  'incident_id': 41,
                  'message': 'Responder arrived at Brgy A.',
                  'incident_type': 'Vehicular',
                  'location': 'Brgy A',
                  'responder_name': 'Mark Reyes',
                  'created_at': '2026-10-20 09:30:00',
                },
              ],
            }),
            200,
          );
        }
        return http.Response(jsonEncode({'success': true}), 200);
      }),
    );

    final alerts =
        await client.barangaySceneArrivalAlerts(token: 'admin-token');
    await client.acknowledgeBarangaySceneArrivalAlert(
      token: 'admin-token',
      alertId: 3,
    );

    expect(alerts.single.incidentId, 41);
    expect(alerts.single.responderName, 'Mark Reyes');
    expect(requests.first.url.path,
        endsWith('api/admin/scene_arrival_alerts.php'));
    expect(jsonDecode(requests.first.body), {
      'token': 'admin-token',
      'action': 'queue',
    });
    expect(jsonDecode(requests.last.body), {
      'token': 'admin-token',
      'action': 'acknowledge',
      'alert_id': 3,
    });
  });

  group('MobileApiClient session validation', () {
    test('checks the signed-in account and invalidates it when banned',
        () async {
      var invalidated = false;
      MobileApiClient.onSessionInvalid = () => invalidated = true;
      addTearDown(() => MobileApiClient.onSessionInvalid = null);
      late http.Request captured;
      final client = MobileApiClient(
        baseUrl: 'https://example.test/Reddiph_mobile_api/',
        client: MockClient((request) async {
          captured = request;
          return http.Response(
            jsonEncode({
              'success': false,
              'message': 'Your session is no longer active.',
            }),
            401,
          );
        }),
      );

      await expectLater(
        client.validateSession(token: 'signed-token'),
        throwsA(isA<MobileApiException>()),
      );

      expect(captured.url.path, endsWith('api/auth/session.php'));
      expect(jsonDecode(captured.body), {'token': 'signed-token'});
      expect(invalidated, isTrue);
    });
  });
}

class _CaptureClient extends http.BaseClient {
  _CaptureClient(this.handler);

  final Future<http.StreamedResponse> Function(http.BaseRequest) handler;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) =>
      handler(request);
}
