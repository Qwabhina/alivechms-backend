<?php
class Communication
{
   /**
    * Sends a message to a member via email or SMS.
    * @param int $memberId The ID of the member to send the message to.
    * @param string $message The message content.
    * @param string $type The type of message ('email' or 'sms').
    * @return array The status of the message sending operation.
    * @throws Exception If the member does not exist or if sending fails.
    */
   public static function sendMessage($memberId, $message, $type)
   {
      $orm = new ORM();
      $member = $orm->getWhere('churchmember', ['MbrID' => $memberId]);
      if (!$member) {
         throw new Exception('Member not found');
      }

      // Logic to send email or SMS based on type
      if ($type === 'email') {
         // Send email logic here
         // For example, using PHPMailer or similar library
      } elseif ($type === 'sms') {
         // Send SMS logic here
         // For example, using Twilio or similar service
      } else {
         throw new Exception('Invalid message type');
      }

      return ['status' => 'success', 'message' => 'Message sent successfully'];
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
