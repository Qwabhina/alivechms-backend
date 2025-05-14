<?php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$token = Auth::getBearerToken();
if (!$token || !Auth::verify($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

switch ($path) {
    case 'member/recent':
        $orm = new ORM();
        $members = $orm->selectWithJoin(
            baseTable: 'churchmember s',
            joins: [
                ['table' => 'userauthentication u', 'on' => 'u.MbrID = s.MbrCustomID']
            ],
            conditions: ['s.MbrMembershipStatus' => ':status'],
            params: [':status' => 'Active'],
            orderBy: ['s.MbrRegistrationDate' => 'DESC'],
            limit: 10
        );

        $formattedMembers = array_map(function ($member) {
            return [
                'MbrCustomID' => $member['MbrCustomID'],
                'MbrFirstName' => $member['MbrFirstName'],
                'MbrFamilyName' => $member['MbrFamilyName'],
                'MbrRegistrationDate' => $member['MbrRegistrationDate'],
                'MbrMembershipStatus' => $member['MbrMembershipStatus'],
                'MbrOtherNames' => $member['MbrOtherNames'] ?? '',
                'MbrGender' => $member['MbrGender'] ?? 'Male',
                'MbrPhoneNumbers' => $member['MbrPhoneNumbers'] ?? '',
                'MbrEmailAddress' => $member['MbrEmailAddress'] ?? '',
                'MbrResidentialAddress' => $member['MbrResidentialAddress'] ?? '',
                'MbrDateOfBirth' => $member['MbrDateOfBirth'] ?? '0000-00-00',
                'MbrOccupation' => $member['MbrOccupation'] ?? 'Not Applicable',
                'BranchID' => $member['BranchID'] ?? 1,
                'Username' => $member['Username'] ?? '',
                'LastLoginAt' => $member['LastLoginAt'] ?? '0000-00-00 00:00:00'
            ];
        }, $members);

        echo json_encode($formattedMembers);
        break;

    case 'member/all':

        $orm = new ORM();
        $members = $orm->getWhere('churchmember', ['MbrMembershipStatus' => 'Active']);

        $formattedMembers = array_map(function ($member) {
            return [
                'MbrCustomID' => $member['MbrCustomID'],
                'MbrFirstName' => $member['MbrFirstName'],
                'MbrFamilyName' => $member['MbrFamilyName'],
                'MbrRegistrationDate' => $member['MbrRegistrationDate'],
                'MbrMembershipStatus' => $member['MbrMembershipStatus'],
                'MbrOtherNames' => $member['MbrOtherNames'] ?? '',
                'MbrGender' => $member['MbrGender'] ?? 'Male',
                'MbrPhoneNumbers' => $member['MbrPhoneNumbers'] ?? '',
                'MbrEmailAddress' => $member['MbrEmailAddress'] ?? '',
                'MbrResidentialAddress' => $member['MbrResidentialAddress'] ?? '',
                'MbrDateOfBirth' => $member['MbrDateOfBirth'] ?? '0000-00-00',
                'MbrOccupation' => $member['MbrOccupation'] ?? 'Not Applicable',
                'BranchID' => $member['BranchID'] ?? 1,
                'Username' => '',
                'LastLoginAt' => ''
            ];
        }, $members);

        echo json_encode($formattedMembers);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        exit;
}