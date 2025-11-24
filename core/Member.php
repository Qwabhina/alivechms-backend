<?php

/**
 * Member Management
 *
 * Handles registration, profile updates, soft deletion,
 * retrieval (single + paginated), and related phone/family data.
 *
 * All operations are fully validated, transactional, and audited.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Member
{
    /**
     * Register a new church member with authentication credentials
     *
     * @param array $data Registration payload
     * @return array ['status' => 'success', 'mbr_id' => int]
     * @throws Exception On validation or database failure
     */
    public static function register(array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'first_name'     => 'required|max:50',
            'family_name'    => 'required|max:50',
            'email_address'  => 'required|email',
            'username'       => 'required|max:50',
            'password'       => 'required',
            'gender'         => 'in:Male,Female,Other|nullable',
            'branch_id'      => 'numeric|nullable',
        ]);

        // Uniqueness checks
        if (!empty($orm->getWhere('userauthentication', ['Username' => $data['username']]))) {
            Helpers::sendFeedback('Username already exists', 400);
        }

        if (!empty($orm->getWhere('churchmember', ['MbrEmailAddress' => $data['email_address']]))) {
            Helpers::sendFeedback('Email address already in use', 400);
        }

        $orm->beginTransaction();
        try {
            $memberData = [
                'MbrFirstName'         => $data['first_name'],
                'MbrFamilyName'        => $data['family_name'],
                'MbrOtherNames'        => $data['other_names'] ?? null,
                'MbrGender'            => $data['gender'] ?? 'Other',
                'MbrEmailAddress'      => $data['email_address'],
                'MbrResidentialAddress' => $data['address'] ?? null,
                'MbrDateOfBirth'       => $data['date_of_birth'] ?? null,
                'MbrOccupation'        => $data['occupation'] ?? 'Not Specified',
                'MbrRegistrationDate'  => date('Y-m-d'),
                'MbrMembershipStatus'  => 'Active',
                'BranchID'             => (int)($data['branch_id'] ?? 1),
                'FamilyID'             => !empty($data['family_id']) ? (int)$data['family_id'] : null,
                'Deleted'              => 0
            ];

            $mbrId = $orm->insert('churchmember', $memberData)['id'];

            // Handle phone numbers
            if (!empty($data['phone_numbers']) && is_array($data['phone_numbers'])) {
                foreach ($data['phone_numbers'] as $index => $phone) {
                    $phone = trim($phone);
                    if ($phone === '') {
                        continue;
                    }
                    $isPrimary = $index === 0 ? 1 : 0;
                    $orm->insert('member_phone', [
                        'MbrID'      => $mbrId,
                        'PhoneNumber' => $phone,
                        'PhoneType'  => 'Mobile',
                        'IsPrimary'  => $isPrimary
                    ]);
                }
            }

            // Create authentication record
            $orm->insert('userauthentication', [
                'MbrID'        => $mbrId,
                'Username'     => $data['username'],
                'PasswordHash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'CreatedAt'    => date('Y-m-d H:i:s')
            ]);

            // Assign default "Member" role (RoleID 6)
            $orm->insert('memberrole', [
                'MbrID'        => $mbrId,
                'ChurchRoleID' => 6
            ]);

            $orm->commit();

            Helpers::logError("New member registered: MbrID $mbrId ({$data['username']})");
            return ['status' => 'success', 'mbr_id' => $mbrId];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError("Member registration failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing member profile
     *
     * @param int   $mbrId Member ID
     * @param array $data  Updated data
     * @return array ['status' => 'success', 'mbr_id' => int]
     * @throws Exception On validation or database failure
     */
    public static function update(int $mbrId, array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'first_name'     => 'required|max:50',
            'family_name'    => 'required|max:50',
            'email_address'  => 'required|email',
            'gender'         => 'in:Male,Female,Other|nullable',
            'branch_id'      => 'numeric|nullable',
        ]);

        // Prevent email conflict
        $conflict = $orm->runQuery(
            "SELECT MbrID FROM churchmember WHERE MbrEmailAddress = :email AND MbrID != :id AND Deleted = 0",
            [':email' => $data['email_address'], ':id' => $mbrId]
        );
        if (!empty($conflict)) {
            Helpers::sendFeedback('Email address already in use by another member', 400);
        }

        $updateData = [
            'MbrFirstName'         => $data['first_name'],
            'MbrFamilyName'        => $data['family_name'],
            'MbrOtherNames'        => $data['other_names'] ?? null,
            'MbrGender'            => $data['gender'] ?? 'Other',
            'MbrEmailAddress'      => $data['email_address'],
            'MbrResidentialAddress' => $data['address'] ?? null,
            'MbrDateOfBirth'       => $data['date_of_birth'] ?? null,
            'MbrOccupation'        => $data['occupation'] ?? 'Not Specified',
            'BranchID'             => !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
            'FamilyID'             => !empty($data['family_id']) ? (int)$data['family_id'] : null,
        ];

        $orm->beginTransaction();
        try {
            $orm->update('churchmember', $updateData, ['MbrID' => $mbrId]);

            // Replace phone numbers if provided
            if (isset($data['phone_numbers']) && is_array($data['phone_numbers'])) {
                $orm->delete('member_phone', ['MbrID' => $mbrId]);
                foreach ($data['phone_numbers'] as $index => $phone) {
                    $phone = trim($phone);
                    if ($phone === '') {
                        continue;
                    }
                    $isPrimary = $index === 0 ? 1 : 0;
                    $orm->insert('member_phone', [
                        'MbrID'      => $mbrId,
                        'PhoneNumber' => $phone,
                        'PhoneType'  => 'Mobile',
                        'IsPrimary'  => $isPrimary
                    ]);
                }
            }

            $orm->commit();
            return ['status' => 'success', 'mbr_id' => $mbrId];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError("Member update failed for MbrID $mbrId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete a member
     *
     * @param int $mbrId Member ID
     * @return array ['status' => 'success']
     */
    public static function delete(int $mbrId): array
    {
        $orm = new ORM();

        $affected = $orm->softDelete('churchmember', $mbrId, 'MbrID');
        if ($affected === 0) {
            Helpers::sendFeedback('Member not found or already deleted', 404);
        }

        Helpers::logError("Member soft-deleted: MbrID $mbrId");
        return ['status' => 'success'];
    }

    /**
     * Retrieve a single member with phones and family
     *
     * @param int $mbrId Member ID
     * @return array Member data
     */
    public static function get(int $mbrId): array
    {
        $orm = new ORM();

        $result = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [
                ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                ['table' => 'family f',       'on' => 'c.FamilyID = f.FamilyID', 'type' => 'LEFT']
            ],
            fields: [
                'c.*',
                "GROUP_CONCAT(DISTINCT p.PhoneNumber ORDER BY p.IsPrimary DESC SEPARATOR ', ') AS PhoneNumbers",
                'f.FamilyName'
            ],
            conditions: ['c.MbrID' => ':id', 'c.Deleted' => 0],
            params: [':id' => $mbrId],
            groupBy: ['c.MbrID']
        );

        if (empty($result)) {
            Helpers::sendFeedback('Member not found', 404);
        }

        $member = $result[0];
        $member['PhoneNumbers'] = $member['PhoneNumbers'] ? explode(', ', $member['PhoneNumbers']) : [];

        return $member;
    }

    /**
     * Retrieve paginated list of active members
     *
     * @param int $page  Page number (1-based)
     * @param int $limit Items per page
     * @return array Paginated result
     */
    public static function getAll(int $page = 1, int $limit = 10): array
    {
        $orm    = new ORM();
        $offset = ($page - 1) * $limit;

        $members = $orm->selectWithJoin(
            baseTable: 'churchmember c',
            joins: [
                ['table' => 'member_phone p', 'on' => 'c.MbrID = p.MbrID', 'type' => 'LEFT'],
                ['table' => 'family f',       'on' => 'c.FamilyID = f.FamilyID', 'type' => 'LEFT']
            ],
            fields: [
                'c.MbrID',
                'c.MbrFirstName',
                'c.MbrFamilyName',
                'c.MbrEmailAddress',
                'c.MbrMembershipStatus',
                'c.MbrRegistrationDate',
                "GROUP_CONCAT(DISTINCT p.PhoneNumber ORDER BY p.IsPrimary DESC SEPARATOR ', ') AS PhoneNumbers",
                'f.FamilyName'
            ],
            conditions: ['c.MbrMembershipStatus' => ':status', 'c.Deleted' => 0],
            params: [':status' => 'Active'],
            groupBy: ['c.MbrID'],
            orderBy: ['c.MbrRegistrationDate' => 'DESC'],
            limit: $limit,
            offset: $offset
        );

        $total = $orm->runQuery(
            "SELECT COUNT(*) AS total FROM churchmember WHERE Deleted = 0 AND MbrMembershipStatus = 'Active'"
        )[0]['total'];

        return [
            'data' => $members,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
                'total'  => (int)$total,
                'pages'  => (int)ceil($total / $limit)
            ]
        ];
    }
}