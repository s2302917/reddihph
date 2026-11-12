<?php

namespace Reddiph\Shared;

final class AuditActionCatalog
{
    private const ACTIONS = [
        'auth.login_success' => 'User Login Success',
        'auth.login_failed' => 'User Login Failure',
        'auth.logout' => 'User Logout Success',
        'user.created' => 'Create User Account',
        'user.updated' => 'Update User Details',
        'user.archived' => 'Archive User Account',
        'user.restored' => 'Restore User Account',
        'user.account_archived' => 'Archive User Account',
        'user.account_restored' => 'Restore User Account',
        'user.login_disabled' => 'Disable User Login',
        'user.login_enabled' => 'Enable User Login',
        'user.password_reset' => 'Reset User Password',
        'password.changed' => 'Change User Password',
        'password.reset_requested' => 'Request Password Reset',
        'hospital.created' => 'Create Hospital Record',
        'hospital.updated' => 'Update Hospital Details',
        'hospital.archived' => 'Archive Hospital Record',
        'hospital.restored' => 'Restore Hospital Record',
        'facility.hospital_created' => 'Create Hospital Record',
        'facility.hospital_updated' => 'Update Hospital Details',
        'facility.archive_hospital' => 'Archive Hospital Record',
        'facility.restore_hospital' => 'Restore Hospital Record',
        'barangay.created' => 'Create Barangay Record',
        'barangay.updated' => 'Update Barangay Details',
        'barangay.archived' => 'Archive Barangay Record',
        'barangay.restored' => 'Restore Barangay Record',
        'barangay.visibility_updated' => 'Update Barangay Visibility',
        'facility.barangay_created' => 'Create Barangay Record',
        'facility.barangay_updated' => 'Update Barangay Details',
        'facility.archive_barangay' => 'Archive Barangay Record',
        'facility.restore_barangay' => 'Restore Barangay Record',
        'facility.barangay_shown' => 'Show Barangay Listing',
        'facility.barangay_hidden' => 'Hide Barangay Listing',
        'incident.created' => 'Create Incident Report',
        'incident.updated' => 'Update Incident Report',
        'incident.status_updated' => 'Update Incident Status',
        'incident.note_added' => 'Add Incident Note',
        'incident.arrival_recorded' => 'Record Responder Arrival',
        'incident.handoff_recorded' => 'Record Patient Handoff',
        'patient.created' => 'Create Patient Record',
        'patient.updated' => 'Update Patient Record',
        'patient.deleted' => 'Delete Patient Record',
        'false_report.submitted' => 'Submit False Report',
        'false_report.reviewed' => 'Review False Report',
        'signup.created' => 'Create User Account',
        'signup.reviewed' => 'Review Account Signup',
        'doctor.review_saved' => 'Save Clinical Review',
        'schedule.created' => 'Create Staff Schedule',
        'schedule.updated' => 'Update Staff Schedule',
        'schedule.deleted' => 'Delete Staff Schedule',
        'nurse.task_updated' => 'Update Preparation Task',
        'nurse.note_saved' => 'Save Clinical Note',
        'nurse.prep_task_updated' => 'Update Preparation Task',
        'nurse.clinical_note_saved' => 'Save Clinical Note',
        'nurse.bed_assigned' => 'Assign Hospital Bed',
        'nurse.arrival_confirmed' => 'Confirm Nurse Arrival',
        'nurse.bed_updated' => 'Update Bed Status',
        'nurse.handoff_recorded' => 'Record Patient Handoff',
        'nurse.shift_swap_requested' => 'Request Shift Swap',
        'nurse.settings_updated' => 'Update Nurse Settings',
        'bed.created' => 'Create Hospital Bed',
        'bed.updated' => 'Update Hospital Bed',
        'bed.deleted' => 'Delete Hospital Bed',
        'resource.updated' => 'Update Facility Resources',
        'vehicle.created' => 'Create Ambulance Record',
        'vehicle.updated' => 'Update Ambulance Record',
        'vehicle.deleted' => 'Delete Ambulance Record',
        'referral.created' => 'Create Hospital Referral',
        'volunteer.requested' => 'Request Volunteer Assistance',
        'volunteer.response_updated' => 'Update Volunteer Request',
        'volunteer.review_saved' => 'Save Volunteer Review',
        'volunteer.availability_updated' => 'Update Volunteer Availability',
        'responder.alert_acknowledged' => 'Acknowledge Responder Alert',
        'staff.alert_acknowledged' => 'Acknowledge Staff Alert',
        'staff.availability_updated' => 'Update Staff Availability',
        'dispatch.assignment_updated' => 'Update Incident Assignment',
        'account.approval_updated' => 'Update Account Approval',
        'profile.updated' => 'Update User Profile',
        'system.migration_completed' => 'Record Database Migration',
        'system.operation_failed' => 'Record System Failure',
    ];

    public static function describe(string $actionCode): string
    {
        $description = self::ACTIONS[$actionCode] ?? null;
        if ($description === null || !self::isValidDescription($description)) {
            throw new \InvalidArgumentException('Choose a recognized audit action.');
        }

        return $description;
    }

    public static function isValidDescription(string $description): bool
    {
        return preg_match('/^[A-Za-z]+ [A-Za-z]+ [A-Za-z]+$/D', $description) === 1;
    }

    public static function supportedActions(): array
    {
        return self::ACTIONS;
    }
}
