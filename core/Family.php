<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/ORM.php';
require_once __DIR__ . '/Helpers.php';

class Family
{
    private static $validRoles = ['Head', 'Spouse', 'Child', 'Other'];

    public static function create($data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'name' => 'required',
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
                throw new Exception('Invalid or inactive head of household ID');
            }

            // Validate branch
            $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
            if (empty($branch)) {
                throw new Exception('Invalid branch ID');
            }

            // Validate head's branch
            if ($head[0]['BranchID'] != $data['branch_id']) {
                throw new Exception('Head of household must belong to the specified branch');
            }

            // Check for duplicate family name
            $existing = $orm->getWhere('family', ['FamilyName' => $data['name']]);
            if (!empty($existing)) {
                throw new Exception('Family name already exists');
            }

            // Check if head is already in a family
            if (!empty($head[0]['FamilyID'])) {
                throw new Exception('Head of household is already assigned to a family');
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
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family create error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function update($familyId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'name' => 'required',
                'head_id' => 'required|numeric',
                'branch_id' => 'required|numeric'
            ]);

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                throw new Exception('Family not found');
            }

            // Validate head of household
            $head = $orm->getWhere('churchmember', [
                'MbrID' => $data['head_id'],
                'MbrMembershipStatus' => 'Active',
                'Deleted' => 0
            ]);
            if (empty($head)) {
                throw new Exception('Invalid or inactive head of household ID');
            }

            // Validate branch
            $branch = $orm->getWhere('branch', ['BranchID' => $data['branch_id']]);
            if (empty($branch)) {
                throw new Exception('Invalid branch ID');
            }

            // Validate head's branch
            if ($head[0]['BranchID'] != $data['branch_id']) {
                throw new Exception('Head of household must belong to the specified branch');
            }

            // Check for duplicate family name (excluding current)
            $existing = $orm->getWhere('family', ['FamilyName' => $data['name'], 'FamilyID != ' => $familyId]);
            if (!empty($existing)) {
                throw new Exception('Family name already exists');
            }

            // Check if new head is in another family
            if ($data['head_id'] != $family[0]['HeadOfHouseholdID'] && !empty($head[0]['FamilyID']) && $head[0]['FamilyID'] != $familyId) {
                throw new Exception('New head of household is already assigned to another family');
            }

            $orm->beginTransaction();
            $transactionStarted = true;

            // Update family
            $orm->update('family', [
                'FamilyName' => $data['name'],
                'HeadOfHouseholdID' => $data['head_id'],
                'BranchID' => $data['branch_id']
            ], ['FamilyID' => $familyId]);

            // Update family_member roles if head changed
            if ($data['head_id'] != $family[0]['HeadOfHouseholdID']) {
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
                'Message' => "Family '{$data['name']}' has been updated.",
                'SentBy' => $data['created_by'] ?? $data['head_id'],
                'TargetGroupID' => null
            ]);

            $orm->commit();
            return ['status' => 'success', 'family_id' => $familyId];
        } catch (Exception $e) {
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family update error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function delete($familyId)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                throw new Exception('Family not found');
            }

            // Check for members
            $members = $orm->getWhere('family_member', ['FamilyID' => $familyId]);
            if (count($members) > 1) { // Allow deletion if only head exists
                throw new Exception('Cannot delete family with members');
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
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family delete error: ' . $e->getMessage());
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
                throw new Exception('Family not found');
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
            throw $e;
        }
    }

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

            $total = $orm->runQuery(
                "SELECT COUNT(*) as total FROM family f" .
                    (!empty($conditions) ? ' WHERE ' . implode(' AND ', array_keys($conditions)) : ''),
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
            throw $e;
        }
    }

    public static function addMember($familyId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, [
                'member_id' => 'required|numeric',
                'role' => 'required'
            ]);

            // Validate role
            if (!in_array($data['role'], self::$validRoles)) {
                throw new Exception('Invalid family role');
            }

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                throw new Exception('Family not found');
            }

            // Validate member
            $member = $orm->getWhere('churchmember', [
                'MbrID' => $data['member_id'],
                'MbrMembershipStatus' => 'Active',
                'Deleted' => 0
            ]);
            if (empty($member)) {
                throw new Exception('Invalid or inactive member ID');
            }

            // Check if member is already in a family
            if (!empty($member[0]['FamilyID']) && $member[0]['FamilyID'] != $familyId) {
                throw new Exception('Member is already assigned to another family');
            }

            // Check if member is already in family_member
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $data['member_id']]);
            if (!empty($existing)) {
                throw new Exception('Member is already in the family');
            }

            // Prevent setting another 'Head'
            if ($data['role'] === 'Head') {
                throw new Exception('Family already has a head of household');
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
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family addMember error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function removeMember($familyId, $memberId)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                throw new Exception('Family not found');
            }

            // Validate member exists
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                throw new Exception('Invalid member ID');
            }

            // Check if member is in family
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);
            if (empty($existing)) {
                throw new Exception('Member is not in the family');
            }

            // Prevent removing head
            if ($memberId == $family[0]['HeadOfHouseholdID']) {
                throw new Exception('Cannot remove head of household');
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
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family removeMember error: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function updateMemberRole($familyId, $memberId, $data)
    {
        $orm = new ORM();
        $transactionStarted = false;
        try {
            // Validate input
            Helpers::validateInput($data, ['role' => 'required']);

            // Validate role
            if (!in_array($data['role'], self::$validRoles)) {
                throw new Exception('Invalid family role');
            }

            // Validate family exists
            $family = $orm->getWhere('family', ['FamilyID' => $familyId]);
            if (empty($family)) {
                throw new Exception('Family not found');
            }

            // Validate member exists
            $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
            if (empty($member)) {
                throw new Exception('Invalid member ID');
            }

            // Check if member is in family
            $existing = $orm->getWhere('family_member', ['FamilyID' => $familyId, 'MbrID' => $memberId]);
            if (empty($existing)) {
                throw new Exception('Member is not in the family');
            }

            // Prevent setting another 'Head'
            if ($data['role'] === 'Head' && $memberId != $family[0]['HeadOfHouseholdID']) {
                throw new Exception('Family already has a head of household');
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
            if ($transactionStarted && $orm->in_transaction()) {
                $orm->rollBack();
            }
            Helpers::logError('Family updateMemberRole error: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>