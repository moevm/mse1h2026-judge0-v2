"""test_plugin_acceptance.py — Тесты приёмки плагина qtype_judge0"""

import os
import re
import unittest
import xml.etree.ElementTree as ET

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
PLUGIN_DIR = os.environ.get("PLUGIN_DIR",
                             os.path.join(PROJECT_ROOT, "judge0_plug"))

def read_file(relative_path: str) -> str:
    """Прочитать файл относительно PLUGIN_DIR."""
    full_path = os.path.join(PLUGIN_DIR, relative_path)
    if not os.path.exists(full_path):
        return ""
    with open(full_path, "r", encoding="utf-8", errors="replace") as f:
        return f.read()

def file_exists(relative_path: str) -> bool:
    """Проверить существование файла в PLUGIN_DIR."""
    return os.path.exists(os.path.join(PLUGIN_DIR, relative_path))


class TestPluginStructure(unittest.TestCase):
    """Проверка наличия всех обязательных файлов плагина Moodle."""

    def test_01_plugin_dir_exists(self):
        """Директория плагина существует."""
        self.assertTrue(os.path.isdir(PLUGIN_DIR),
                        f"Директория плагина не найдена: {PLUGIN_DIR}")

    def test_02_version_php(self):
        """version.php - обязательный файл плагина Moodle."""
        self.assertTrue(file_exists("version.php"),
                        "Отсутствует version.php")

    def test_03_question_php(self):
        """question.php -логика вопроса и оценивания."""
        self.assertTrue(file_exists("question.php"),
                        "Отсутствует question.php")

    def test_04_questiontype_php(self):
        """questiontype.php - определение типа вопроса."""
        self.assertTrue(file_exists("questiontype.php"),
                        "Отсутствует questiontype.php")

    def test_05_renderer_php(self):
        """renderer.php - рендеринг UI для студента."""
        self.assertTrue(file_exists("renderer.php"),
                        "Отсутствует renderer.php")

    def test_06_edit_form_php(self):
        """edit_judge0_form.php - форма создания/редактирования задачи."""
        files = [f for f in os.listdir(PLUGIN_DIR)
                 if f.startswith("edit_") and f.endswith("_form.php")]
        self.assertGreater(len(files), 0,
                           "Отсутствует edit_*_form.php (форма задачи)")

    def test_07_install_xml(self):
        """db/install.xml - схема БД плагина."""
        self.assertTrue(file_exists(os.path.join("db", "install.xml")),
                        "Отсутствует db/install.xml")

    def test_08_lang_en(self):
        """lang/en/qtype_judge0.php — английская локализация."""
        lang_path = os.path.join("lang", "en", "qtype_judge0.php")
        self.assertTrue(file_exists(lang_path),
                        f"Отсутствует {lang_path}")

    def test_09_settings_php(self):
        """settings.php - страница настроек плагина в админке."""
        self.assertTrue(file_exists("settings.php"),
                        "Отсутствует settings.php — невозможно настроить URL Judge0 ")

    def test_10_icon(self):
        """pix/icon.svg или pix/icon.png — иконка плагина."""
        has_svg = file_exists(os.path.join("pix", "icon.svg"))
        has_png = file_exists(os.path.join("pix", "icon.png"))
        self.assertTrue(has_svg or has_png,
                        "Отсутствует pix/icon.svg или pix/icon.png")



class TestVersionFile(unittest.TestCase):
    """Проверка корректности version.php."""

    def setUp(self):
        self.content = read_file("version.php")
        self.assertTrue(len(self.content) > 0, "version.php пуст или не найден")

    def test_01_component(self):
        """version.php содержит $plugin->component = 'qtype_judge0'."""
        self.assertRegex(self.content, r"\$plugin->component\s*=\s*'qtype_judge0'",
                         "Не найден $plugin->component = 'qtype_judge0'")

    def test_02_version_set(self):
        """version.php содержит $plugin->version."""
        self.assertRegex(self.content, r"\$plugin->version\s*=\s*\d+",
                         "Не найден $plugin->version")

    def test_03_maturity_set(self):
        """version.php содержит $plugin->maturity."""
        self.assertRegex(self.content, r"\$plugin->maturity\s*=",
                         "Не найден $plugin->maturity")

    def test_04_requires_set(self):
        """version.php содержит $plugin->requires."""
        self.assertRegex(self.content, r"\$plugin->requires\s*=\s*\d+",
                         "Не найден $plugin->requires")

class TestDatabaseSchema(unittest.TestCase):
    """Проверка корректности db/install.xml."""

    def setUp(self):
        xml_path = os.path.join(PLUGIN_DIR, "db", "install.xml")
        self.assertTrue(os.path.exists(xml_path), "db/install.xml не найден")
        self.tree = ET.parse(xml_path)
        self.root = self.tree.getroot()

    def test_01_has_table(self):
        """install.xml содержит хотя бы одну таблицу."""
        tables = self.root.findall(".//TABLE")
        self.assertGreater(len(tables), 0, "Нет таблиц в install.xml")

    def test_02_has_questionid_field(self):
        """Таблица содержит поле 'questionid' (FK на question)."""
        fields = self.root.findall(".//FIELD")
        field_names = [f.get("NAME") for f in fields]
        self.assertIn("questionid", field_names,
                       "Нет поля 'questionid' — нельзя привязать опции к вопросу")

    def test_03_has_checker_code_field(self):
        """Таблица содержит поле 'checker_code'."""
        fields = self.root.findall(".//FIELD")
        field_names = [f.get("NAME") for f in fields]
        self.assertIn("checker_code", field_names,
                       "Нет поля 'checker_code'")

    def test_04_has_expected_output_field(self):
        """Таблица содержит поле 'expected_output'."""
        fields = self.root.findall(".//FIELD")
        field_names = [f.get("NAME") for f in fields]
        self.assertIn("expected_output", field_names,
                       "Нет поля 'expected_output'")

    def test_05_has_language_id_field(self):
        """Таблица содержит поле для выбора языка программирования."""
        fields = self.root.findall(".//FIELD")
        field_names = [f.get("NAME") for f in fields]
        has_lang = any(name for name in field_names
                       if "lang" in name.lower() or "language" in name.lower())
        self.assertTrue(has_lang,
                        "Нет поля для языка программирования (language_id / lang_id). "
                        "Необходимо, чтобы преподаватель мог выбирать язык для задачи.")

    def test_06_has_primary_key(self):
        """Таблица имеет первичный ключ."""
        keys = self.root.findall(".//KEY[@TYPE='primary']")
        self.assertGreater(len(keys), 0, "Нет PRIMARY KEY")

    def test_07_has_foreign_key_to_question(self):
        """Таблица имеет FK на таблицу question."""
        keys = self.root.findall(".//KEY[@TYPE='foreign']")
        has_fk = any(k is not None and k.get("REFTABLE") == "question"
                     for k in keys)
        self.assertTrue(has_fk,
                        "Нет FK на таблицу 'question'")



class TestQuestionLogic(unittest.TestCase):
    """Проверка question.php - логика оценивания решений."""

    def setUp(self):
        self.content = read_file("question.php")
        self.assertTrue(len(self.content) > 0, "question.php пуст или не найден")

    def test_01_extends_question_base(self):
        """Класс наследуется от question_graded_automatically."""
        self.assertRegex(
            self.content,
            r"class\s+\w+\s+extends\s+question_graded_automatically",
            "Класс не наследует question_graded_automatically"
        )

    def test_02_has_grade_response(self):
        """Реализован метод grade_response()."""
        self.assertIn("function grade_response",
                       self.content,
                       "Метод grade_response() не найден")

    def test_03_calls_judge0_api(self):
        """grade_response() обращается к Judge0 API (/submissions)."""
        self.assertIn("/submissions", self.content,
                       "Нет обращения к /submissions в question.php")

    def test_04_checks_status_id(self):
        """Проверяется status.id из ответа Judge0."""
        has_status_check = ("status" in self.content and
                            ("['id']" in self.content or "->id" in self.content
                             or '"id"' in self.content))
        self.assertTrue(has_status_check,
                        "Нет проверки status.id из ответа Judge0")

    def test_05_language_id_not_hardcoded(self):
        """language_id берётся из настроек вопроса."""
        hardcoded_patterns = [
            r"'language_id'\s*=>\s*\d+",        
            r'"language_id"\s*=>\s*\d+',        
            r'"language_id"\s*:\s*\d+',         
        ]
        
        hardcoded_found = False
        for pattern in hardcoded_patterns:
            if re.search(pattern, self.content):
                hardcoded_found = True
                break
        
        uses_dynamic = bool(re.search(
            r'\$this->language_id|\$question->language_id|'
            r'get_config.*language|language_id.*\$',
            self.content
        ))
        
        if hardcoded_found and not uses_dynamic:
            self.fail(
                "language_id (найдено числовое значение). "
                "Необходимо читать language_id из свойств вопроса "
                "($this->language_id) или из настроек."
            )

    def test_06_curl_error_handling(self):
        """Код обрабатывает ошибки cURL (curl_errno / curl_error)."""
        has_error_check = (
            "curl_errno" in self.content or
            "curl_error" in self.content or
            "CURLOPT_FAILONERROR" in self.content or
            "try" in self.content  
        )
        self.assertTrue(has_error_check,
                        "Нет обработки ошибок cURL (curl_errno / curl_error). "
                        "При недоступности Judge0 студент получит 0 без объяснения.")

    def test_07_configurable_server_url(self):
        """URL Judge0-сервера настраиваемый (get_config или аналог)."""
        self.assertIn("get_config", self.content,
                       "URL Judge0 не читается из конфигурации (get_config). "
                       "Невозможно изменить адрес сервера без правки кода.")

    def test_08_uses_curl(self):
        """Используется cURL для HTTP-запросов к Judge0."""
        self.assertIn("curl_init", self.content,
                       "curl_init не найден в question.php")

    def test_09_returns_proper_grade_format(self):
        """grade_response возвращает массив [оценка, состояние]."""
        has_gradedright = "gradedright" in self.content
        has_gradedwrong = "gradedwrong" in self.content
        self.assertTrue(has_gradedright and has_gradedwrong,
                        "grade_response должен возвращать gradedright / gradedwrong")



class TestRenderer(unittest.TestCase):
    """Проверка renderer.php - UI для студента."""

    def setUp(self):
        self.content = read_file("renderer.php")
        self.assertTrue(len(self.content) > 0, "renderer.php пуст или не найден")

    def test_01_extends_qtype_renderer(self):
        """Класс наследует qtype_renderer."""
        self.assertIn("extends qtype_renderer", self.content,
                       "Класс не наследует qtype_renderer")

    def test_02_has_formulation_and_controls(self):
        """Реализован метод formulation_and_controls()."""
        self.assertIn("function formulation_and_controls", self.content,
                       "Метод formulation_and_controls не найден")

    def test_03_has_textarea_for_code(self):
        """Студенту предоставляется textarea для ввода кода."""
        self.assertIn("textarea", self.content,
                       "Нет textarea для ввода кода студентом")

    def test_04_monospace_font(self):
        """Textarea использует моноширинный шрифт (для кода)."""
        has_mono = ("monospace" in self.content.lower() or
                    "Courier" in self.content or
                    "courier" in self.content.lower() or
                    "ace.edit" in self.content or        
                    "monaco" in self.content.lower())   
        self.assertTrue(has_mono,
                        "Textarea не использует моноширинный шрифт")

    def test_05_no_duplicate_judge0_request(self):
        """renderer.php не отправляет отдельный запрос к Judge0."""
        has_curl = "curl_init" in self.content
        has_submissions = "/submissions" in self.content
        
        if has_curl and has_submissions:
            self.fail(
                "КРИТИЧЕСКОЕ: renderer.php ПОВТОРНО отправляет запрос к Judge0 API! "
                "Это дублирование нагрузки. Результат должен сохраняться в "
                "question_attempt_step_data при оценивании и повторно "
                "использоваться при рендеринге."
            )

    def test_06_shows_success_message(self):
        """При правильном ответе показывается сообщение об успехе."""
        has_success = ("gradedright" in self.content or
                       "success" in self.content.lower() or
                       "Поздравляем" in self.content or
                       "correct" in self.content.lower())
        self.assertTrue(has_success,
                        "Нет отображения успеха при правильном ответе")

    def test_07_shows_error_details(self):
        """При неправильном ответе показываются детали ошибки."""
        has_error_output = ("stderr" in self.content or
                            "stdout" in self.content or
                            "ошибк" in self.content.lower() or
                            "error" in self.content.lower())
        self.assertTrue(has_error_output,
                        "Нет отображения деталей ошибки при неправильном ответе")



class TestEditForm(unittest.TestCase):
    """Проверка формы создания/редактирования задачи."""

    def setUp(self):
        form_files = [f for f in os.listdir(PLUGIN_DIR)
                      if f.startswith("edit_") and f.endswith("_form.php")]
        self.assertTrue(len(form_files) > 0, "Файл формы не найден")
        self.content = read_file(form_files[0])

    def test_01_extends_question_edit_form(self):
        """Форма наследует question_edit_form."""
        self.assertIn("extends question_edit_form", self.content,
                       "Форма не наследует question_edit_form")

    def test_02_has_checker_code_field(self):
        """Форма содержит поле 'checker_code'."""
        self.assertIn("checker_code", self.content,
                       "Поле 'checker_code' не найдено в форме")

    def test_03_has_expected_output_field(self):
        """Форма содержит поле 'expected_output'."""
        self.assertIn("expected_output", self.content,
                       "Поле 'expected_output' не найдено в форме")

    def test_04_has_language_selector(self):
        """Форма содержит механизм выбора языка программирования."""
        has_lang = ("language" in self.content.lower() or
                    "lang_id" in self.content or
                    "language_id" in self.content)
        self.assertTrue(has_lang,
                        "Форма не содержит выбора языка программирования. "
                        "Необходимо добавить dropdown/select для language_id.")

    def test_05_has_qtype_method(self):
        """Форма определяет метод qtype() → 'judge0'."""
        self.assertIn("function qtype", self.content,
                       "Метод qtype() не найден")
        self.assertIn("'judge0'", self.content,
                       "qtype() не возвращает 'judge0'")

    def test_06_repeat_testcase_fields_have_types(self):
        """repeat_elements test case fields must declare types via repeatoptions."""
        for field, expected_type in {
            "test_input": "PARAM_RAW",
            "test_expected_output": "PARAM_RAW",
            "is_hidden": "PARAM_BOOL",
            "weight": "PARAM_FLOAT",
        }.items():
            self.assertIn(
                f"$repeatoptions['{field}']['type'] = {expected_type};",
                self.content,
                f"Repeated field '{field}' must set type {expected_type} "
                "through repeatoptions, otherwise Moodle drops it on submit."
            )


class TestLocalization(unittest.TestCase):
    """Проверка наличия локализаций."""

    def test_01_english_lang_file(self):
        """Английская локализация существует и содержит pluginname."""
        content = read_file(os.path.join("lang", "en", "qtype_judge0.php"))
        self.assertGreater(len(content), 0,
                           "Файл en/qtype_judge0.php пуст или не найден")
        self.assertIn("pluginname", content,
                       "Строка 'pluginname' не найдена в EN-локализации")

    def test_02_russian_lang_file(self):
        """Русская локализация существует."""
        ru_path = os.path.join("lang", "ru", "qtype_judge0.php")
        self.assertTrue(file_exists(ru_path),
                        "Отсутствует lang/ru/qtype_judge0.php. "
                        "Moodle настроен на русский — плагин будет "
                        "отображаться на английском.")

class TestAsyncRequirements(unittest.TestCase):
    """Проверка реализации асинхронной обработки (целевая архитектура)."""

    def test_01_no_wait_true_in_grading(self):
        """question.php НЕ использует wait=true при оценивании."""
        content = read_file("question.php")
        if "wait=true" in content:
            import warnings
            warnings.warn(
                "question.php использует wait=true (синхронный режим). "
                "Целевая архитектура: wait=false + polling/callback. "
                "Допустимо для MVP, но требует замены.",
                UserWarning
            )

    def test_02_callback_or_polling_support(self):
        """Код содержит механизм callback или polling."""
        content = read_file("question.php")
        has_async = ("wait=false" in content or
                     "callback" in content.lower() or
                     "poll" in content.lower() or
                     "token" in content.lower() or
                     "async" in content.lower() or
                     "queue" in content.lower())
        
        if not has_async:
            import warnings
            warnings.warn(
                "Нет признаков асинхронной обработки (wait=false, callback, "
                "polling, token). На текущем этапе допустимо, но это "
                "ключевое требование архитектуры.",
                UserWarning
            )


class TestSecurityFixes(unittest.TestCase):
    """Проверки исправлений из строгого security review."""

    def setUp(self):
        self.question = read_file("question.php")
        self.renderer = read_file("renderer.php")
        self.settings = read_file("settings.php")

    def test_01_language_whitelist_enforced_server_side(self):
        """language_id проверяется на сервере, а не только в UI."""
        self.assertIn("function resolve_language_id", self.question)
        self.assertIn("invalid_language", self.question)
        self.assertIn("in_array($requested, $allowed, true)", self.question)
        self.assertNotIn("in_array($student_lang, $this->allowed_languages)", self.question)

    def test_02_renderer_normalizes_allowed_language(self):
        """Hidden language_id синхронизируется с allowed_languages."""
        self.assertIn("$allowed_langs[0]", self.renderer)
        self.assertIn("!in_array($current_lang_id, $allowed_langs, true)", self.renderer)
        self.assertIn("'value' => $current_lang_id", self.renderer)

    def test_03_judge0_auth_header_supported(self):
        """Плагин умеет отправлять auth-заголовок в private Judge0."""
        self.assertIn("auth_header", self.settings)
        self.assertIn("auth_token", self.settings)
        self.assertIn("function get_judge0_headers", self.question)
        self.assertIn("$auth_header . ': ' . $auth_token", self.question)
        self.assertIn("CURLOPT_HTTPHEADER", self.question)

    def test_04_no_hardcoded_monaco_cdn(self):
        """Renderer не грузит Monaco с зашитого CDN."""
        self.assertNotIn("cdnjs.cloudflare.com", self.renderer)
        self.assertIn("monaco_base_url", self.renderer)
        self.assertIn("srcdoc", self.renderer)
        self.assertIn("getWorkerUrl", self.renderer)

    def test_05_reference_and_generator_fail_fast(self):
        """Сбой generator/reference не превращается в expected_output."""
        self.assertIn("generator_failed", self.question)
        self.assertIn("reference_failed", self.question)
        self.assertIn("fail_grading", self.question)
        self.assertNotIn("ОШИБКА ИДЕАЛЬНОГО РЕШЕНИЯ", self.question)

    def test_06_poll_timeout_configurable_and_bounded(self):
        """Polling timeout настраиваемый и ограниченный сверху."""
        self.assertIn("poll_timeout", self.question)
        self.assertIn("min($poll_timeout, 120)", self.question)
        self.assertIn("max(5", self.question)

    def test_07_debug_table_shows_compile_and_runtime_details(self):
        """Renderer показывает compile_output/stderr/message, а не только stdout."""
        self.assertIn("compile_output", self.renderer)
        self.assertIn("[COMPILE]", self.renderer)
        self.assertIn("[STDERR]", self.renderer)
        self.assertIn("[MESSAGE]", self.renderer)

    def test_08_testcase_textarea_array_values_supported(self):
        """Сохранение тестов поддерживает Moodle textarea массивы с ключом text."""
        questiontype = read_file("questiontype.php")
        self.assertIn("is_array($input)", questiontype)
        self.assertIn("$input['text']", questiontype)
        self.assertIn("is_array($expected)", questiontype)
        self.assertIn("optional_param_array('test_input'", questiontype)
        self.assertIn("optional_param_array('test_expected_output'", questiontype)

    def test_09_parent_save_null_does_not_skip_testcases(self):
        """parent::save_question_options() возвращает null при успехе в Moodle."""
        questiontype = read_file("questiontype.php")
        self.assertIn("parent::save_question_options($formdata)", questiontype)
        self.assertIn("$result instanceof stdClass", questiontype)
        self.assertNotIn("$result !== true", questiontype)


class TestCodeQuality(unittest.TestCase):
    """Общие проверки качества кода плагина."""

    def test_01_no_debug_output(self):
        """Нет отладочных var_dump / print_r / echo в production-коде."""
        for filename in ["question.php", "questiontype.php", "renderer.php"]:
            content = read_file(filename)
            if not content:
                continue
            debug_patterns = [
                (r'^\s*var_dump\s*\(', "var_dump"),
                (r'^\s*print_r\s*\(', "print_r"),
                (r'^\s*echo\s+["\']debug', "echo debug"),
                (r'^\s*error_log\s*\(["\']DEBUG', "error_log DEBUG"),
            ]
            for pattern, name in debug_patterns:
                matches = re.findall(pattern, content, re.MULTILINE)
                self.assertEqual(len(matches), 0,
                                 f"Отладочный вывод '{name}' найден в {filename}")

    def test_02_moodle_internal_check(self):
        """Все PHP-файлы содержат проверку MOODLE_INTERNAL."""
        php_files = [f for f in os.listdir(PLUGIN_DIR) if f.endswith(".php")]
        for filename in php_files:
            content = read_file(filename)
            self.assertIn("MOODLE_INTERNAL", content,
                          f"{filename}: нет проверки defined('MOODLE_INTERNAL')")

    def test_03_no_hardcoded_passwords(self):
        """Нет захардкоженных паролей/токенов в PHP-файлах."""
        php_files = [f for f in os.listdir(PLUGIN_DIR) if f.endswith(".php")]
        for filename in php_files:
            content = read_file(filename)
            suspicious = re.findall(
                r'(?:password|passwd|secret|token|api_key)\s*=\s*["\'][^"\']+["\']',
                content, re.IGNORECASE
            )
            self.assertEqual(len(suspicious), 0,
                             f"{filename}: возможно захардкоженные секреты: {suspicious}")

    def test_04_reasonable_timeout(self):
        """Таймаут cURL разумный (не менее 10 секунд для оценивания)."""
        content = read_file("question.php")
        timeouts = re.findall(r'CURLOPT_TIMEOUT\s*,\s*(\d+)', content)
        if timeouts:
            max_timeout = max(int(t) for t in timeouts)
            self.assertGreaterEqual(max_timeout, 10,
                                   f"Таймаут cURL слишком мал: {max_timeout}с. "
                                   f"Для сложных задач может не хватить.")

    def test_05_question_php_total_lines(self):
        """question.php не слишком маленький (содержит достаточно логики)."""
        content = read_file("question.php")
        lines = content.strip().split("\n")
        self.assertGreater(len(lines), 20,
                           f"question.php слишком мал ({len(lines)} строк) — "
                           f"возможно, недостаточно логики")

if __name__ == "__main__":
    print(f"\n{'='*60}")
    print(f"  Plugin Acceptance Tests (qtype_judge0)")
    print(f"  Plugin dir: {PLUGIN_DIR}")
    print(f"{'='*60}\n")
    unittest.main(verbosity=2)
