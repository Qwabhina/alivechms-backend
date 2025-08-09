<?php

/**
 * Member Class
 * This class handles operations related to church members in the church management system.
 * It includes methods for registering, updating, deleting, retrieving a single member, and listing all members with pagination.
 * @package Member 
 * @version 1.0
 */
class Member
{
    /**
     * Registers a new church member.
     * Validates input, checks for duplicates, and inserts into the database.
     * @param array $data The member data to register.
     * @return array The created member ID and status.
     * @throws Exception If validation fails or database operations fail.
     */
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
            if ($existing) Helpers::sendFeedback('Username already exists');

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

            $orm->insert('memberrole', ['MbrID' => $mbrId, 'ChurchRoleID' => 6]);

            return ['status' => 'success', 'mbr_id' => $mbrId];
        } catch (Exception $e) {
            Helpers::logError('Member register error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
    }
    /**
     * Updates an existing church member.
     * Validates input, checks for duplicates, and updates the database.
     * @param int $mbrId The ID of the member to update.
     * @param array $data The member data to update.
     * @return array The updated member ID and status.
     * @throws Exception If validation fails, member not found, or database operations fail.
     */
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
            Helpers::logError('Member update error: ' . $e->getMessage());
            Helpers::sendFeedback($e->getMessage(), 400);
        }
    }
    /**
     * Deletes a church member.
     * Soft deletes the member by marking them as deleted in the database.
     * @param int $mbrId The ID of the member to delete.
     * @return array The status of the deletion.
     * @throws Exception If database operations fail.
     */
    public static function delete($mbrId)
    {
        $orm = new ORM();
        try {
            $orm->softDelete('churchmember', $mbrId, 'MbrID');
            return ['status' => 'success'];
        } catch (Exception $e) {
            Helpers::logError('Member delete error: ' . $e->getMessage());
            Helpers::sendFeedback('Failed to delete member', 400);
        }
    }
    /**
     * Retrieves a list of all church members with pagination.
     * @param int $page The page number for pagination.
     * @param int $limit The number of members per page.
     * @return array The list of members and total count.
     * @throws Exception If database operations fail.
     */
    public static function getAll($page = 1, $limit = 10)
    {
        $orm = new ORM();
        try {
            $offset = ($page - 1) * $limit;

            $members = $orm->selectWithJoin(
                baseTable: 'churchmember c',
                joins: [
                    ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                    ['table' => 'family f', 'on' => 'c.FamilyID = f.FamilyID', 'type' => 'LEFT']
                ],
                fields: ['c.*', 'GROUP_CONCAT(p.PhoneNumber) as PhoneNumbers', 'f.FamilyName'],
                conditions: ['c.Deleted' => 0],
                limit: $limit,
                offset: $offset,
                groupBy: ['c.MbrID']
            );

            $total = $orm->runQuery('SELECT COUNT(*) as total FROM churchmember WHERE Deleted = 0')[0]['total'];

            return [
                'data' => $members,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ]
            ];
        } catch (Exception $e) {
            Helpers::logError('Member list error: ' . $e->getMessage());
            Helpers::sendFeedback('Failed to retrieve members', 400);
        }
    }
    /**
     * Retrieves a single church member by ID.
     * @param int $mbrId The ID of the member to retrieve.
     * @return array The member data.
     * @throws Exception If the member is not found or database operations fail.
     */
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

            if (!$member) Helpers::sendFeedback('Member not found');
            return $member;
        } catch (Exception $e) {
            Helpers::logError('Member get error: ' . $e->getMessage());
            Helpers::sendFeedback('Failed to retrieve member', 400);
        }
    }
}
?>