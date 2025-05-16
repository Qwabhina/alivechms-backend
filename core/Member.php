<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class Member
{
    public static function register($data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'first_name' => 'required',
                'family_name' => 'required',
                'email_address' => 'required|email',
                'username' => 'required',
                'password' => 'required'
            ]);

            $existing = $orm->getWhere('userauthentication', ['Username' => $data['username']]);
            if ($existing) {
                throw new Exception('Username already exists');
            }

            $mbrId = $orm->insert('churchmember', [
                'MbrFirstName' => $data['first_name'],
                'MbrFamilyName' => $data['family_name'],
                'MbrOtherNames' => $data['other_names'] ?? null,
                'MbrGender' => $data['gender'] ?? 'Male',
                'MbrEmailAddress' => $data['email_address'],
                'MbrResidentialAddress' => $data['address'] ?? null,
                'MbrDateOfBirth' => $data['date_of_birth'] ?? null,
                'MbrOccupation' => $data['occupation'] ?? 'Not Applicable',
                'MbrRegistrationDate' => date('Y-m-d'),
                'MbrMembershipStatus' => 'Active',
                'BranchID' => $data['branch_id'] ?? 1,
                'FamilyID' => $data['family_id'] ?? null
            ])['id'];

            if (!empty($data['phone_numbers'])) {
                foreach ($data['phone_numbers'] as $phone) {
                    $orm->insert('member_phone', [
                        'MbrID' => $mbrId,
                        'PhoneNumber' => $phone
                    ]);
                }
            }

            $orm->insert('userauthentication', [
                'MbrID' => $mbrId,
                'Username' => $data['username'],
                'PasswordHash' => password_hash($data['password'], PASSWORD_BCRYPT)
            ]);

            $orm->insert('memberrole', [
                'MbrID' => $mbrId,
                'ChurchRoleID' => 6 // Default: Member
            ]);

            return ['status' => 'success', 'mbr_id' => $mbrId];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Member register error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function update($mbrId, $data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'first_name' => 'required',
                'family_name' => 'required',
                'email_address' => 'required|email'
            ]);

            $updateData = [
                'MbrFirstName' => $data['first_name'],
                'MbrFamilyName' => $data['family_name'],
                'MbrOtherNames' => $data['other_names'] ?? null,
                'MbrGender' => $data['gender'] ?? 'Male',
                'MbrEmailAddress' => $data['email_address'],
                'MbrResidentialAddress' => $data['address'] ?? null,
                'MbrDateOfBirth' => $data['date_of_birth'] ?? null,
                'MbrOccupation' => $data['occupation'] ?? 'Not Applicable',
                'BranchID' => $data['branch_id'] ?? 1,
                'FamilyID' => $data['family_id'] ?? null
            ];

            $orm->update('churchmember', $updateData, ['MbrID' => $mbrId]);

            if (!empty($data['phone_numbers'])) {
                $orm->delete('member_phone', ['MbrID' => $mbrId]);
                foreach ($data['phone_numbers'] as $phone) {
                    $orm->insert('member_phone', [
                        'MbrID' => $mbrId,
                        'PhoneNumber' => $phone
                    ]);
                }
            }

            return ['status' => 'success', 'mbr_id' => $mbrId];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Member update error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function delete($mbrId)
    {
        $orm = new ORM();
        try {
            $orm->softDelete('churchmember', $mbrId, 'MbrID');
            return ['status' => 'success'];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Member delete error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function get($mbrId)
    {
        $orm = new ORM();
        try {
            $member = $orm->selectWithJoin(
                baseTable: 'churchmember c',
                joins: [
                    ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                    ['table' => 'family f', 'on' => 'c.FamilyID = f.FamilyID', 'type' => 'LEFT']
                ],
                fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers', 'f.FamilyName'],
                conditions: ['c.MbrID' => ':id', 'c.Deleted' => 0],
                params: [':id' => $mbrId]
            )[0] ?? null;

            if (!$member) {
                throw new Exception('Member not found');
            }
            return $member;
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Member get error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }
}
?>