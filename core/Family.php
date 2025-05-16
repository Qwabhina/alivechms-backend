<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class Family
{
    public static function create($data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'family_name' => 'required',
                'head_of_household_id' => 'required|numeric',
                'branch_id' => 'required|numeric'
            ]);

            $familyId = $orm->insert('family', [
                'FamilyName' => $data['family_name'],
                'HeadOfHouseholdID' => $data['head_of_household_id'],
                'BranchID' => $data['branch_id'],
                'CreatedAt' => date('Y-m-d H:i:s')
            ])['id'];

            if (!empty($data['members'])) {
                foreach ($data['members'] as $member) {
                    Helpers::validateInput($member, [
                        'mbr_id' => 'required|numeric',
                        'role' => 'required'
                    ]);
                    $orm->insert('family_member', [
                        'FamilyID' => $familyId,
                        'MbrID' => $member['mbr_id'],
                        'FamilyRole' => $member['role']
                    ]);
                    $orm->update('churchmember', ['FamilyID' => $familyId], ['MbrID' => $member['mbr_id']]);
                }
            }

            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Family create error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function get($familyId)
    {
        $orm = new ORM();
        try {
            $family = $orm->selectWithJoin(
                baseTable: 'family f',
                joins: [
                    ['table' => 'family_member fm', 'on' => 'f.FamilyID = fm.FamilyID', 'type' => 'LEFT'],
                    ['table' => 'churchmember c', 'on' => 'fm.MbrID = c.MbrID', 'type' => 'LEFT']
                ],
                fields: ['f.*', 'GROUP_CONCAT(c.MbrFirstName, " ", c.MbrFamilyName, " (", fm.FamilyRole, ")") as Members'],
                conditions: ['f.FamilyID' => ':id'],
                params: [':id' => $familyId]
            )[0] ?? null;

            if (!$family) {
                throw new Exception('Family not found');
            }
            return $family;
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Family get error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function update($familyId, $data)
    {
        $orm = new ORM();
        try {
            Helpers::validateInput($data, [
                'family_name' => 'required',
                'head_of_household_id' => 'required|numeric',
                'branch_id' => 'required|numeric'
            ]);

            $orm->update('family', [
                'FamilyName' => $data['family_name'],
                'HeadOfHouseholdID' => $data['head_of_household_id'],
                'BranchID' => $data['branch_id']
            ], ['FamilyID' => $familyId]);

            if (isset($data['members'])) {
                $orm->delete('family_member', ['FamilyID' => $familyId]);
                $orm->update('churchmember', ['FamilyID' => null], ['FamilyID' => $familyId]);
                foreach ($data['members'] as $member) {
                    Helpers::validateInput($member, [
                        'mbr_id' => 'required|numeric',
                        'role' => 'required'
                    ]);
                    $orm->insert('family_member', [
                        'FamilyID' => $familyId,
                        'MbrID' => $member['mbr_id'],
                        'FamilyRole' => $member['role']
                    ]);
                    $orm->update('churchmember', ['FamilyID' => $familyId], ['MbrID' => $member['mbr_id']]);
                }
            }

            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Family update error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }

    public static function delete($familyId)
    {
        $orm = new ORM();
        try {
            $orm->delete('family_member', ['FamilyID' => $familyId]);
            $orm->update('churchmember', ['FamilyID' => null], ['FamilyID' => $familyId]);
            $orm->delete('family', ['FamilyID' => $familyId]);
            return ['status' => 'success'];
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/../logs/app.log', date('Y-m-d H:i:s') . ' - Family delete error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            throw $e;
        }
    }
}
