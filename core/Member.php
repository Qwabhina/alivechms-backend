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
    /**
     * Adds a phone number for a member.
     * Validates input, checks for existing phone numbers, and inserts into the database.
     * @param int $memberId The ID of the member to add the phone number for.
     * @param array $data The phone number data to add.
     * @return array The created phone ID and status.
     * @throws Exception If validation fails, member not found, or phone number already exists.
     */
    public static function addPhone($memberId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'phone_number' => 'required|phone',
                'phone_type' => 'required|in:Mobile,Home,Work,Other',
                'is_primary' => 'optional|boolean'
            ]);

            // Validate member
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                throw new Exception('Invalid member');
            }

            // Check phone number uniqueness
            $existing = $orm->getWhere('member_phone', ['PhoneNumber' => $data['phone_number']]);
            if (!empty($existing)) {
                throw new Exception('Phone number already exists');
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // If setting as primary, unset others
            if (!empty($data['is_primary']) && $data['is_primary']) {
                $orm->update('member_phone', ['IsPrimary' => 0], ['MbrID' => $memberId]);
            }

            $phoneId = $orm->insert('member_phone', [
                'MbrID' => $memberId,
                'PhoneNumber' => $data['phone_number'],
                'PhoneType' => $data['phone_type'],
                'IsPrimary' => !empty($data['is_primary']) ? 1 : 0
            ])['id'];

            // Set primary if no other primary exists
            if (!empty($data['is_primary']) || !$orm->getWhere('member_phone', ['MbrID' => $memberId, 'IsPrimary' => 1])) {
                $orm->update('member_phone', ['IsPrimary' => 1], ['MemberPhoneID' => $phoneId]);
            }

            $orm->commit();
            return ['status' => 'success', 'phone_id' => $phoneId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('MemberEngagement addPhone error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Updates a phone number for a member.
     * Validates input, checks for existing phone numbers, and updates the database.
     * @param int $phoneId The ID of the phone number to update.
     * @param array $data The phone number data to update.
     * @return array The updated phone ID and status.
     * @throws Exception If validation fails, phone number not found, or phone number already exists.
     */
    public static function updatePhone($phoneId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'phone_number' => 'optional|phone',
                'phone_type' => 'optional|in:Mobile,Home,Work,Other',
                'is_primary' => 'optional|boolean'
            ]);

            // Validate phone exists
            $phone = $orm->getWhere('member_phone', ['MemberPhoneID' => $phoneId]);
            if (empty($phone)) {
                throw new Exception('Phone number not found');
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            $updateData = [];
            if (isset($data['phone_number'])) {
                $existing = $orm->getWhere('member_phone', ['PhoneNumber' => $data['phone_number'], 'MemberPhoneID != ' => $phoneId]);
                if (!empty($existing)) {
                    throw new Exception('Phone number already exists');
                }
                $updateData['PhoneNumber'] = $data['phone_number'];
            }
            if (isset($data['phone_type'])) {
                $updateData['PhoneType'] = $data['phone_type'];
            }
            if (isset($data['is_primary']) && $data['is_primary']) {
                $orm->update('member_phone', ['IsPrimary' => 0], ['MbrID' => $phone[0]['MbrID']]);
                $updateData['IsPrimary'] = 1;
            }

            if (!empty($updateData)) {
                $orm->update('member_phone', $updateData, ['MemberPhoneID' => $phoneId]);
            }

            $orm->commit();
            return ['status' => 'success', 'phone_id' => $phoneId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('MemberEngagement updatePhone error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Deletes a phone number for a member.
     * Validates input, checks if the phone number is primary, and deletes from the database.
     * @param int $phoneId The ID of the phone number to delete.
     * @return array The status of the deletion.
     * @throws Exception If phone number not found or is primary.
     */
    public static function deletePhone($phoneId)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate phone exists
            $phone = $orm->getWhere('member_phone', ['MemberPhoneID' => $phoneId]);
            if (empty($phone)) {
                throw new Exception('Phone number not found');
            }

            // Check if primary
            if ($phone[0]['IsPrimary']) {
                throw new Exception('Cannot delete primary phone number');
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            $orm->delete('member_phone', ['MemberPhoneID' => $phoneId]);

            $orm->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('MemberEngagement deletePhone error: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Retrieves all phone numbers for a member.
     * Validates member existence and retrieves phone numbers from the database.
     * @param int $memberId The ID of the member to retrieve phone numbers for.
     * @return array The list of phone numbers for the member.
     * @throws Exception If member not found or database operations fail.
     */
    public static function getPhones($memberId)
    {
        $orm = new ORM();
        try {
            // Validate member
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                throw new Exception('Invalid member');
            }

            $phones = $orm->getWhere('member_phone', ['MbrID' => $memberId]);
            return ['data' => $phones];
        } catch (Exception $e) {
            Helpers::logError('MemberEngagement getPhones error: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>