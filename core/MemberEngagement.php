<?php

/**
 * Member Engagement Class
 * This class handles member engagement activities such as recording attendance, generating engagement reports,
 * adding and updating phone numbers, and sending engagement messages.
 * @package MemberEngagement
 * @version 1.0
 */
class MemberEngagement
{
   /**
    * Records attendance for a member at a specific event.
    * Validates input, checks for existing attendance, and inserts into the database.
    * @param array $data The attendance data to record.
    * @return array The created attendance ID and status.
    * @throws Exception If validation fails, member or event not found, or attendance already recorded.
    */
   public static function recordAttendance($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'event_id' => 'required|numeric',
            'member_id' => 'required|numeric',
            'attendance_date' => 'required|date'
         ]);

         // Validate member
         $member = $orm->getWhere('churchmember', [
            'MbrID' => $data['member_id'],
            'MbrMembershipStatus' => 'Active',
            'Deleted' => 0
         ]);
         if (empty($member)) {
            throw new Exception('Invalid or inactive member');
         }

         // Validate event
         $event = $orm->getWhere('event', ['EventID' => $data['event_id']]);
         if (empty($event)) {
            throw new Exception('Event not found');
         }

         // Check if already attended
         $existing = $orm->getWhere('event_attendance', [
            'EventID' => $data['event_id'],
            'MbrID' => $data['member_id'],
            'AttendanceDate' => $data['attendance_date']
         ]);
         if (!empty($existing)) {
            throw new Exception('Attendance already recorded');
         }

         $attendanceId = $orm->insert('event_attendance', [
            'EventID' => $data['event_id'],
            'MbrID' => $data['member_id'],
            'AttendanceDate' => $data['attendance_date']
         ])['id'];

         // Create notification
         $orm->insert('communication', [
            'Title' => 'Event Attendance Recorded',
            'Message' => "Your attendance at '{$event[0]['EventName']}' on {$data['attendance_date']} has been recorded.",
            'SentBy' => $data['created_by'] ?? 0,
            'TargetMemberID' => $data['member_id'],
            'DeliveryStatus' => 'Pending'
         ]);

         return ['status' => 'success', 'attendance_id' => $attendanceId];
      } catch (Exception $e) {
         Helpers::logError('MemberEngagement recordAttendance error: ' . $e->getMessage());
         throw $e;
      }
   }
   /**
    * Generates an engagement report for a member.
    * Includes group memberships, event attendance, volunteering, and contributions.
    * @param int $memberId The ID of the member to generate the report for.
    * @param array $filters Optional filters for date range and branch.
    * @return array The engagement report data.
    * @throws Exception If member not found or database operations fail.
    */
   public static function getEngagementReport($memberId, $filters = [])
   {
      $orm = new ORM();
      try {
         // Validate member
         $member = $orm->getWhere('churchmember', ['MbrID' => $memberId, 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member');
         }

         $conditions = [];
         $params = [];
         if (!empty($filters['start_date'])) {
            $conditions[] = 'DATE >= :start_date';
            $params[':start_date'] = $filters['start_date'];
         }
         if (!empty($filters['end_date'])) {
            $conditions[] = 'DATE <= :end_date';
            $params[':end_date'] = $filters['end_date'];
         }
         if (!empty($filters['branch_id'])) {
            $conditions[] = 'BranchID = :branch_id';
            $params[':branch_id'] = $filters['branch_id'];
         }

         // Group memberships
         $groups = $orm->getWhere(
            'group_member gm',
            array_merge(['gm.MbrID' => ':mbr_id'], $conditions),
            array_merge([':mbr_id' => $memberId], $params)
         );

         // Event attendance
         $attendance = $orm->getWhere(
            'event_attendance ea',
            array_merge(['ea.MbrID' => ':mbr_id'], $conditions),
            array_merge([':mbr_id' => $memberId], $params)
         );

         // Volunteering
         $volunteering = $orm->getWhere(
            'event_volunteer ev',
            array_merge(['ev.MbrID' => ':mbr_id'], $conditions),
            array_merge([':mbr_id' => $memberId], $params)
         );

         // Contributions
         $contributions = $orm->getWhere(
            'contribution c',
            array_merge(['c.MbrID' => ':mbr_id'], $conditions),
            array_merge([':mbr_id' => $memberId], $params)
         );

         $totalContributions = array_sum(array_column($contributions, 'ContributionAmount'));

         return [
            'member_id' => $memberId,
            'groups_count' => count($groups),
            'events_attended' => count($attendance),
            'volunteer_instances' => count($volunteering),
            'contributions_count' => count($contributions),
            'contributions_total' => $totalContributions,
            'details' => [
               'groups' => $groups,
               'attendance' => $attendance,
               'volunteering' => $volunteering,
               'contributions' => $contributions
            ]
         ];
      } catch (Exception $e) {
         Helpers::logError('MemberEngagement getEngagementReport error: ' . $e->getMessage());
         throw $e;
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
   /**
    * Sends an engagement message to a member based on specific triggers.
    * Validates input, checks member existence, and sends the message if trigger conditions are met.
    * @param array $data The engagement message data including member ID, title, message, and trigger.
    * @return array The status of the message sending and communication ID.
    * @throws Exception If validation fails, member not found, or trigger conditions not met.
    */
   public static function sendEngagementMessage($data)
   {
      $orm = new ORM();
      try {
         // Validate input
         Helpers::validateInput($data, [
            'member_id' => 'required|numeric',
            'title' => 'required',
            'message' => 'required',
            'trigger' => 'required|in:low_attendance,no_contributions,no_groups'
         ]);

         // Validate member
         $member = $orm->getWhere('churchmember', ['MbrID' => $data['member_id'], 'Deleted' => 0]);
         if (empty($member)) {
            throw new Exception('Invalid member');
         }

         // Verify trigger condition
         $filters = ['start_date' => date('Y-m-d', strtotime('-30 days'))];
         $report = self::getEngagementReport($data['member_id'], $filters)['details'];

         $validTrigger = false;
         switch ($data['trigger']) {
            case 'low_attendance':
               $validTrigger = count($report['attendance']) < 2;
               break;
            case 'no_contributions':
               $validTrigger = count($report['contributions']) == 0;
               break;
            case 'no_groups':
               $validTrigger = count($report['groups']) == 0;
               break;
         }

         if (!$validTrigger) {
            throw new Exception('Engagement trigger condition not met');
         }

         $communicationId = $orm->insert('communication', [
            'Title' => $data['title'],
            'Message' => $data['message'],
            'SentBy' => $data['created_by'] ?? 0,
            'TargetMemberID' => $data['member_id'],
            'DeliveryStatus' => 'Pending'
         ])['id'];

         return ['status' => 'success', 'communication_id' => $communicationId];
      } catch (Exception $e) {
         Helpers::logError('MemberEngagement sendEngagementMessage error: ' . $e->getMessage());
         throw $e;
      }
   }
}
