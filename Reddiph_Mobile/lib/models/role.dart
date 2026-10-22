import 'package:flutter/material.dart';

import '../core/theme.dart';

class Role {
  final String name, desc, loginTitle;
  final Color color;
  final IconData icon;
  final List<String> fields;

  String get apiRole => name.toLowerCase().replaceAll(' ', '_');

  const Role(
    this.name,
    this.desc,
    this.color,
    this.icon,
    this.loginTitle,
    this.fields,
  );
}

const roles = <Role>[
  Role('Resident', 'Reports own emergency,\nviews status only',
      kResidentBrand, Icons.person, 'Resident login', ['Email', 'Password']),
  Role(
      'Responder',
      'SOS, dispatch,\ntransport, field routing',
      kResponderBrand,
      Icons.shield,
      'Barangay Responder login',
      ['Email', 'Password']),
  Role(
      'Doctor',
      'Case review, patient records,\narrival confirm',
      kDoctorBrand,
      Icons.medical_services,
      'Doctor login',
      ['Email', 'Password']),
  Role(
      'Nurse',
      'Resource prep, incoming\npatient monitoring',
      kNurseBrand,
      Icons.local_hospital,
      'Nurse login',
      ['Email', 'Password']),
  Role('Volunteer', 'Supplementary field support', kVolunteerBrand,
      Icons.volunteer_activism, 'Volunteer login', ['Email', 'Password']),
  Role(
      'Barangay Admin',
      'Barangay emergency coordination',
      kBarangayAdminBrand,
      Icons.admin_panel_settings,
      'Barangay Admin login',
      ['Email', 'Password']),
];
