<?php
namespace qtype_judge0\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;

class run_recheck extends adhoc_task {
    public static function instance(int $jobid): self {
        $task = new self();
        $task->set_custom_data((object)['jobid' => $jobid]);
        return $task;
    }

    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        $data = $this->get_custom_data();
        $job = $DB->get_record('qtype_judge0_queue', ['id' => (int)$data->jobid]);
        if (!$job) {
            mtrace('Judge0 recheck job not found: ' . (int)$data->jobid);
            return;
        }

        if (!in_array($job->status, ['queued', 'failed'], true)) {
            mtrace('Judge0 recheck job ' . $job->id . ' already ' . $job->status . '.');
            return;
        }

        $now = time();
        $job->status = 'running';
        $job->timemodified = $now;
        $job->timestarted = $now;
        $job->error = null;
        $DB->update_record('qtype_judge0_queue', $job);

        try {
            $question = \question_bank::load_question((int)$job->questionid);
            if (!method_exists($question, 'evaluate_response')) {
                throw new \coding_exception('Question does not support queued Judge0 evaluation.');
            }

            [$fraction, $state] = $question->evaluate_response([
                'answer' => (string)$job->answer,
                'language_id' => (int)$job->languageid
            ], (int)$job->userid);

            $job->status = 'completed';
            $job->fraction = (float)$fraction;
            $job->state = (string)$state;
            $job->resultjson = json_encode($question->last_judge0_response ?: []);
            $job->error = null;
            $job->timemodified = time();
            $job->timecompleted = time();
            $DB->update_record('qtype_judge0_queue', $job);

            $this->update_question_attempt_step($job);

            mtrace('Judge0 recheck job completed: ' . $job->id);
        } catch (\Throwable $e) {
            $job->status = 'failed';
            $job->error = $e->getMessage();
            $job->timemodified = time();
            $job->timecompleted = time();
            $DB->update_record('qtype_judge0_queue', $job);

            mtrace('Judge0 recheck job failed: ' . $job->id . ' ' . $e->getMessage());
        }
    }

    private function update_question_attempt_step($job): void {
        global $DB;

        try {
            $quiz_attempt = $DB->get_record('quiz_attempts', ['id' => (int)$job->attemptid]);
            if (!$quiz_attempt || $quiz_attempt->state === 'finished') {
                mtrace('Judge0: quiz attempt not found or already finished, skipping step update.');
                return;
            }

            $qa = $DB->get_record_sql(
                'SELECT * FROM {question_attempts}
                 WHERE questionusageid = ? AND slot = ?',
                [(int)$quiz_attempt->uniqueid, (int)$job->slot]
            );

            if (!$qa) {
                mtrace('Judge0: question attempt not found for slot ' . $job->slot);
                return;
            }

            $graded_step = $DB->get_record_sql(
                "SELECT * FROM {question_attempt_steps}
                 WHERE questionattemptid = ? AND state LIKE 'graded%'
                 ORDER BY sequencenumber DESC LIMIT 1",
                [$qa->id]
            );

            if (!$graded_step) {
                mtrace('Judge0: no graded step found, skipping step update.');
                return;
            }

            $moodle_state = 'gradedwrong';
            $state_str = (string)$job->state;
            if ($state_str === 'gradedright') {
                $moodle_state = 'gradedright';
            } elseif ($state_str === 'gradedpartial') {
                $moodle_state = 'gradedpartial';
            }

            $transaction = $DB->start_delegated_transaction();

            $graded_step->state = $moodle_state;
            $graded_step->fraction = (float)$job->fraction;
            $DB->update_record('question_attempt_steps', $graded_step);

            $this->upsert_step_data($graded_step->id, 'answer', (string)$job->answer);
            $this->upsert_step_data($graded_step->id, 'language_id', (string)$job->languageid);

            $DB->set_field('question_attempts', 'responsesummary',
                (string)$job->answer, ['id' => $qa->id]);

            $transaction->allow_commit();

            mtrace('Judge0: updated step ' . $graded_step->id .
                ' to state=' . $moodle_state . ', fraction=' . $job->fraction);
        } catch (\Throwable $e) {
            mtrace('Judge0: failed to update QA step: ' . $e->getMessage());
        }
    }


    private function upsert_step_data(int $stepid, string $name, string $value): void {
        global $DB;

        $existing = $DB->get_record('question_attempt_step_data', [
            'attemptstepid' => $stepid,
            'name' => $name
        ]);

        if ($existing) {
            $existing->value = $value;
            $DB->update_record('question_attempt_step_data', $existing);
        } else {
            $DB->insert_record('question_attempt_step_data', (object)[
                'attemptstepid' => $stepid,
                'name' => $name,
                'value' => $value
            ]);
        }
    }
}
