<?php

/**
 * Family Management Class
 * Handles creation, updating, deletion, and retrieval of family records.
 * Validates input data and checks for uniqueness of family names.
 * Manages family members, including adding, removing, and updating roles.
 */
class Family
{
    /**
     * Valid family roles
     */
    private static $validRoles = ['Head', 'Spouse', 'Child', 'Other'];

    /**
     * Create a new family
     * @param array $data The family data containing 'name', 'head_id', and 'branch_id'
     * @return array The created family ID and status
     */
    public static function create($data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'name' => 'required|string',
                'head_id' => 'required|numeric',
                'branch_id' => 'required|numeric'
            ]);

            // Validate head of household
            $head = $orm->getWhere('churchmember', [
                'MbrID' => $data['head_id'],
                'MbrMembershipStatus' => 'Active',
                'Deleted' => 0
            ]);
            if (empty($head)) {
                Helpers::logError('Family create error: Invalid or inactive head of household ID');
                Helpers::sendFeedback('Invalid or inactive head of household ID', 400);
            }

            // Validate branch
            $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
            if (empty($branch)) {
                Helpers::logError('Family create error: Invalid branch ID');
                Helpers::sendFeedback('Invalid branch ID', 400);
            }

            // Validate head's branch
            if ($head[0]['BranchID'] != $data['branch_id']) {
                Helpers::logError('Family create error: Head of household must belong to the specified branch');
                Helpers::sendFeedback('Head of household must belong to the specified branch', 400);
            }

            // Check for duplicate family name
            $existing = $orm->getWhere('family', ['FamilyName' => $data['name']]);
            if (!empty($existing)) {
                Helpers::logError('Family create error: Family name already exists');
                Helpers::sendFeedback('Family name already exists', 400);
            }

            // Check if head is already in a family
            if (!empty($head[0]['FamilyID'])) {
                Helpers::logError('Family create error: Head of household is already assigned to a family');
                Helpers::sendFeedback('Head of household is already assigned to a family', 400);
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Create family
            $familyId = $orm->insert('family', [
                'FamilyName' => $data['name'],
                'HeadOfHouseholdID' => $data['head_id'],
                'BranchID' => $data['branch_id']
            ])['id'];

            // Add head to family_member
            $orm->insert('family_member', [
                'FamilyID' => $familyId,
                'MbrID' => $data['head_id'],
                'FamilyRole' => 'Head'
            ]);

            // Update churchmember.FamilyID
            $orm->update('churchmember', [
                'FamilyID' => $familyId
            ], ['MbrID' => $data['head_id']]);

            // Create notification
            $orm->insert('communication', [
                'Title' => 'New Family Created',
                'Message' => "Family '{$data['name']}' has been created.",
                'SentBy' => $data['created_by'] ?? $data['head_id'],
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family create error: ' . $e->getMessage());
            Helpers::sendFeedback('Family creation failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Update an existing family
     * @param int $familyId The ID of the family to update
     * @param array $data The new family data containing 'name', 'head_id', and 'branch_id'
     * @return array The updated family ID and status
     */
    public static function update($familyId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'name' => 'string|nullable',
                'head_id' => 'numeric|nullable',
                'branch_id' => 'numeric|nullable'
            ]);

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                Helpers::logError('Family update error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Prepare update data
            $updateData = [];
            $headId = $data['head_id'] ?? $family[0]['HeadOfHouseholdID'];
            $branchId = $data['branch_id'] ?? $family[0]['BranchID'];

            // Validate head of household if provided
            if (isset($data['head_id'])) {
                $head = $orm->getWhere('churchmember', [
                    'MbrID' => $data['head_id'],
                    'MbrMembershipStatus' => 'Active',
                    'Deleted' => 0
                ]);
                if (empty($head)) {
                    Helpers::logError('Family update error: Invalid or inactive head of household ID');
                    Helpers::sendFeedback('Invalid or inactive head of household ID', 400);
                }

                // Validate head's branch
                if ($head[0]['BranchID'] != $branchId) {
                    Helpers::logError('Family update error: Head of household must belong to the specified branch');
                    Helpers::sendFeedback('Head of household must belong to the specified branch', 400);
                }

                // Check if new head is in another family
                if ($data['head_id'] != $family[0]['HeadOfHouseholdID'] && !empty($head[0]['FamilyID']) && $head[0]['FamilyID'] != $familyId) {
                    Helpers::logError('Family update error: New head of household is already assigned to another family');
                    Helpers::sendFeedback('New head of household is already assigned to another family', 400);
                }
            }

            // Validate branch if provided
            if (isset($data['branch_id'])) {
                $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
                if (empty($branch)) {
                    Helpers::logError('Family update error: Invalid branch ID');
                    Helpers::sendFeedback('Invalid branch ID', 400);
                }
            }

            // Check for duplicate family name if provided
            if (isset($data['name'])) {
                $existing = $orm->getWhere('family', ['FamilyName' => $data['name'], 'FamilyID != ' => $familyId]);
                if (!empty($existing)) {
                    Helpers::logError('Family update error: Family name already exists');
                    Helpers::sendFeedback('Family name already exists', 400);
                }
                $updateData['FamilyName'] = $data['name'];
            }

            if (isset($data['head_id'])) $updateData['HeadOfHouseholdID'] = $data['head_id'];
            if (isset($data['branch_id'])) $updateData['BranchID'] = $data['branch_id'];

            if (empty($updateData)) {
                return ['status' => 'success', 'family_id' => $familyId];
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Update family
            $orm->update('family', $updateData, ['FamilyID' => $familyId]);

            // Update family_member roles if head changed
            if (isset($data['head_id']) && $data['head_id'] != $family[0]['HeadOfHouseholdID']) {
                // Remove old head's role or set to 'Other'
                $orm->update('family_member', [
                    'FamilyRole' => 'Other'
                ], ['FamilyID' => $familyId, 'MbrID' => $family[0]['HeadOfHouseholdID']]);

                // Add or update new head
                $existingMember = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $data['head_id']]);
                if (empty($existingMember)) {
                    $orm->insert('family_member', [
                        'FamilyID' => $familyId,
                        'MbrID' => $data['head_id'],
                        'FamilyRole' => 'Head'
                    ]);
                } else {
                    $orm->update('family_member', [
                        'FamilyRole' => 'Head'
                    ], ['FamilyID' => $familyId, 'MbrID' => $data['head_id']]);
                }

                // Update churchmember.FamilyID for new head
                $orm->update('churchmember', [
                    'FamilyID' => $familyId
                ], ['MbrID' => $data['head_id']]);
            }

            // Create notification
            $orm->insert('communication', [
                'Title' => 'Family Updated',
                'Message' => "Family '{$family[0]['FamilyName']}' has been updated.",
                'SentBy' => $data['created_by'] ?? $headId,
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family update error: ' . $e->getMessage());
            Helpers::sendFeedback('Family update failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Delete a family
     * @param int $familyId The ID of the family to delete
     * @return array The status of the deletion
     */
    public static function delete($familyId)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                Helpers::logError('Family delete error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Check for members
            $members = $orm->getWhere('family_member', ['FamilyID' => $familyId]);
            if (count($members) > 1) { // Allow deletion if only head exists
                Helpers::logError('Family delete error: Cannot delete family with members');
                Helpers::sendFeedback('Cannot delete family with members', 400);
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Remove family_member entries
            $orm->delete('family_member', ['FamilyID' => $familyId]);

            // Null churchmember.FamilyID
            $orm->update('churchmember', [
                'FamilyID' => null
            ], ['FamilyID' => $familyId]);

            // Delete family
            $orm->delete('family', ['FamilyID' => $familyId]);

            $orm->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family delete error: ' . $e->getMessage());
            Helpers::sendFeedback('Family delete failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get a family by ID
     * @param int $familyId The ID of the family to retrieve
     * @return array The family data including members
     */
    public static function get($familyId)
    {
        $orm = new ORM();
        try {
            $family = $orm->selectWithJoin(
                baseTable: 'family f',
                joins: [
                    ['table' => 'churchmember h', 'on' => 'f.HeadOfHouseholdID = h.MbrID', 'type' => 'LEFT'],
                    ['table' => 'branch b', 'on' => 'f.BranchID = b.BranchID', 'type' => 'LEFT']
                ],
                fields: [
                    'f.*',
                    'h.MbrFirstName as HeadFirstName',
                    'h.MbrFamilyName as HeadFamilyName',
                    'b.BranchName'
                ],
                conditions: ['f.FamilyID' => ':id'],
                params: [':id' => $familyId]
            )[0] ?? null;

            if (!$family) {
                Helpers::logError('Family get error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Get members
            $members = $orm->selectWithJoin(
                baseTable: 'family_member fm',
                joins: [
                    ['table' => 'churchmember m', 'on' => 'fm.MbrID = m.MbrID', 'type' => 'LEFT']
                ],
                fields: [
                    'fm.FamilyMemberID',
                    'fm.MbrID',
                    'fm.FamilyRole',
                    'm.MbrFirstName',
                    'm.MbrFamilyName',
                    'm.MbrEmailAddress'
                ],
                conditions: ['fm.FamilyID' => ':id'],
                params: [':id' => $familyId]
            );

            $family['Members'] = $members;
            return $family;
        } catch (Exception $e) {
            Helpers::logError('Family get error: ' . $e->getMessage());
            Helpers::sendFeedback('Family retrieval failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get all families with pagination and optional filters
     * @param int $page The page number for pagination
     * @param int $limit The number of records per page
     * @param array $filters Optional filters for branch_id and name
     * @return array List of families with pagination info
     */
    public static function getAll($page = 1, $limit = 10, $filters = [])
    {
        $orm = new ORM();
        try {
            $offset = ($page - 1) * $limit;
            $conditions = [];
            $params = [];

            if (!empty($filters['branch_id'])) {
                $conditions['f.BranchID ='] = ':branch_id';
                $params[':branch_id'] = $filters['branch_id'];
            }
            if (!empty($filters['name']) && is_string($filters['name']) && trim($filters['name']) !== '') {
                $conditions['f.FamilyName LIKE'] = ':name';
                $params[':name'] = '%' . trim($filters['name']) . '%';
            }

            $families = $orm->selectWithJoin(
                baseTable: 'family f',
                joins: [
                    ['table' => 'churchmember h', 'on' => 'f.HeadOfHouseholdID = h.MbrID', 'type' => 'LEFT'],
                    ['table' => 'branch b', 'on' => 'f.BranchID = b.BranchID', 'type' => 'LEFT']
                ],
                fields: [
                    'f.*',
                    'h.MbrFirstName as HeadFirstName',
                    'h.MbrFamilyName as HeadFamilyName',
                    'b.BranchName',
                    '(SELECT COUNT(*) FROM family_member fm WHERE fm.FamilyID = f.FamilyID) as MemberCount'
                ],
                conditions: $conditions,
                params: $params,
                limit: $limit,
                offset: $offset
            );

            $whereClause = '';
            if (!empty($conditions)) {
                $whereConditions = [];
                foreach ($conditions as $column => $placeholder) {
                    $whereConditions[] = "$column $placeholder";
                }
                $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
            }

            $total = $orm->runQuery(
                "SELECT COUNT(*) as total FROM family f" . $whereClause,
                $params
            )[0]['total'];

            return [
                'data' => $families,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (Exception $e) {
            Helpers::logError('Family getAll error: ' . $e->getMessage());
            Helpers::sendFeedback('Family retrieval failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Add a member to a family
     * @param int $familyId The ID of the family to add the member to
     * @param array $data The member data containing 'member_id' and 'role'
     * @return array The status of the addition and family ID
     */
    public static function addMember($familyId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'member_id' => 'required|numeric',
                'role' => 'required|string'
            ]);

            // Validate role
            if (!in_array($data['role'], self::$validRoles)) {
                Helpers::logError('Family addMember error: Invalid family role');
                Helpers::sendFeedback('Invalid family role', 400);
            }

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                Helpers::logError('Family addMember error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Validate member
            $member = $orm->getWhere('churchmember', [
                'MbrID' => $data['member_id'],
                'MbrMembershipStatus' => 'Active',
                'Deleted' => 0
            ]);
            if (empty($member)) {
                Helpers::logError('Family addMember error: Invalid or inactive member ID');
                Helpers::sendFeedback('Invalid or inactive member ID', 400);
            }

            // Check if member is already in a family
            if (!empty($member[0]['FamilyID']) && $member[0]['FamilyID'] != $familyId) {
                Helpers::logError('Family addMember error: Member is already assigned to another family');
                Helpers::sendFeedback('Member is already assigned to another family', 400);
            }

            // Check if member is already in family_member
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $data['member_id']]);
            if (!empty($existing)) {
                Helpers::logError('Family addMember error: Member is already in the family');
                Helpers::sendFeedback('Member is already in the family', 400);
            }

            // Prevent setting another 'Head'
            if ($data['role'] === 'Head') {
                Helpers::logError('Family addMember error: Family already has a head of household');
                Helpers::sendFeedback('Family already has a head of household', 400);
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Add to family_member
            $orm->insert('family_member', [
                'FamilyID' => $familyId,
                'MbrID' => $data['member_id'],
                'FamilyRole' => $data['role']
            ]);

            // Update churchmember.FamilyID
            $orm->update('churchmember', [
                'FamilyID' => $familyId
            ], ['MbrID' => $data['member_id']]);

            // Create notification
            $orm->insert('communication', [
                'Title' => 'Added to Family',
                'Message' => "You have been added to family '{$family[0]['FamilyName']}' as {$data['role']}.",
                'SentBy' => $data['created_by'] ?? $family[0]['HeadOfHouseholdID'],
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $data['member_id']];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family addMember error: ' . $e->getMessage());
            Helpers::sendFeedback('Family add member failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Remove a member from a family
     * @param int $familyId The ID of the family to remove the member from
     * @param int $memberId The ID of the member to remove
     * @return array The status of the removal and family ID
     */
    public static function removeMember($familyId, $memberId)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                Helpers::logError('Family removeMember error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Validate member exists
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                Helpers::logError('Family removeMember error: Invalid member ID');
                Helpers::sendFeedback('Invalid member ID', 400);
            }

            // Check if member is in family
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);
            if (empty($existing)) {
                Helpers::logError('Family removeMember error: Member is not in the family');
                Helpers::sendFeedback('Member is not in the family', 400);
            }

            // Prevent removing head
            if ($memberId == $family[0]['HeadOfHouseholdID']) {
                Helpers::logError('Family removeMember error: Cannot remove head of household');
                Helpers::sendFeedback('Cannot remove head of household', 400);
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Remove from family_member
            $orm->delete('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);

            // Null churchmember.FamilyID
            $orm->update('churchmember', [
                'FamilyID' => null
            ], ['MbrID' => $memberId]);

            // Create notification
            $orm->insert('communication', [
                'Title' => 'Removed from Family',
                'Message' => "You have been removed from family '{$family[0]['FamilyName']}'.",
                'SentBy' => $family[0]['HeadOfHouseholdID'],
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $memberId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family removeMember error: ' . $e->getMessage());
            Helpers::sendFeedback('Family remove member failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Update a member's role in a family
     * @param int $familyId The ID of the family
     * @param int $memberId The ID of the member
     * @param array $data The data containing 'role' and optionally 'created_by'
     * @return array The status of the update and family/member IDs
     */
    public static function updateMemberRole($familyId, $memberId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'role' => 'required|string'
            ]);

            // Validate role
            if (!in_array($data['role'], self::$validRoles)) {
                Helpers::logError('Family updateMemberRole error: Invalid family role');
                Helpers::sendFeedback('Invalid family role', 400);
            }

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                Helpers::logError('Family updateMemberRole error: Family not found');
                Helpers::sendFeedback('Family not found', 404);
            }

            // Validate member exists
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                Helpers::logError('Family updateMemberRole error: Invalid member ID');
                Helpers::sendFeedback('Invalid member ID', 400);
            }

            // Check if member is in family
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);
            if (empty($existing)) {
                Helpers::logError('Family updateMemberRole error: Member is not in the family');
                Helpers::sendFeedback('Member is not in the family', 400);
            }

            // Prevent setting another 'Head'
            if ($data['role'] === 'Head' && $memberId != $family[0]['HeadOfHouseholdID']) {
                Helpers::logError('Family updateMemberRole error: Family already has a head of household');
                Helpers::sendFeedback('Family already has a head of household', 400);
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Update role
            $orm->update('family_member', [
                'FamilyRole' => $data['role']
            ], ['FamilyID' => $familyId, 'MbrID' => $memberId]);

            // Create notification
            $orm->insert('communication', [
                'Title' => 'Family Role Updated',
                'Message' => "Your role in family '{$family[0]['FamilyName']}' has been updated to {$data['role']}.",
                'SentBy' => $data['created_by'] ?? $family[0]['HeadOfHouseholdID'],
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId, 'member_id' => $memberId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->inTransaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family updateMemberRole error: ' . $e->getMessage());
            Helpers::sendFeedback('Family role update failed: ' . $e->getMessage(), 400);
        }
    }
}