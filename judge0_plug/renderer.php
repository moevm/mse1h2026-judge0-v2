<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');

        $safe_id = preg_replace('/[^A-Za-z0-9_-]/', '_', $inputname);
        $container_id = 'monaco_container_' . $safe_id;
        $textarea_id = 'hidden_textarea_' . $safe_id;
        
        $lang_id_inputname = $qa->get_qt_field_name('language_id');
        $current_lang_id = (int)$qa->get_last_qt_var('language_id', 0);

        $html = html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));
        
        $html .= html_writer::start_tag('div', array('style' => 'margin-top: 15px; position: relative;'));

        $monaco_modes = [
            71 => 'python',   50 => 'c',      54 => 'cpp',    62 => 'java',
            63 => 'javascript', 51 => 'csharp', 46 => 'shell',
            72 => 'ruby',     73 => 'rust',     78 => 'kotlin',  68 => 'php'
        ];
        $language_names = [
            71 => 'Python 3.8.1', 50 => 'C (GCC 9.2.0)', 54 => 'C++ (GCC 9.2.0)', 62 => 'Java (OpenJDK 13.0.1)',
            63 => 'JavaScript (Node.js 12.14.0)', 51 => 'C# (Mono 6.6.0.161)', 46 => 'Bash (5.0.0)',
            72 => 'Ruby (2.7.0)', 73 => 'Rust (1.40.0)', 78 => 'Kotlin (1.3.70)', 68 => 'PHP (7.4.1)'
        ];

        $allowed_langs = array_values(array_unique(array_map('intval', (array)($question->allowed_languages ?? []))));
        $allowed_langs = array_values(array_filter($allowed_langs, function($id) {
            return $id > 0;
        }));
        if (empty($allowed_langs)) {
            $allowed_langs = [(int)($question->language_id ?? 71)];
        }
        if (!$current_lang_id || !in_array($current_lang_id, $allowed_langs, true)) {
            $current_lang_id = (int)$allowed_langs[0];
        }

        $lang_mode = $monaco_modes[$current_lang_id] ?? 'python';
        $dropdown_id = 'lang_select_' . $safe_id;
        $monaco_base_url = $this->get_monaco_base_url();
        $monaco_enabled = $monaco_base_url !== '';
        
        $readonly_attr = (!empty($options) && $options->readonly) ? ' disabled' : '';

        $html .= '<div style="margin-bottom: 12px;">';
        if (count($allowed_langs) === 1) {
            $lang_name = $language_names[$current_lang_id] ?? "Language ID: {$current_lang_id}";
            $html .= '<span id="judge0_lang_badge" style="
                display: inline-block; background: #e8f4fd;
                border: 1px solid #a0cfe8; border-radius: 4px;
                padding: 4px 12px; font-size: 13px; color: #2c5f8a; font-weight: bold;">
                &#x1F4BB; ' . get_string('language_label', 'qtype_judge0') . ' '
                . htmlspecialchars($lang_name) . '
            </span>';
        } else {
            $html .= '<label for="' . htmlspecialchars($dropdown_id) . '"
                style="display: block; font-weight: bold; margin-bottom: 6px; color: #333;">
                &#x1F4BB; ' . get_string('choose_language', 'qtype_judge0') . ':
            </label>';
            $html .= '<select id="' . htmlspecialchars($dropdown_id) . '"' . $readonly_attr . '
                style="padding: 6px 10px; border-radius: 4px; border: 1px solid #aaa; font-size: 14px;">';
            foreach ($allowed_langs as $lid) {
                $sel = ($lid == $current_lang_id) ? ' selected' : '';
                $name = $language_names[$lid] ?? "Language {$lid}";
                $mode = $monaco_modes[$lid] ?? 'python';
                $html .= '<option value="' . (int)$lid . '" data-mode="'
                    . htmlspecialchars($mode) . '"' . $sel . '>'
                    . htmlspecialchars($name) . '</option>';
            }
            $html .= '</select>';
        }
        $html .= '</div>';

        $iframe_id = 'monaco_iframe_' . $safe_id;

        $textarea_style = $monaco_enabled
            ? 'display: none;'
            : 'width: 100%; min-height: 400px; font-family: monospace; font-size: 14px;';

        if ($monaco_enabled) {
            $loader_url = $monaco_base_url . '/loader.js';
            $worker_url = $monaco_base_url . '/base/worker/workerMain.js';
            $iframe_channel = 'qtype_judge0_' . $safe_id;
            $iframe_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        html, body, #monaco-root {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #1e1e1e;
        }
    </style>
</head>
<body>
    <div id="monaco-root"></div>
    <script src="' . htmlspecialchars($loader_url) . '"></script>
    <script>
        (function() {
            var editor = null;
            var editorId = ' . json_encode($safe_id) . ';
            var channel = ' . json_encode($iframe_channel) . ';
            var mode = ' . json_encode($lang_mode) . ';
            var monacoBaseUrl = ' . json_encode($monaco_base_url) . ';
            var workerUrl = ' . json_encode($worker_url) . ';

            require.config({ paths: { "vs": monacoBaseUrl } });
            window.MonacoEnvironment = {
                getWorkerUrl: function() {
                    return "data:text/javascript;charset=utf-8," + encodeURIComponent(
                        "self.MonacoEnvironment = { baseUrl: " + JSON.stringify(monacoBaseUrl + "/") + " }; importScripts(" + JSON.stringify(workerUrl) + ");"
                    );
                }
            };

            require(["vs/editor/editor.main"], function() {
                editor = monaco.editor.create(document.getElementById("monaco-root"), {
                    value: "",
                    language: mode,
                    theme: "vs-dark",
                    automaticLayout: true,
                    fontSize: 14,
                    minimap: { enabled: false },
                    scrollBeyondLastLine: false
                });

                editor.onDidChangeModelContent(function() {
                    window.parent.postMessage({
                        type: "monaco_change",
                        id: editorId,
                        channel: channel,
                        value: editor.getValue()
                    }, "*");
                });

                window.addEventListener("message", function(event) {
                    if (!event.data || event.data.id !== editorId || event.data.channel !== channel) {
                        return;
                    }
                    if (event.data.type === "set_value" && editor.getValue() !== event.data.value) {
                        editor.setValue(event.data.value || "");
                    }
                    if (event.data.type === "set_language" && event.data.mode) {
                        monaco.editor.setModelLanguage(editor.getModel(), event.data.mode);
                    }
                });

                window.parent.postMessage({
                    type: "monaco_ready",
                    id: editorId,
                    channel: channel
                }, "*");
            });
        })();
    </script>
</body>
</html>';

            $html .= html_writer::tag('iframe', '', array(
                'id' => $iframe_id,
                'srcdoc' => $iframe_html,
                'style' => 'width: 100%; height: 400px; border: 1px solid #ccc; border-radius: 4px; display: block; background: #1e1e1e;',
                'frameborder' => '0'
            ));
        }
        
        $html .= html_writer::tag('textarea', htmlspecialchars($currentAnswer), array(
            'id' => $textarea_id,
            'name' => $inputname,
            'rows' => 20,
            'style' => $textarea_style
        ));
        
        $html .= html_writer::tag('input', '', array(
            'type' => 'hidden',
            'id' => 'hidden_lang_' . $safe_id,
            'name' => $lang_id_inputname,
            'value' => $current_lang_id
        ));

        if ($monaco_enabled) {
            $textarea_js = json_encode($textarea_id);
            $lang_input_js = json_encode('hidden_lang_' . $safe_id);
            $dropdown_js = json_encode($dropdown_id);
            $iframe_js = json_encode($iframe_id);
            $editor_id_js = json_encode($safe_id);
            $iframe_channel_js = json_encode($iframe_channel);
            $initial_value_js = json_encode((string)$currentAnswer, JSON_UNESCAPED_UNICODE);
            $js = "
        <script>
            (function() {
                var hiddenInput = document.getElementById({$textarea_js});
                var langInput = document.getElementById({$lang_input_js});
                var select = document.getElementById({$dropdown_js});
                var iframe = document.getElementById({$iframe_js});
                var editorId = {$editor_id_js};
                var channel = {$iframe_channel_js};
                var initialValue = {$initial_value_js};

                hiddenInput.setAttribute('data-qtype-judge0-editor', '1');
                hiddenInput.qtypeJudge0Sync = function() {
                    return hiddenInput.value;
                };

                var form = hiddenInput.closest('form');
                if (form && !form.getAttribute('data-qtype-judge0-submit-guard')) {
                    form.setAttribute('data-qtype-judge0-submit-guard', '1');
                    form.addEventListener('submit', function(event) {
                        var editors = form.querySelectorAll('textarea[data-qtype-judge0-editor=\"1\"]');
                        Array.prototype.forEach.call(editors, function(textarea) {
                            if (typeof textarea.qtypeJudge0Sync === 'function') {
                                textarea.qtypeJudge0Sync();
                            }
                        });

                        if (form.getAttribute('data-qtype-judge0-submitting') === '1') {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            return false;
                        }

                        form.setAttribute('data-qtype-judge0-submitting', '1');
                        window.setTimeout(function() {
                            if (event.defaultPrevented) {
                                form.removeAttribute('data-qtype-judge0-submitting');
                                return;
                            }
                            var buttons = form.querySelectorAll('input[type=\"submit\"], button[type=\"submit\"]');
                            Array.prototype.forEach.call(buttons, function(button) {
                                button.disabled = true;
                            });
                        }, 0);
                    }, true);
                }

                window.addEventListener('message', function(event) {
                    if (!event.data || event.data.id !== editorId || event.data.channel !== channel) {
                        return;
                    }

                    if (event.data.type === 'monaco_ready') {
                        iframe.contentWindow.postMessage({
                            type: 'set_value',
                            id: editorId,
                            channel: channel,
                            value: initialValue
                        }, '*');
                    } else if (event.data.type === 'monaco_change') {
                        hiddenInput.value = event.data.value;
                    }
                });

                if (select) {
                    select.addEventListener('change', function() {
                        var opt = select.options[select.selectedIndex];
                        var mode = opt.getAttribute('data-mode');
                        var langId = opt.value;
                        if (langInput) {
                            langInput.value = langId;
                        }
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({
                                type: 'set_language',
                                id: editorId,
                                channel: channel,
                                mode: mode
                            }, '*');
                        }
                    });
                }
            })();
        </script>
        ";
            $html .= $js;
        } else {
            $textarea_js = json_encode($textarea_id);
            $lang_input_js = json_encode('hidden_lang_' . $safe_id);
            $dropdown_js = json_encode($dropdown_id);
            $js = "
        <script>
            (function() {
                var textarea = document.getElementById({$textarea_js});
                var langInput = document.getElementById({$lang_input_js});
                var select = document.getElementById({$dropdown_js});
                if (textarea) {
                    textarea.setAttribute('data-qtype-judge0-editor', '1');
                }
                if (select && langInput) {
                    select.addEventListener('change', function() {
                        langInput.value = select.value;
                    });
                }
            })();
        </script>
        ";
            $html .= $js;
        }

        $html .= html_writer::end_tag('div');

        return $html;
    }

    public function specific_feedback(question_attempt $qa, question_display_options $options = null) {
        $html = '';
        $question = $qa->get_question();
        $state = $qa->get_state();
        $currentAnswer = $qa->get_last_qt_var('answer', '');

        if (!empty($currentAnswer)) {
            if ($state == question_state::$gradedright) {
                $html .= $this->get_success_box();
            }
            if ($state->is_finished()) {
                $html .= $this->get_debug_box($qa, $question);
            }
            $readonly = $options && $options->readonly;
            if (!$readonly) {
                $html .= $this->get_recheck_button($qa);
            }
        }

        return $html;
    }

    private function get_recheck_button(question_attempt $qa) {
        $submit_name = $qa->get_behaviour_field_name('submit');
        return '
        <div style="margin-top: 16px; text-align: right;">
            <button id="judge0_recheck_btn" type="button"
                style="padding: 8px 20px; background: #0f6cbf; color: #fff;
                       border: none; border-radius: 4px; cursor: pointer; font-size: 14px;"
                onclick="(function() {
                    var btn = document.querySelector(\'input[name=&quot;' . $submit_name . '&quot;]\')
                           || document.querySelector(\'input[type=&quot;submit&quot;][name$=&quot;-submit&quot;]\')
                           || document.querySelector(\'button[type=&quot;submit&quot;]\')
                           || document.querySelector(\'input[type=&quot;submit&quot;]\');
                    if (btn) {
                        btn.click();
                    } else {
                        console.error(&quot;Check button not found! Expected name: ' . $submit_name . '&quot;);
                    }
                })()">
                &#x1F501; ' . get_string('recheck_button', 'qtype_judge0') . '
            </button>
        </div>';
    }

    private function get_monaco_base_url() {
        global $CFG;

        $base_url = trim((string)(get_config('qtype_judge0', 'monaco_base_url') ?: ''));
        if ($base_url === '') {
            return '';
        }

        $base_url = rtrim($base_url, '/');
        if (preg_match('#^https?://#i', $base_url)) {
            return $base_url;
        }

        $wwwroot = rtrim($CFG->wwwroot, '/');
        if (strpos($base_url, '/') === 0) {
            return $wwwroot . $base_url;
        }

        return $wwwroot . '/' . $base_url;
    }

    private function get_success_box() {
        $html = "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; border: 1px solid #c3e6cb; margin-top: 15px; display: flex; align-items: center;'>";
        $html .= "<span style='font-size: 24px; margin-right: 15px;'>✅</span>";
        $html .= "<div>";
        $html .= "<h4 style='margin: 0; color: #155724;'>Поздравляем!</h4>";
        $html .= "<p style='margin: 5px 0 0 0;'>Ваше решение успешно прошло все тесты.</p>";
        $html .= "</div></div>";
        return $html;
    }

    private function get_debug_box(question_attempt $qa, $question) {
        $stored = $qa->get_last_qt_var('_judge0_result', '');
        $data = json_decode($stored, true);
        
        if (empty($data) && !empty($question->last_judge0_response)) {
            $data = $question->last_judge0_response;
        }

        global $SESSION;
        if (empty($data) && !empty($SESSION->qtype_judge0_last_result)) {
            $data = json_decode($SESSION->qtype_judge0_last_result, true);
            unset($SESSION->qtype_judge0_last_result);
        }

        if (empty($data)) {
            return '';
        }

        if (isset($data['status'])) {
            $data = [$data];
        }

        $box = '<div style="margin-top: 25px; border-top: 2px solid #dee2e6; padding-top: 15px;">';
        $box .= '<h4 style="margin-bottom: 15px; color: #495057;">Результаты тестирования:</h4>';
        $box .= '<div class="table-responsive">';
        $box .= '<table class="generaltable table table-bordered table-striped table-hover" style="width: 100%; text-align: left; background-color: #fff; margin-bottom: 20px;">';
        $box .= '<thead style="background-color: #f8f9fa;"><tr>';
        $box .= '<th scope="col" style="padding: 10px 12px; width: 1%;">#</th>';
        $box .= '<th scope="col" style="padding: 10px 12px;">Ввод (stdin)</th>';
        $box .= '<th scope="col" style="padding: 10px 12px;">Ожидалось</th>';
        $box .= '<th scope="col" style="padding: 10px 12px;">Ваш вывод</th>';
        $box .= '<th scope="col" style="padding: 10px 12px; width: 1%; white-space: nowrap;">Статус</th>';
        $box .= '</tr></thead><tbody>';

        foreach ($data as $index => $res) {
            $status = $res['status']['description'] ?? 'Ошибка';
            $status_id = $res['status']['id'] ?? 0;
            $stdout = $res['stdout'] ?? '';
            $stderr = $res['stderr'] ?? '';
            $compile_output = $res['compile_output'] ?? '';
            $message = $res['message'] ?? '';
            
            $is_hidden = false;
            $input = '';
            $expected = $question->expected_output ?? ''; 

            $is_dynamic = false;
            if (isset($res['_testcase'])) {
                $is_hidden = !empty($res['_testcase']['is_hidden']);
                $is_dynamic = !empty($res['_testcase']['is_dynamic']);
                $input = $res['_testcase']['input'];
                $expected = $res['_testcase']['expected'];
            }

            if ($status_id == 3) {
                $status_html = '<span style="display:inline-block;background-color:#28a745;color:white;padding:4px 8px;border-radius:4px;font-weight:bold;font-size:13px;">' . htmlspecialchars($status) . '</span>';
            } else {
                $status_html = '<span style="display:inline-block;background-color:#dc3545;color:white;padding:4px 8px;border-radius:4px;font-weight:bold;font-size:13px;">' . htmlspecialchars($status) . '</span>';
            }

            if ($is_hidden) {
                $input_html = '<span style="color:#6c757d;font-style:italic;">Скрытый тест</span>';
                $expected_html = '<span style="color:#6c757d;font-style:italic;">Скрыто</span>';
                $stdout_html = '<span style="color:#6c757d;font-style:italic;">Скрыто</span>';
            } else {
                $input_html = '<pre style="margin:0;font-size:13px;background:transparent;border:0;padding:0;white-space:pre-wrap;">' . htmlspecialchars($input) . '</pre>';
                if ($is_dynamic) {
                    $input_html .= '<div style="font-size:11px;color:#17a2b8;margin-top:3px;">(Сгенерировано)</div>';
                }
                
                $expected_html = '<pre style="margin:0;font-size:13px;background:transparent;border:0;padding:0;white-space:pre-wrap;">' . htmlspecialchars($expected) . '</pre>';
                $stdout_disp = (string)($stdout ?: '');
                if ($compile_output) {
                    $stdout_disp .= ($stdout_disp === '' ? '' : "\n") . "[COMPILE]\n" . $compile_output;
                }
                if ($stderr) {
                    $stdout_disp .= ($stdout_disp === '' ? '' : "\n") . "[STDERR]\n" . $stderr;
                }
                if ($message) {
                    $stdout_disp .= ($stdout_disp === '' ? '' : "\n") . "[MESSAGE]\n" . $message;
                }
                if (trim($stdout_disp) === '') $stdout_disp = 'Пусто';
                $stdout_html = '<pre style="margin:0;font-size:13px;background:transparent;border:0;padding:0;white-space:pre-wrap;">' . htmlspecialchars($stdout_disp) . '</pre>';
            }

            $box .= '<tr>';
            $box .= '<td style="padding: 10px 12px; vertical-align: top;"><b>' . ($index + 1) . '</b></td>';
            $box .= '<td style="padding: 10px 12px; vertical-align: top;">' . $input_html . '</td>';
            $box .= '<td style="padding: 10px 12px; vertical-align: top;">' . $expected_html . '</td>';
            $box .= '<td style="padding: 10px 12px; vertical-align: top;">' . $stdout_html . '</td>';
            $box .= '<td style="padding: 10px 12px; vertical-align: top;">' . $status_html . '</td>';
            $box .= '</tr>';
        }
        $box .= '</tbody></table></div></div>';

        return $box;
    }
}
