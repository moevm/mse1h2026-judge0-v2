<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_sesskey();

$token = required_param('token', PARAM_ALPHANUM);
$job = $DB->get_record('qtype_judge0_queue', ['token' => $token], '*', MUST_EXIST);

require_login();

if ((int)$job->userid !== (int)$USER->id) {
    throw new moodle_exception('nopermissions', 'error');
}

$results = [];
if (!empty($job->resultjson)) {
    $decoded = json_decode($job->resultjson, true);
    if (is_array($decoded)) {
        $results = $decoded;
    }
}

@header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => $job->status,
    'token' => $job->token,
    'fraction' => $job->fraction === null ? null : (float)$job->fraction,
    'state' => $job->state,
    'results' => $results,
    'error' => $job->error
]);
