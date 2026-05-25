<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_judge0_question extends question_graded_automatically {
    public $language_id;
    public $allowed_languages = [];
    public $checker_code;
    public $expected_output;
    public $reference_solution = '';
    public $reference_solution_language_id = 71;
    public $compiler_options = '';
    public $input_generator_code = '';
    public $input_generator_language_id = 71;
    public $testcases = [];
    public $last_judge0_response = null;

    public function get_expected_data() { return array('answer' => PARAM_RAW, 'language_id' => PARAM_INT); }


    public function grade_response(array $response) {
        global $USER;
        $student_id = $USER->id ?? rand(1000, 9999);

        $code = $response['answer'] ?? '';
        if (trim($code) === '') {
            return array(0.0, question_state::$gradedwrong);
        }

        $lang_id = $this->resolve_language_id($response);
        if ($lang_id === false) {
            return $this->fail_grading(
                get_string('invalid_language', 'qtype_judge0'),
                '',
                true
            );
        }

        $full_code = $this->checker_code . "\n" . $code;
        $base_url = rtrim(get_config('qtype_judge0', 'server_url') ?: 'http://server:2358', '/');
        
        $sync_url = $base_url . '/submissions?base64_encoded=false&wait=true';
        $batch_url = $base_url . '/submissions/batch?base64_encoded=false';

        $this->last_judge0_response = [];

        $dynamic_input = null;
        if (!empty($this->input_generator_code)) {
            $gen_payload = json_encode([
                'source_code' => $this->input_generator_code,
                'language_id' => (int)$this->input_generator_language_id,
                'stdin' => (string)$student_id
            ]);
            $gen_res = $this->send_judge0_request($sync_url, $gen_payload);
            if ($gen_res && isset($gen_res['status']['id']) && $gen_res['status']['id'] == 3) {
                $dynamic_input = $gen_res['stdout'] ?? '';
            } else {
                return $this->fail_grading(
                    get_string('generator_failed', 'qtype_judge0'),
                    $gen_res,
                    true
                );
            }
        }

        $cases_to_run = [];
        if ($dynamic_input !== null) {
            $cases_to_run[] = [
                'input' => $dynamic_input,
                'expected' => '',
                'is_hidden' => false,
                'weight' => 1.0,
                'is_dynamic' => true
            ];
        }

        if (!empty($this->testcases)) {
            foreach ($this->testcases as $tc) {
                if (trim($tc->test_input) === '' && trim($tc->expected_output) === '') continue;
                $cases_to_run[] = [
                    'input' => $tc->test_input,
                    'expected' => $tc->expected_output,
                    'is_hidden' => $tc->is_hidden,
                    'weight' => $tc->weight,
                    'is_dynamic' => false
                ];
            }
        }

        if (empty($cases_to_run)) {
            $cases_to_run[] = [
                'input' => '',
                'expected' => $this->expected_output ?? '',
                'is_hidden' => false,
                'weight' => 1.0,
                'is_dynamic' => false
            ];
        }


        foreach ($cases_to_run as &$case) {
            $expected = $case['expected'];
            if (trim($expected) === '' && !empty($this->reference_solution)) {
                $ref_lang = !empty($this->reference_solution_language_id) ? $this->reference_solution_language_id : $lang_id;
                $ref_payload_array = [
                    'source_code' => $this->reference_solution,
                    'language_id' => (int)$ref_lang,
                    'stdin' => $case['input']
                ];
                $ref_payload = json_encode($ref_payload_array);
                $ref_res = $this->send_judge0_request($sync_url, $ref_payload);
                if ($ref_res && isset($ref_res['status']['id']) && $ref_res['status']['id'] == 3) {
                    $case['expected'] = $ref_res['stdout'] ?? '';
                } else {
                    return $this->fail_grading(
                        get_string('reference_failed', 'qtype_judge0'),
                        $ref_res,
                        !empty($case['is_hidden']),
                        $case
                    );
                }
            }
        }
        unset($case);

        $submissions = [];
        foreach ($cases_to_run as $case) {
            $sub_payload = [
                'source_code' => $full_code,
                'language_id' => $lang_id,
                'stdin' => $case['input'],
                'expected_output' => $case['expected']
            ];
            if (!empty($this->compiler_options)) {
                $sub_payload['compiler_options'] = $this->compiler_options;
            }
            $submissions[] = $sub_payload;
        }

        $batch_size = 20;
        $all_results = [];
        $chunks = array_chunk($submissions, $batch_size, true);
        
        foreach ($chunks as $chunk_index => $chunk_submissions) {
            $batch_payload = json_encode(['submissions' => array_values($chunk_submissions)]);
            $batch_response = $this->send_judge0_request($batch_url, $batch_payload);
            
            if (!$batch_response || !is_array($batch_response)) {

                foreach ($chunk_submissions as $idx => $sub) {
                    $all_results[$idx] = ['status' => ['id' => 13, 'description' => 'Internal Error / Connect Failed']];
                }
                continue;
            }

            $tokens = array_column($batch_response, 'token');
            if (empty($tokens)) {
                foreach ($chunk_submissions as $idx => $sub) {
                    $all_results[$idx] = ['status' => ['id' => 13, 'description' => 'Internal Error / No Tokens Returned']];
                }
                continue;
            }


            $poll_timeout = (int)(get_config('qtype_judge0', 'poll_timeout') ?: 45);
            $poll_timeout = max(5, min($poll_timeout, 120));
            $polled_results = $this->poll_batch_results($base_url, $tokens, $poll_timeout);
            
            $local_i = 0;
            foreach ($chunk_submissions as $idx => $sub) {
                if (isset($polled_results[$local_i])) {
                    $all_results[$idx] = $polled_results[$local_i];
                } else {
                    $token_str = $tokens[$local_i] ?? 'unknown';
                    debugging("Judge0 polling timeout for token: {$token_str}", DEBUG_DEVELOPER);
                    $all_results[$idx] = ['status' => ['id' => 13, 'description' => 'Internal Error / Timeout']];
                }
                $local_i++;
            }
        }

        $passed = 0;
        $total = count($cases_to_run);
        $total_weight = 0.0;
        $earned_weight = 0.0;

        foreach ($cases_to_run as $idx => $case) {
            $res = $all_results[$idx] ?? ['status' => ['id' => 13, 'description' => 'Internal Error']];
            
            $res['_testcase'] = [
                'input' => $case['input'],
                'expected' => $case['expected'],
                'is_hidden' => $case['is_hidden'],
                'weight' => $case['weight'],
                'is_dynamic' => $case['is_dynamic']
            ];
            $this->last_judge0_response[] = $res;

            $w = (float)$case['weight'];
            if ($w <= 0) $w = 1.0;
            $total_weight += $w;

            if (isset($res['status']['id']) && $res['status']['id'] == 3) {
                $passed++;
                $earned_weight += $w;
            }
        }

        if ($total_weight <= 0) $total_weight = 1.0;
        $fraction = $earned_weight / $total_weight;

        global $SESSION;
        $SESSION->qtype_judge0_last_result = json_encode($this->last_judge0_response);

        if ($passed === $total) {
            return array($fraction, question_state::$gradedright);
        } elseif ($passed > 0) {
            return array($fraction, question_state::$gradedpartial);
        }
        return array(0.0, question_state::$gradedwrong);
    }

    private function poll_batch_results($base_url, $tokens, $timeout = 45) {
        $start_time = time();
        $tokens_str = implode(',', $tokens);
        $poll_url = $base_url . '/submissions/batch?tokens=' . $tokens_str . '&base64_encoded=false&fields=*';
        
        $sleep_us = 500000;

        while (time() - $start_time < $timeout) {
            $response = $this->send_judge0_request($poll_url, '', false);
            if (!$response || !isset($response['submissions'])) {
                sleep(1);
                continue;
            }
            
            $all_done = true;
            foreach ($response['submissions'] as $sub) {
                $status_id = $sub['status']['id'] ?? 1;

                if ($status_id == 1 || $status_id == 2) {
                    $all_done = false;
                    break;
                }
            }
            
            if ($all_done) {
                return $response['submissions'];
            }
            
            usleep($sleep_us);
            if ($sleep_us < 2000000) {
                $sleep_us += 250000;
            }
        }
        
        return [];
    }

    private function send_judge0_request($url, $payload, $is_post = true) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->get_judge0_headers());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_errno !== 0) {
            debugging("Judge0 cURL error #{$curl_errno}: {$curl_error}", DEBUG_DEVELOPER);
            return false;
        }
        return $result ? json_decode($result, true) : false;
    }

    private function get_judge0_headers() {
        $headers = ['Content-Type: application/json'];
        $auth_header = trim((string)(get_config('qtype_judge0', 'auth_header') ?: ''));
        $auth_token = trim((string)(get_config('qtype_judge0', 'auth_token') ?: ''));

        if ($auth_header !== '' && $auth_token !== '') {
            if (preg_match('/^[A-Za-z0-9-]+$/', $auth_header)) {
                $headers[] = $auth_header . ': ' . $auth_token;
            } else {
                debugging('Judge0 auth header contains invalid characters.', DEBUG_DEVELOPER);
            }
        }

        return $headers;
    }

    private function get_allowed_language_ids() {
        if (empty($this->allowed_languages)) {
            return [];
        }

        $allowed = $this->allowed_languages;
        if (is_string($allowed)) {
            $decoded = json_decode($allowed, true);
            $allowed = is_array($decoded) ? $decoded : [];
        }

        $allowed = array_map('intval', (array)$allowed);
        $allowed = array_values(array_unique(array_filter($allowed, function($id) {
            return $id > 0;
        })));

        return $allowed;
    }

    private function resolve_language_id(array $response) {
        $default_lang = (int)($this->language_id ?: 71);
        $allowed = $this->get_allowed_language_ids();

        if (empty($allowed)) {
            return $default_lang;
        }

        if (!isset($response['language_id']) || $response['language_id'] === '') {
            return $allowed[0];
        }

        $requested = (int)$response['language_id'];
        if (!in_array($requested, $allowed, true)) {
            return false;
        }

        return $requested;
    }

    private function fail_grading($description, $judge0_response = null, $hidden = true, $case = null) {
        $details = '';
        if (is_array($judge0_response)) {
            $details = trim(implode("\n", array_filter([
                $judge0_response['stderr'] ?? '',
                $judge0_response['compile_output'] ?? '',
                $judge0_response['message'] ?? '',
                $judge0_response['status']['description'] ?? ''
            ])));
        } elseif ($judge0_response === false || $judge0_response === null) {
            $details = get_string('judge0_unavailable', 'qtype_judge0');
        }

        $this->last_judge0_response = [[
            'status' => [
                'id' => 13,
                'description' => $description
            ],
            'stderr' => $details,
            '_testcase' => [
                'input' => is_array($case) ? ($case['input'] ?? '') : '',
                'expected' => is_array($case) ? ($case['expected'] ?? '') : '',
                'is_hidden' => $hidden,
                'weight' => is_array($case) ? ($case['weight'] ?? 1.0) : 1.0,
                'is_dynamic' => is_array($case) ? ($case['is_dynamic'] ?? false) : false
            ]
        ]];

        debugging('Judge0 grading stopped: ' . $description . ($details ? ' ' . $details : ''), DEBUG_DEVELOPER);

        global $SESSION;
        $SESSION->qtype_judge0_last_result = json_encode($this->last_judge0_response);

        return array(0.0, question_state::$gradedwrong);
    }

    public function summarise_response(array $response) { return isset($response['answer']) ? $response['answer'] : null; }
    public function is_complete_response(array $response) { return array_key_exists('answer', $response) && $response['answer'] !== ''; }
    public function is_gradable_response(array $response) { return $this->is_complete_response($response); }
    public function is_same_response(array $prevresponse, array $newresponse) { 
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer') && 
               question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'language_id'); 
    }
    public function get_validation_error(array $response) {
        if ($this->resolve_language_id($response) === false) {
            return get_string('invalid_language', 'qtype_judge0');
        }
        return '';
    }
    public function get_correct_response() { return array(); }

    public function get_response_summary_for_storage(array $response) {
        return json_encode($this->last_judge0_response ?? []);
    }
}
