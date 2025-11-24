<?php

/**
 * Family Management
 *
 * Handles creation, updates, deletion, membership management,
 * and retrieval of family units with full audit trail and validation.
 *
 * @package  AliveChMS\Core
 * @version  1.0.0
 * @author   Benjamin Ebo Yankson
 * @since    2025-November
 */

declare(strict_types=1);

class Family
{
    private const VALID_ROLES = ['Head', 'Spouse', 'Child', 'Other'];

    /**
     * Create a new family unit
     *
     * @param array $data Family creation payload
     * @return array ['status' => 'success', 'family_id' => int]
     * @throws Exception On validation or database failure
     */
    public static function create(array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'name'      => 'required|max:100',
            'head_id'   => 'required|numeric',
            'branch_id' => 'required|numeric',
        ]);

        $headId   = (int)$data['head_id'];
        $branchId = (int)$data['branch_id'];

        // Validate head exists and is active
        $head = $orm->getWhere('churchmember', [
            'MbrID'              => $headId,
            'MbrMembershipStatus' => 'Active',
            'Deleted'            => 0
        ]);

        if (empty($head)) {
            Helpers::sendFeedback('Invalid or inactive head of household', 400);
        }

        // Validate branch
        if (empty($orm->getWhere('branch', ['BranchID' => $branchId]))) {
            Helpers::sendFeedback('Invalid branch ID', 400);
        }

        // Validate head belongs to specified branch
        if ((int)$head[0]['BranchID'] !== $branchId) {
            Helpers::sendFeedback('Head of household must belong to the selected branch', 400);
        }

        // Check duplicate family name
        if (!empty($orm->getWhere('family', ['FamilyName' => $data['name']]))) {
            Helpers::sendFeedback('Family name already exists', 400);
        }

        // Prevent head already in another family
        if (!empty($head[0]['FamilyID'])) {
            Helpers::sendFeedback('Head of household is already assigned to a family', 400);
        }

        $orm->beginTransaction();
        try {
            $familyId = $orm->insert('family', [
                'FamilyName'        => $data['name'],
                'HeadOfHouseholdID' => $headId,
                'BranchID'          => $branchId,
                'CreatedAt'         => date('Y-m-d H:i:s')
            ])['id'];

            $orm->insert('family_member', [
                'FamilyID'   => $familyId,
                'MbrID'      => $headId,
                'FamilyRole' => 'Head',
                'JoinedAt'   => date('Y-m-d H:i:s')
            ]);

            $orm->update('churchmember', ['FamilyID' => $familyId], ['MbrID' => $headId]);

            $orm->commit();
            Helpers::logError("New family created: FamilyID $familyId – {$data['name']}");
            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError("Family creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update family details
     *
     * @param int   $familyId Family ID
     * @param array $data     Updated data
     * @return array ['status' => 'success', 'family_id' => int]
     * @throws Exception On validation or database failure
     */
    public static function update(int $familyId, array $data): array
    {
        $orm = new ORM();

        $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
        if (empty($family)) {
            Helpers::sendFeedback('Family not found', 404);
        }

        $updateData = [];

        if (!empty($data['name'])) {
            Helpers::validateInput($data, ['name' => 'required|max:100']);
            if (!empty($orm->getWhere('family', ['FamilyName' => $data['name'], 'FamilyID!=' => $familyId]))) {
                Helpers::sendFeedback('Family name already exists', 400);
            }
            $updateData['FamilyName'] = $data['name'];
        }

        if (!empty($data['branch_id'])) {
            if (empty($orm->getWhere('branch', ['BranchID' => (int)$data['branch_id']]))) {
                Helpers::sendFeedback('Invalid branch ID', 400);
            }
            $updateData['BranchID'] = (int)$data['branch_id'];
        }

        if (empty($updateData)) {
            return ['status' => 'success', 'family_id' => $familyId];
        }

        $orm->update('family', $updateData, ['FamilyID' => $familyId]);
        return ['status' => 'success', 'family_id' => $familyId];
    }

    /**
     * Delete family (only if no non-head members)
     *
     * @param int $familyId Family ID
     * @return array ['status' => 'success']
     * @throws Exception On failure
     */
    public static function delete(int $familyId): array
    {
        $orm = new ORM();

        $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
        if (empty($family)) {
            Helpers::sendFeedback('Family not found', 404);
        }

        $members = $orm->getWhere('family_member', ['FamilyID' => $familyId]);
        if (count($members) > 1) {
            Helpers::sendFeedback('Cannot delete family with multiple members', 400);
        }

        $orm->beginTransaction();
        try {
            $orm->delete('family_member', ['FamilyID' => $familyId]);
            $orm->update('churchmember', ['FamilyID' => null], ['FamilyID' => $familyId]);
            $orm->delete('family', ['FamilyID' => $familyId]);
            $orm->commit();

            Helpers::logError("Family deleted: FamilyID $familyId");
            return ['status' => 'success'];
        } catch (Exception $e) {
            $orm->rollBack();
            Helpers::logError("Family deletion failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve family with all members
     *
     * @param int $familyId Family ID
     * @return array Full family data
     */
    public static function get(int $familyId): array
    {
        $orm = new ORM();

        $rows = $orm->selectWithJoin(
            baseTable: 'family f',
            joins: [
                ['table' => 'churchmember h', 'on' => 'f.HeadOfHouseholdID = h.MbrID'],
                ['table' => 'branch b',       'on' => 'f.BranchID = b.BranchID'],
                ['table' => 'family_member fm', 'on' => 'f.FamilyID = fm.FamilyID'],
                ['table' => 'churchmember m',   'on' => 'fm.MbrID = m.MbrID']
            ],
            fields: [
                'f.FamilyID',
                'f.FamilyName',
                'f.HeadOfHouseholdID',
                'h.MbrFirstName AS HeadFirstName',
                'h.MbrFamilyName AS HeadFamilyName',
                'b.BranchName',
                'fm.FamilyRole',
                'fm.JoinedAt',
                'm.MbrID',
                'm.MbrFirstName',
                'm.MbrFamilyName',
                'm.MbrGender',
                'm.MbrDateOfBirth'
            ],
            conditions: ['f.FamilyID' => ':id'],
            params: [':id' => $familyId],
            groupBy: ['f.FamilyID', 'm.MbrID']
        );

        if (empty($rows)) {
            Helpers::sendFeedback('Family not found', 404);
        }

        $family  = $rows[0];
        $members = [];

        foreach ($rows as $row) {
            $members[] = [
                'MbrID'       => (int)$row['MbrID'],
                'FirstName'   => $row['MbrFirstName'],
                'FamilyName'  => $row['MbrFamilyName'],
                'Gender'      => $row['MbrGender'],
                'DateOfBirth' => $row['MbrDateOfBirth'],
                'Role'        => $row['FamilyRole'],
                'JoinedAt'    => $row['JoinedAt']
            ];
        }

        unset(
            $family['MbrID'],
            $family['MbrFirstName'],
            $family['MbrFamilyName'],
            $family['MbrGender'],
            $family['MbrDateOfBirth'],
            $family['FamilyRole'],
            $family['JoinedAt']
        );

        $family['Members'] = $members;

        return $family;
    }

    /**
     * Retrieve paginated list of families
     *
     * @param int   $page    Page number (1-based)
     * @param int   $limit   Items per page
     * @param array $filters Optional filters
     * @return array Paginated result
     */
    public static function getAll(int $page = 1, int $limit = 10, array $filters = []): array
    {
        $orm    = new ORM();
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params     = [];

        if (!empty($filters['branch_id'])) {
            $conditions['f.BranchID'] = ':branch_id';
            $params[':branch_id'] = (int)$filters['branch_id'];
        }
        if (!empty($filters['name'])) {
            $conditions['f.FamilyName LIKE'] = ':name';
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        $families = $orm->selectWithJoin(
            baseTable: 'family f',
            joins: [
                ['table' => 'churchmember h', 'on' => 'f.HeadOfHouseholdID = h.MbrID'],
                ['table' => 'branch b',       'on' => 'f.BranchID = b.BranchID']
            ],
            fields: [
                'f.FamilyID',
                'f.FamilyName',
                'f.HeadOfHouseholdID',
                'h.MbrFirstName AS HeadFirstName',
                'h.MbrFamilyName AS HeadFamilyName',
                'b.BranchName',
                '(SELECT COUNT(*) FROM family_member fm WHERE fm.FamilyID = f.FamilyID) AS MemberCount'
            ],
            conditions: $conditions,
            params: $params,
            orderBy: ['f.FamilyName' => 'ASC'],
            limit: $limit,
            offset: $offset
        );

        $total = $orm->runQuery(
            "SELECT COUNT(*) AS total FROM family f" . (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
            $params
        )[0]['total'];

        return [
            'data' => $families,
            'pagination' => [
                'page'   => $page,
                'limit'  => $limit,
                'total'  => (int)$total,
                'pages'  => (int)ceil($total / $limit)
            ]
        ];
    }

    /**
     * Add member to family
     *
     * @param int   $familyId Family ID
     * @param array $data     Member data
     * @return array Success response
     * @throws Exception On failure
     */
    public static function addMember(int $familyId, array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'member_id' => 'required|numeric',
            'role'      => 'required|in:' . implode(',', self::VALID_ROLES)
        ]);

        $memberId = (int)$data['member_id'];
        $role     = $data['role'];

        if (empty($orm->getWhere('family', ['FamilyID' => $familyId]))) {
            Helpers::sendFeedback('Family not found', 404);
        }

        $member = $orm->getWhere('churchmember', [
            'MbrID'              => $memberId,
            'MbrMembershipStatus' => 'Active',
            'Deleted'            => 0
        ]);
        if (empty($member)) {
            Helpers::sendFeedback('Invalid or inactive member', 400);
        }

        if (!empty($member[0]['FamilyID']) && $member[0]['FamilyID'] != $familyId) {
            Helpers::sendFeedback('Member already belongs to another family', 400);
        }

        if ($role === 'Head') {
            Helpers::sendFeedback('Cannot assign Head role – use family creation/update', 400);
        }

        if (!empty($orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]))) {
            Helpers::sendFeedback('Member already in family', 400);
        }

        $orm->beginTransaction();
        try {
            $orm->insert('family_member', [
                'FamilyID'   => $familyId,
                'MbrID'      => $memberId,
                'FamilyRole' => $role,
                'JoinedAt'   => date('Y-m-d H:i:s')
            ]);

            $orm->update('churchmember', ['FamilyID' => $familyId], ['MbrID' => $memberId]);
            $orm->commit();

            return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $memberId];
        } catch (Exception $e) {
            $orm->rollBack();
            throw $e;
        }
    }

    /**
     * Remove member from family
     *
     * @param int $familyId Family ID
     * @param int $memberId Member ID
     * @return array Success response
     */
    public static function removeMember(int $familyId, int $memberId): array
    {
        $orm = new ORM();

        $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
        if (empty($family)) {
            Helpers::sendFeedback('Family not found', 404);
        }

        if ($memberId === (int)$family[0]['HeadOfHouseholdID']) {
            Helpers::sendFeedback('Cannot remove head of household', 400);
        }

        if (empty($orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]))) {
            Helpers::sendFeedback('Member not in family', 400);
        }

        $orm->beginTransaction();
        try {
            $orm->delete('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);
            $orm->update('churchmember', ['FamilyID' => null], ['MbrID' => $memberId]);
            $orm->commit();

            return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $memberId];
        } catch (Exception $e) {
            $orm->rollBack();
            throw $e;
        }
    }

    /**
     * Update member role in family
     *
     * @param int   $familyId Family ID
     * @param int   $memberId Member ID
     * @param array $data     Role data
     * @return array Success response
     */
    public static function updateMemberRole(int $familyId, int $memberId, array $data): array
    {
        $orm = new ORM();

        Helpers::validateInput($data, [
            'role' => 'required|in:' . implode(',', self::VALID_ROLES)
        ]);

        $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
        if (empty($family)) {
            Helpers::sendFeedback('Family not found', 404);
        }

        if ($memberId === (int)$family[0]['HeadOfHouseholdID'] && $data['role'] !== 'Head') {
            Helpers::sendFeedback('Head of household role cannot be changed', 400);
        }

        if (empty($orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]))) {
            Helpers::sendFeedback('Member not in family', 400);
        }

        $orm->update('family_member', ['FamilyRole' => $data['role']], [
            'FamilyID' => $familyId,
            'MbrID'    => $memberId
        ]);

        return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $memberId];
    }
}