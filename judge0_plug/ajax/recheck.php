<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

require_sesskey();

$attemptid = required_param('attemptid', PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$answer = required_param('answer', PARAM_RAW);
$languageid = required_param('languageid', PARAM_INT);

$attemptobj = \mod_quiz\quiz_attempt::create($attemptid);

require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_exception('notyourattempt', 'quiz', $attemptobj->view_url());
}

if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}

if ($attemptobj->is_finished()) {
    throw new moodle_exception('attemptalreadyclosed', 'quiz', $attemptobj->review_url());
}

$qa = $attemptobj->get_question_attempt($slot);
if ((int)$qa->get_question_id() !== $questionid) {
    throw new moodle_exception('invalidquestionid', 'question');
}

$question = $qa->get_question();
if (!$question instanceof qtype_judge0_question) {
    throw new moodle_exception('invalidquestiontype', 'question');
}

$now = time();
$answerhash = qtype_judge0_question::hash_response($answer, $languageid);

$params = [
    'userid' => (int)$USER->id,
    'attemptid' => $attemptid,
    'slot' => $slot,
    'questionid' => $questionid,
    'languageid' => $languageid,
    'answerhash' => $answerhash,
    'queued' => 'queued',
    'running' => 'running',
    'recent' => $now - 300
];

$existing = $DB->get_records_select(
    'qtype_judge0_queue',
    'userid = :userid AND attemptid = :attemptid AND slot = :slot AND questionid = :questionid ' .
        'AND languageid = :languageid AND answerhash = :answerhash ' .
        'AND (status IN (:queued, :running) OR (status = \'completed\' AND timecompleted > :recent))',
    $params,
    'id DESC',
    '*',
    0,
    1
);

if (!empty($existing)) {
    $job = reset($existing);
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $job->status,
        'token' => $job->token
    ]);
    exit;
}

$job = new stdClass();
$job->token = bin2hex(random_bytes(16));
$job->userid = (int)$USER->id;
$job->attemptid = $attemptid;
$job->usageid = (int)$qa->get_usage_id();
$job->slot = $slot;
$job->questionid = $questionid;
$job->languageid = $languageid;
$job->answerhash = $answerhash;
$job->answer = $answer;
$job->status = 'queued';
$job->fraction = null;
$job->state = null;
$job->resultjson = null;
$job->error = null;
$job->timecreated = $now;
$job->timemodified = $now;
$job->timestarted = null;
$job->timecompleted = null;

$jobid = $DB->insert_record('qtype_judge0_queue', $job);

$task = \qtype_judge0\task\run_recheck::instance((int)$jobid);
\core\task\manager::queue_adhoc_task($task, true);

@header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'queued',
    'token' => $job->token
]);
