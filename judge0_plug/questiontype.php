<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');

class qtype_judge0 extends question_type {


    public function menu_name() {
        return get_string('pluginname', 'qtype_judge0');
    }
     
    public function can_be_created_by_user() {
        return true;
    }

    
    public function extra_question_fields() {
        return array('qtype_judge0_options', 'language_id', 'allowed_languages', 'checker_code', 'expected_output', 'reference_solution', 'reference_solution_language_id', 'compiler_options', 'cpu_time_limit', 'memory_limit', 'input_generator_code', 'input_generator_language_id');
    }

    public function save_question_options($formdata) {
        if (isset($formdata->allowed_languages)) {
            if (is_array($formdata->allowed_languages)) {
                $formdata->allowed_languages = json_encode(array_map('intval', $formdata->allowed_languages));
            } else {
               
                $decoded = json_decode($formdata->allowed_languages, true);
                if (is_array($decoded)) {
                    $formdata->allowed_languages = json_encode(array_map('intval', $decoded));
                } else {
                    $formdata->allowed_languages = '';
                }
            }
        } else {
            $formdata->allowed_languages = '';
        }

        $cpu_time_limit = trim((string)($formdata->cpu_time_limit ?? ''));
        $formdata->cpu_time_limit = $cpu_time_limit === '' ? null : (float)$cpu_time_limit;

        $memory_limit = trim((string)($formdata->memory_limit ?? ''));
        $formdata->memory_limit = $memory_limit === '' ? null : (int)$memory_limit;
        
        global $DB;
        $result = parent::save_question_options($formdata);
        if ($result instanceof stdClass) {
            return $result;
        }

        $DB->delete_records('qtype_judge0_testcases', ['questionid' => $formdata->id]);

        $testinputs = $formdata->test_input ?? [];
        if (empty($testinputs)) {
            $testinputs = optional_param_array('test_input', [], PARAM_RAW);
        }
        $expectedoutputs = $formdata->test_expected_output ?? [];
        if (empty($expectedoutputs)) {
            $expectedoutputs = optional_param_array('test_expected_output', [], PARAM_RAW);
        }
        $hiddenflags = $formdata->is_hidden ?? [];
        if (empty($hiddenflags)) {
            $hiddenflags = optional_param_array('is_hidden', [], PARAM_BOOL);
        }
        $weights = $formdata->weight ?? [];
        if (empty($weights)) {
            $weights = optional_param_array('weight', [], PARAM_FLOAT);
        }

        if (!empty($testinputs)) {
            foreach ($testinputs as $key => $input) {
                $input = is_array($input) ? ($input['text'] ?? '') : $input;
                $expected = $expectedoutputs[$key] ?? '';
                $expected = is_array($expected) ? ($expected['text'] ?? '') : $expected;
                if (trim($input) === '' && trim($expected) === '') {
                    continue;
                }
                $tc = new stdClass();
                $tc->questionid = $formdata->id;
                $tc->test_input = $input;
                $tc->expected_output = $expected;
                $tc->is_hidden = !empty($hiddenflags[$key]) ? 1 : 0;
                $weight = isset($weights[$key]) ? (float)$weights[$key] : 1.0;
                $tc->weight = $weight > 0 ? $weight : 1.0;
                $DB->insert_record('qtype_judge0_testcases', $tc);
            }
        }
        return true;
    }

    public function get_question_options($question) {
        global $DB;
        $result = parent::get_question_options($question);
        if ($result && isset($question->options)) {
            $question->options->testcases = $DB->get_records('qtype_judge0_testcases', ['questionid' => $question->id], 'id ASC');
        }
        return $result;
    }

    public function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->language_id = $questiondata->options->language_id ?? 71;
        $question->allowed_languages = [];
        if (!empty($questiondata->options->allowed_languages)) {
            $question->allowed_languages = json_decode($questiondata->options->allowed_languages, true) ?: [];
        }
        $question->checker_code = $questiondata->options->checker_code ?? '';
        $question->expected_output = $questiondata->options->expected_output ?? '';
        $question->reference_solution = $questiondata->options->reference_solution ?? '';
        $question->reference_solution_language_id = $questiondata->options->reference_solution_language_id ?? 71;
        $question->compiler_options = $questiondata->options->compiler_options ?? '';
        $question->cpu_time_limit = $questiondata->options->cpu_time_limit ?? null;
        $question->memory_limit = $questiondata->options->memory_limit ?? null;
        $question->input_generator_code = $questiondata->options->input_generator_code ?? '';
        $question->input_generator_language_id = $questiondata->options->input_generator_language_id ?? 71;
        $question->testcases = $questiondata->options->testcases ?? [];
    }
}
