<?php

switch ($path) {
    case 'members/register':
        $input = json_decode(file_get_contents("php://input"), true);
        $output = Member::register($input['userid'], $input['passkey'], $input['email']);
        echo json_encode($output);
        break;
}
