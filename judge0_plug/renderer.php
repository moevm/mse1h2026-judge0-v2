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
                    if (event.data.type === "get_value") {
                        window.parent.postMessage({
                            type: "monaco_value",
                            id: editorId,
                            channel: channel,
                            requestId: event.data.requestId || "",
                            value: editor.getValue()
                        }, "*");
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
            'style' => $textarea_style,
            'data-qtype-judge0-editor' => '1'
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
                var pendingSyncs = {};

                hiddenInput.value = initialValue;
                hiddenInput.setAttribute('data-qtype-judge0-editor', '1');
                hiddenInput.qtypeJudge0Sync = function() {
                    return hiddenInput.value;
                };
                hiddenInput.qtypeJudge0RequestSync = function() {
                    if (typeof Promise === 'undefined' || !iframe || !iframe.contentWindow) {
                        return null;
                    }

                    return new Promise(function(resolve) {
                        var requestId = editorId + '_' + Date.now() + '_' + Math.random();
                        var done = false;
                        var finish = function(value) {
                            if (done) {
                                return;
                            }
                            done = true;
                            delete pendingSyncs[requestId];
                            hiddenInput.value = (typeof value === 'string') ? value : hiddenInput.value;
                            resolve(hiddenInput.value);
                        };

                        pendingSyncs[requestId] = finish;
                        window.setTimeout(function() {
                            finish(hiddenInput.value);
                        }, 700);

                        iframe.contentWindow.postMessage({
                            type: 'get_value',
                            id: editorId,
                            channel: channel,
                            requestId: requestId
                        }, '*');
                    });
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
                        if (typeof jQuery !== 'undefined') {
                            jQuery(hiddenInput).trigger('change');
                        } else {
                            hiddenInput.dispatchEvent(new Event('change', {bubbles: true}));
                        }
                    } else if (event.data.type === 'monaco_value') {
                        if (event.data.requestId && pendingSyncs[event.data.requestId]) {
                            pendingSyncs[event.data.requestId](event.data.value);
                        }
                    }
                });

                if (select) {
                    select.addEventListener('change', function() {
                        var opt = select.options[select.selectedIndex];
                        var mode = opt.getAttribute('data-mode');
                        var langId = opt.value;
                        if (langInput) {
                            langInput.value = langId;
                            if (typeof jQuery !== 'undefined') {
                                jQuery(langInput).trigger('change');
                            } else {
                                langInput.dispatchEvent(new Event('change', {bubbles: true}));
                            }
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
                    textarea.qtypeJudge0Sync = function() {
                        return textarea.value;
                    };
                }
                if (select && langInput) {
                    select.addEventListener('change', function() {
                        langInput.value = select.value;
                        if (typeof jQuery !== 'undefined') {
                            jQuery(langInput).trigger('change');
                        } else {
                            langInput.dispatchEvent(new Event('change', {bubbles: true}));
                        }
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
        global $CFG;

        $answer_name_js = json_encode($qa->get_qt_field_name('answer'));
        $language_name_js = json_encode($qa->get_qt_field_name('language_id'));
        $slot_js = json_encode((string)$qa->get_slot());
        $question_id_js = json_encode((string)$qa->get_question_id());
        $autosave_url_js = json_encode($CFG->wwwroot . '/mod/quiz/autosave.ajax.php');
        $recheck_url_js = json_encode($CFG->wwwroot . '/question/type/judge0/ajax/recheck.php');
        $status_url_js = json_encode($CFG->wwwroot . '/question/type/judge0/ajax/status.php');
        $default_title_js = json_encode(get_string('recheck_button', 'qtype_judge0'));
        $wait_title_js = json_encode(get_string('recheck_wait', 'qtype_judge0'));
        $saving_title_js = json_encode(get_string('recheck_saving', 'qtype_judge0'));
        $queued_title_js = json_encode(get_string('recheck_queued', 'qtype_judge0'));
        $running_title_js = json_encode(get_string('recheck_running', 'qtype_judge0'));
        $passed_title_js = json_encode(get_string('recheck_passed', 'qtype_judge0'));
        $failed_title_js = json_encode(get_string('recheck_failed', 'qtype_judge0'));
        $error_title_js = json_encode(get_string('recheck_error', 'qtype_judge0'));
        $timeout_title_js = json_encode(get_string('recheck_timeout', 'qtype_judge0'));
        $hidden_test_js = json_encode('<span style="color:#6c757d;font-style:italic;">Скрытый тест</span>');
        $hidden_value_js = json_encode('<span style="color:#6c757d;font-style:italic;">Скрыто</span>');
        $empty_value_js = json_encode('Пусто');
        $state_correct_js = json_encode(get_string('correct', 'question'));
        $state_incorrect_js = json_encode(get_string('incorrect', 'question'));
        $state_partial_js = json_encode(get_string('partiallycorrect', 'question'));
        $onclick = "
            (function(button) {
                var form = button.closest('form');
                if (!form) {
                    console.error('Quiz form not found for Judge0 recheck button');
                    return false;
                }

                if (button.getAttribute('data-running') === '1') {
                    return false;
                }

                var now = Date.now();
                var lastRun = Number(button.getAttribute('data-last-run') || 0);
                var waitTitle = {$wait_title_js};
                var defaultTitle = button.getAttribute('data-original-title')
                    || button.getAttribute('title')
                    || {$default_title_js};

                if (!button.getAttribute('data-original-title')) {
                    button.setAttribute('data-original-title', defaultTitle);
                }

                if (now - lastRun < 5000) {
                    button.setAttribute('title', waitTitle);
                    return false;
                }
                button.setAttribute('data-last-run', String(now));

                var statusBox = button.parentNode.querySelector('[data-qtype-judge0-recheck-status=\"1\"]');
                var setStatus = function(message, isError) {
                    if (!statusBox) {
                        return;
                    }
                    statusBox.textContent = message || '';
                    statusBox.style.color = isError ? '#dc3545' : '#495057';
                };

                var clearStatus = function() {
                    if (statusBox) {
                        statusBox.textContent = '';
                        while (statusBox.firstChild) {
                            statusBox.removeChild(statusBox.firstChild);
                        }
                    }
                    var original = form.querySelector('.qtype-judge0-original-results');
                    if (original) {
                        original.style.display = 'none';
                    }
                };

                var appendCell = function(row, value, isHtml) {
                    var cell = document.createElement('td');
                    cell.style.padding = '10px 12px';
                    cell.style.verticalAlign = 'top';
                    if (isHtml) {
                        cell.innerHTML = value;
                    } else {
                        var pre = document.createElement('pre');
                        pre.style.margin = '0';
                        pre.style.fontSize = '13px';
                        pre.style.background = 'transparent';
                        pre.style.border = '0';
                        pre.style.padding = '0';
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.textContent = value || '';
                        cell.appendChild(pre);
                    }
                    row.appendChild(cell);
                };

                var renderResults = function(payload) {
                    if (!statusBox) {
                        return;
                    }

                    var que = button.closest('.que');
                    if (que && payload && payload.state) {
                        var stateDiv = que.querySelector('.state');
                        var stateClass = 'incorrect';
                        var stateText = {$state_incorrect_js};
                        
                        if (payload.state === 'gradedright') {
                            stateClass = 'correct';
                            stateText = {$state_correct_js};
                        } else if (payload.state === 'gradedpartial') {
                            stateClass = 'partiallycorrect';
                            stateText = {$state_partial_js};
                        }
                        
                        que.classList.remove('notanswered', 'incorrect', 'partiallycorrect', 'correct');
                        que.classList.add(stateClass);
                        
                        if (stateDiv) {
                            stateDiv.textContent = stateText;
                        }
                        
                        var gradeDiv = que.querySelector('.grade');
                        if (gradeDiv && payload.fraction !== undefined && payload.fraction !== null) {
                            var numbers = gradeDiv.textContent.match(/[\d.]+/g);
                            if (numbers && numbers.length >= 2) {
                                var maxMark = parseFloat(numbers[1]);
                                var newMark = (parseFloat(payload.fraction) * maxMark).toFixed(2);
                                gradeDiv.textContent = gradeDiv.textContent.replace(numbers[0], newMark);
                            } else if (numbers && numbers.length === 1) {
                                var maxMark = parseFloat(numbers[0]);
                                var newMark = (parseFloat(payload.fraction) * maxMark).toFixed(2);
                                gradeDiv.textContent = 'Mark ' + newMark + ' out of ' + maxMark.toFixed(2);
                            }
                        }
                    }

                    clearStatus();
                    var title = document.createElement('div');
                    var passed = payload && (payload.state === 'gradedright' || Number(payload.fraction) >= 1);
                    title.textContent = passed ? {$passed_title_js} : {$failed_title_js};
                    title.style.fontWeight = '600';
                    title.style.marginBottom = '8px';
                    title.style.color = passed ? '#155724' : '#721c24';
                    statusBox.appendChild(title);

                    var results = payload && payload.results ? payload.results : [];
                    if (!results.length) {
                        if (payload && payload.error) {
                            title.textContent = {$error_title_js} + ' ' + payload.error;
                        }
                        return;
                    }

                    var h4 = document.createElement('h4');
                    h4.style.marginBottom = '15px';
                    h4.style.marginTop = '25px';
                    h4.style.paddingTop = '15px';
                    h4.style.borderTop = '2px solid #dee2e6';
                    h4.style.color = '#495057';
                    h4.textContent = 'Результаты тестирования:';
                    statusBox.appendChild(h4);

                    var table = document.createElement('table');
                    table.className = 'generaltable table table-bordered table-striped table-hover';
                    table.style.width = '100%';
                    table.style.textAlign = 'left';
                    table.style.backgroundColor = '#fff';
                    table.style.marginBottom = '20px';

                    var head = document.createElement('thead');
                    head.style.backgroundColor = '#f8f9fa';
                    var headRow = document.createElement('tr');
                    
                    var th1 = document.createElement('th'); th1.textContent = '#'; th1.style.padding = '10px 12px'; th1.style.width = '1%'; headRow.appendChild(th1);
                    var th2 = document.createElement('th'); th2.textContent = 'Ввод (stdin)'; th2.style.padding = '10px 12px'; headRow.appendChild(th2);
                    var th3 = document.createElement('th'); th3.textContent = 'Ожидалось'; th3.style.padding = '10px 12px'; headRow.appendChild(th3);
                    var th4 = document.createElement('th'); th4.textContent = 'Ваш вывод'; th4.style.padding = '10px 12px'; headRow.appendChild(th4);
                    var th5 = document.createElement('th'); th5.textContent = 'Статус'; th5.style.padding = '10px 12px'; th5.style.width = '1%'; th5.style.whiteSpace = 'nowrap'; headRow.appendChild(th5);
                    
                    head.appendChild(headRow);
                    table.appendChild(head);

                    var body = document.createElement('tbody');
                    results.forEach(function(result, index) {
                        var testcase = result._testcase || {};
                        var status = result.status || {};
                        var row = document.createElement('tr');
                        
                        var bIndex = document.createElement('b');
                        bIndex.textContent = String(index + 1);
                        var indexCell = document.createElement('td');
                        indexCell.style.padding = '10px 12px';
                        indexCell.style.verticalAlign = 'top';
                        indexCell.appendChild(bIndex);
                        row.appendChild(indexCell);
                        
                        var isHidden = testcase.is_hidden && String(testcase.is_hidden) !== '0' && String(testcase.is_hidden) !== 'false';
                        if (isHidden) {
                            appendCell(row, {$hidden_test_js}, true);
                            appendCell(row, {$hidden_value_js}, true);
                            appendCell(row, {$hidden_value_js}, true);
                        } else {
                            var inputHtml = '<pre style=\"margin:0;font-size:13px;background:transparent;border:0;padding:0;white-space:pre-wrap;\">' + 
                                            (testcase.input || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                            if (testcase.is_dynamic) {
                                inputHtml += '<div style=\"font-size:11px;color:#17a2b8;margin-top:3px;\">(Сгенерировано)</div>';
                            }
                            appendCell(row, inputHtml, true);
                            appendCell(row, testcase.expected || '', false);
                            
                            var output = result.stdout || '';
                            if (result.compile_output) {
                                output += (output ? '\\n' : '') + '[COMPILE]\\n' + result.compile_output;
                            }
                            if (result.stderr) {
                                output += (output ? '\\n' : '') + '[STDERR]\\n' + result.stderr;
                            }
                            if (result.message) {
                                output += (output ? '\\n' : '') + '[MESSAGE]\\n' + result.message;
                            }
                            if (!output || output.trim() === '') {
                                appendCell(row, {$empty_value_js}, false);
                            } else {
                                appendCell(row, output, false);
                            }
                        }
                        
                        var statusId = status.id || 0;
                        var statusHtml = '';
                        if (statusId == 3) {
                            statusHtml = '<span style=\"display:inline-block;background-color:#28a745;color:white;padding:4px 8px;border-radius:4px;font-weight:bold;font-size:13px;\">' + (status.description || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                        } else {
                            statusHtml = '<span style=\"display:inline-block;background-color:#dc3545;color:white;padding:4px 8px;border-radius:4px;font-weight:bold;font-size:13px;\">' + (status.description || 'Ошибка').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                        }
                        appendCell(row, statusHtml, true);
                        
                        body.appendChild(row);
                    });
                    table.appendChild(body);
                    statusBox.appendChild(table);
                };

                var readJson = function(response) {
                    return response.text().then(function(text) {
                        var data = text ? JSON.parse(text) : {};
                        if (!response.ok || data.error) {
                            throw new Error(data.error || data.message || response.statusText);
                        }
                        return data;
                    });
                };

                var fetchJson = function(url, options) {
                    options.credentials = 'same-origin';
                    options.headers = options.headers || {};
                    options.headers['X-Requested-With'] = 'XMLHttpRequest';
                    return fetch(url, options).then(readJson);
                };

                var finishButton = function() {
                    button.removeAttribute('data-running');
                    var elapsed = Date.now() - now;
                    var wait = Math.max(0, 5000 - elapsed);
                    window.setTimeout(function() {
                        if (button.getAttribute('data-running') !== '1') {
                            button.disabled = false;
                            button.setAttribute('title', defaultTitle);
                        }
                    }, wait);
                };

                var editors = form.querySelectorAll('textarea[data-qtype-judge0-editor=\"1\"]');
                var syncs = [];
                Array.prototype.forEach.call(editors, function(textarea) {
                    if (typeof textarea.qtypeJudge0Sync === 'function') {
                        textarea.qtypeJudge0Sync();
                    }
                    if (typeof textarea.qtypeJudge0RequestSync === 'function') {
                        var sync = textarea.qtypeJudge0RequestSync();
                        if (sync && typeof sync.then === 'function') {
                            syncs.push(sync);
                        }
                    }
                });

                button.disabled = true;
                button.setAttribute('data-running', '1');
                button.setAttribute('title', waitTitle);
                setStatus({$saving_title_js}, false);

                var runQueuedRecheck = function() {
                    var autosaveUrl = {$autosave_url_js};
                    var recheckUrl = {$recheck_url_js};
                    var statusUrl = {$status_url_js};
                    var answerName = {$answer_name_js};
                    var languageName = {$language_name_js};
                    var slot = {$slot_js};
                    var questionId = {$question_id_js};
                    var autosaveData = new FormData(form);

                    if (!autosaveData.get('sesskey') && typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) {
                        autosaveData.set('sesskey', M.cfg.sesskey);
                    }

                    var attemptId = autosaveData.get('attempt');
                    var sesskey = autosaveData.get('sesskey');
                    var answer = autosaveData.get(answerName) || '';
                    var languageId = autosaveData.get(languageName) || '';

                    if (!attemptId) {
                        throw new Error('Quiz attempt id not found.');
                    }
                    if (!sesskey) {
                        throw new Error('Moodle session key not found.');
                    }

                    var urlEncodedData = new URLSearchParams(autosaveData).toString();

                    return fetch(autosaveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: urlEncodedData
                    }).then(readJson).then(function(autosaveResult) {
                        if (!autosaveResult || autosaveResult.status !== 'OK') {
                            throw new Error({$error_title_js});
                        }

                        setStatus({$queued_title_js}, false);
                        var recheckData = new FormData();
                        recheckData.set('sesskey', sesskey);
                        recheckData.set('attemptid', attemptId);
                        recheckData.set('cmid', autosaveData.get('cmid') || '');
                        recheckData.set('slot', slot);
                        recheckData.set('questionid', questionId);
                        recheckData.set('answer', answer);
                        recheckData.set('languageid', languageId);

                        return fetch(recheckUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                            body: recheckData
                        }).then(readJson);
                    }).then(function(queueResult) {
                        if (!queueResult || !queueResult.token) {
                            throw new Error({$error_title_js});
                        }

                        setStatus({$running_title_js}, false);
                        var poll = function(attempt) {
                            var statusData = new FormData();
                            statusData.set('sesskey', sesskey);
                            statusData.set('token', queueResult.token);

                            return fetch(statusUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {'X-Requested-With': 'XMLHttpRequest'},
                                body: statusData
                            }).then(readJson).then(function(statusResult) {
                                if (statusResult.status === 'completed') {
                                    renderResults(statusResult);
                                    finishButton();
                                    return;
                                }
                                if (statusResult.status === 'failed') {
                                    setStatus({$error_title_js} + (statusResult.error ? ' ' + statusResult.error : ''), true);
                                    finishButton();
                                    return;
                                }
                                if (attempt >= 120) {
                                    setStatus({$timeout_title_js}, true);
                                    finishButton();
                                    return;
                                }
                                window.setTimeout(function() {
                                    poll(attempt + 1);
                                }, Math.min(1000 + attempt * 250, 5000));
                            });
                        };

                        return poll(0);
                    });
                };

                if (syncs.length > 0 && typeof Promise !== 'undefined') {
                    Promise.all(syncs).then(runQueuedRecheck).catch(function(error) {
                        setStatus({$error_title_js} + ' ' + error.message, true);
                        finishButton();
                    });
                } else {
                    try {
                        runQueuedRecheck().catch(function(error) {
                            setStatus({$error_title_js} + ' ' + error.message, true);
                            finishButton();
                        });
                    } catch (error) {
                        setStatus({$error_title_js} + ' ' + error.message, true);
                        finishButton();
                    }
                }
                return false;
            })(this);
        ";

        $button = html_writer::tag('button',
            '&#x1F501; ' . get_string('recheck_button', 'qtype_judge0'),
            array(
                'type' => 'button',
                'data-qtype-judge0-recheck' => '1',
                'title' => get_string('recheck_button', 'qtype_judge0'),
                'style' => 'padding: 8px 20px; background: #0f6cbf; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;',
                'onclick' => $onclick
            )
        );

        $status = html_writer::tag('div', '', array(
            'data-qtype-judge0-recheck-status' => '1',
            'style' => 'margin-top: 10px; text-align: left; font-size: 14px;'
        ));

        return html_writer::tag('div', $button . $status, array('style' => 'margin-top: 16px; text-align: right;'));
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
        $data = !empty($question->last_judge0_response) ? $question->last_judge0_response : [];

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

        $box = '<div class="qtype-judge0-original-results" style="margin-top: 25px; border-top: 2px solid #dee2e6; padding-top: 15px;">';
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
