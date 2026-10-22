<?php
// CLI test for SignupModel::validate: php Reddiph_mobile_api/tests/signup_validation_test.php

declare(strict_types=1);

require __DIR__ . '/../app/models/SignupModel.php';

$genders = ['male', 'female', 'other'];
$valid = [
    'full_name' => 'Test User',
    'phone_number' => '09123456789',
    'work_email' => 'user@reddiph.com',
    'age' => 30,
    'gender' => 'female',
    'password' => 'Password!123',
    'confirm_password' => 'Password!123',
    'hospital_id' => 1,
    'barangay_id' => 5,
    'license_number' => 'PRC-1',
];

$cases = [];
foreach (['resident', 'responder', 'doctor', 'nurse', 'volunteer', 'barangay_admin'] as $role) {
    $cases["valid {$role} has no errors"] = SignupModel::validate($valid, $role, $genders) === [];
}
$cases += [
    'admin signup accepts a matching server code' => SignupModel::adminSignupCodeMatches(
        'test-only-signup-code-0123456789abcdef',
        'test-only-signup-code-0123456789abcdef'
    ),
    'admin signup rejects a wrong code' => !SignupModel::adminSignupCodeMatches(
        'wrong-test-code',
        'test-only-signup-code-0123456789abcdef'
    ),
    'admin signup rejects a missing or short server code' =>
        !SignupModel::adminSignupCodeMatches('short', 'short'),
    'nurse needs no license' => SignupModel::validate(['license_number' => ''] + $valid, 'nurse', $genders) === [],
    'doctor without license is rejected' => SignupModel::validate(['license_number' => ''] + $valid, 'doctor', $genders) !== [],
    'hospital_admin role is rejected' => SignupModel::validate($valid, 'hospital_admin', $genders) !== [],
    'missing role is rejected' => SignupModel::validate($valid, '', $genders) !== [],
    'missing hospital is rejected' => SignupModel::validate(['hospital_id' => null] + $valid, 'resident', $genders) !== [],
    'missing barangay is rejected' => SignupModel::validate(['barangay_id' => null] + $valid, 'resident', $genders) !== [],
    'age 0 is rejected' => SignupModel::validate(['age' => 0] + $valid, 'resident', $genders) !== [],
    'age 121 is rejected' => SignupModel::validate(['age' => 121] + $valid, 'resident', $genders) !== [],
    'non-numeric age is rejected' => SignupModel::validate(['age' => 'abc'] + $valid, 'resident', $genders) !== [],
    'missing age is rejected' => SignupModel::validate(array_diff_key($valid, ['age' => 1]), 'resident', $genders) !== [],
    'gender outside the database list is rejected' => SignupModel::validate(['gender' => 'robot'] + $valid, 'resident', $genders) !== [],
    'any gender is rejected when the column is missing' => SignupModel::validate($valid, 'resident', []) !== [],
    'volunteer vehicle information may be omitted' => SignupModel::validate($valid, 'volunteer', $genders) === [],
    'volunteer vehicle information is length limited' => SignupModel::validate(['vehicle_type' => str_repeat('v', 81)] + $valid, 'volunteer', $genders) !== [],
    'bad email is rejected' => SignupModel::validate(['work_email' => 'nope'] + $valid, 'resident', $genders) !== [],
    'password mismatch is rejected' => SignupModel::validate(['confirm_password' => 'nope'] + $valid, 'resident', $genders) !== [],
    'password without uppercase is rejected' => SignupModel::validate([
        'password' => 'password!123',
        'confirm_password' => 'password!123',
    ] + $valid, 'resident', $genders) !== [],
    'password without lowercase is rejected' => SignupModel::validate([
        'password' => 'PASSWORD!123',
        'confirm_password' => 'PASSWORD!123',
    ] + $valid, 'resident', $genders) !== [],
    'password without special character is rejected' => SignupModel::validate([
        'password' => 'Password123',
        'confirm_password' => 'Password123',
    ] + $valid, 'resident', $genders) !== [],
];

$failed = 0;
foreach ($cases as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
