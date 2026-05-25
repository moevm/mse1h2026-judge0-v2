"""test_judge0_api.py — Интеграционные тесты Judge0 REST API"""

import os
import json
import time
import unittest
import urllib.request
import urllib.error
import urllib.parse

JUDGE0_URL = os.environ.get("JUDGE0_URL", "http://localhost:2358")

def judge0_get(path: str, timeout: int = 10) -> dict:
    """GET-запрос к Judge0 API."""
    url = f"{JUDGE0_URL}{path}"
    req = urllib.request.Request(url, headers={"Accept": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode())

def judge0_post(path: str, data: dict, timeout: int = 30) -> tuple[dict, int]:
    """POST-запрос к Judge0 API. Возвращает (body, http_code)."""
    url = f"{JUDGE0_URL}{path}"
    payload = json.dumps(data).encode("utf-8")
    req = urllib.request.Request(url, data=payload, method="POST")
    req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = json.loads(resp.read().decode())
            return body, resp.status
    except urllib.error.HTTPError as e:
        body = json.loads(e.read().decode()) if e.fp else {}
        return body, e.code

def create_submission(source_code: str, language_id: int = 71,
                      expected_output: str = None, stdin: str = None,
                      wait: bool = True, cpu_time_limit: float = None,
                      memory_limit: int = None) -> dict:
    """Создаёт submission в Judge0 и возвращает результат."""
    params = "base64_encoded=false"
    if wait:
        params += "&wait=true"
    
    payload = {
        "source_code": source_code,
        "language_id": language_id,
    }
    if expected_output is not None:
        payload["expected_output"] = expected_output
    if stdin is not None:
        payload["stdin"] = stdin
    if cpu_time_limit is not None:
        payload["cpu_time_limit"] = cpu_time_limit
    if memory_limit is not None:
        payload["memory_limit"] = memory_limit
    
    body, code = judge0_post(f"/submissions?{params}", payload)
    return body

def poll_submission(token: str, max_wait: int = 15, interval: float = 1.0) -> dict:
    """Polling результата submission по token."""
    for _ in range(int(max_wait / interval)):
        result = judge0_get(f"/submissions/{token}?base64_encoded=false")
        status_id = result.get("status", {}).get("id", 0)
        if status_id > 2:
            return result
        time.sleep(interval)
    return result



class TestJudge0Endpoints(unittest.TestCase):
    """Тесты базовой доступности Judge0 API."""

    def test_01_languages_endpoint(self):
        """GET /languages возвращает непустой список языков."""
        result = judge0_get("/languages")
        self.assertIsInstance(result, list)
        self.assertGreater(len(result), 0, "Список языков пуст")

    def test_02_python3_available(self):
        """Python 3 (language_id=71) доступен в Judge0."""
        languages = judge0_get("/languages")
        ids = [lang["id"] for lang in languages]
        self.assertIn(71, ids, "Python 3.8.1 (id=71) отсутствует")

    def test_03_cpp_available(self):
        """C++ (language_id=54) доступен в Judge0."""
        languages = judge0_get("/languages")
        ids = [lang["id"] for lang in languages]
        self.assertIn(54, ids, "C++ (id=54) отсутствует")

    def test_04_system_info(self):
        """GET /system_info доступен (или возвращает 401 если auth)."""
        try:
            result = judge0_get("/system_info")
            self.assertIsInstance(result, dict)
        except urllib.error.HTTPError as e:
            self.assertIn(e.code, [401, 403],
                          f"Неожиданный HTTP код: {e.code}")


class TestSubmissionStatuses(unittest.TestCase):
    """Тесты различных статусов выполнения кода в Judge0."""

    def test_01_accepted(self):
        """Корректная программа: print('hello') → status.id == 3 (Accepted)."""
        result = create_submission(
            source_code='print("hello")',
            language_id=71,
            expected_output="hello\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Ожидался Accepted (3), получен: {status_id} — "
                         f"{result.get('status', {}).get('description', '?')}")

    def test_02_wrong_answer(self):
        """Неверный вывод: print('foo') при expected 'bar' → status.id == 4."""
        result = create_submission(
            source_code='print("foo")',
            language_id=71,
            expected_output="bar\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 4,
                         f"Ожидался Wrong Answer (4), получен: {status_id}")

    def test_03_compilation_error(self):
        """Ошибка компиляции C++: невалидный код → status.id == 6."""
        result = create_submission(
            source_code='this is not valid c++ code!!!',
            language_id=54 
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 6,
                         f"Ожидался Compilation Error (6), получен: {status_id}")

    def test_04_runtime_error(self):
        """Runtime error: деление на ноль → status.id in [7..12]."""
        result = create_submission(
            source_code='x = 1 / 0',
            language_id=71
        )
        status_id = result.get("status", {}).get("id")
        self.assertIn(status_id, list(range(7, 13)),
                      f"Ожидался Runtime Error (7-12), получен: {status_id}")

    def test_05_time_limit_exceeded(self):
        """Бесконечный цикл с cpu_time_limit=1 → status.id == 5 (TLE)."""
        result = create_submission(
            source_code='while True: pass',
            language_id=71,
            cpu_time_limit=1
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 5,
                         f"Ожидался Time Limit Exceeded (5), получен: {status_id}")

    def test_06_stdin_processing(self):
        """Проверка работы stdin: input() → stdout."""
        result = create_submission(
            source_code='name = input()\nprint(f"Hello, {name}!")',
            language_id=71,
            stdin="World",
            expected_output="Hello, World!\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Ожидался Accepted (3), получен: {status_id} — "
                         f"stdout: {result.get('stdout', '?')}")



class TestMultiLanguage(unittest.TestCase):
    """Тесты выполнения кода на разных языках."""

    def test_01_cpp_hello_world(self):
        """C++ Hello World компилируется и выполняется."""
        code = """#include <iostream>"""
        result = create_submission(
            source_code=code,
            language_id=54,  # C++ (GCC 9.2.0)
            expected_output="hello from cpp\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"C++ Accepted ожидался, получен: {status_id}")

    def test_02_java_hello_world(self):
        """Java Hello World компилируется и выполняется."""
        code = """public class Main {"""
        result = create_submission(
            source_code=code,
            language_id=62,  # Java (OpenJDK 13.0.1)
            expected_output="hello from java\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Java Accepted ожидался, получен: {status_id}")

    def test_03_bash_hello_world(self):
        """Bash скрипт выполняется."""
        result = create_submission(
            source_code='echo "hello from bash"',
            language_id=46,  # Bash (5.0.0)
            expected_output="hello from bash\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Bash Accepted ожидался, получен: {status_id}")



class TestAsyncSubmission(unittest.TestCase):
    """Тесты асинхронного режима (wait=false + polling)."""

    def test_01_async_creates_token(self):
        """При wait=false возвращается token для polling."""
        result = create_submission(
            source_code='print("async")',
            language_id=71,
            wait=False
        )
        self.assertIn("token", result,
                       "Ответ не содержит 'token'")
        self.assertIsNotNone(result["token"])
        self.assertGreater(len(result["token"]), 0)

    def test_02_async_poll_result(self):
        """Async submission: token → polling → получение результата."""
        result = create_submission(
            source_code='print("async_result")',
            language_id=71,
            expected_output="async_result\n",
            wait=False
        )
        token = result.get("token")
        self.assertIsNotNone(token, "Token не получен")
        
        final = poll_submission(token, max_wait=15)
        status_id = final.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Async result: ожидался Accepted (3), получен: {status_id}")

    def test_03_async_multiple_submissions(self):
        """Несколько async submissions обрабатываются параллельно."""
        tokens = []
        for i in range(3):
            result = create_submission(
                source_code=f'print({i})',
                language_id=71,
                expected_output=f"{i}\n",
                wait=False
            )
            tokens.append(result.get("token"))
        
        self.assertEqual(len(tokens), 3)
        
        for i, token in enumerate(tokens):
            self.assertIsNotNone(token, f"Token #{i} не получен")
            final = poll_submission(token, max_wait=20)
            status_id = final.get("status", {}).get("id")
            self.assertEqual(status_id, 3,
                             f"Submission #{i}: ожидался Accepted, получен: {status_id}")


class TestBatchedSubmissions(unittest.TestCase):
    """Тесты batch-режима (несколько submissions одним запросом)."""

    def test_01_batch_create(self):
        """POST /submissions/batch создаёт несколько submissions."""
        submissions = [
            {"source_code": 'print("batch_0")', "language_id": 71,
             "expected_output": "batch_0\n"},
            {"source_code": 'print("batch_1")', "language_id": 71,
             "expected_output": "batch_1\n"},
            {"source_code": 'print("batch_2")', "language_id": 71,
             "expected_output": "batch_2\n"},
        ]
        
        body, code = judge0_post(
            "/submissions/batch?base64_encoded=false",
            {"submissions": submissions}
        )
        
        self.assertIn(code, [200, 201],
                       f"Batch create: ожидался 200/201, получен: {code}")
        
        self.assertIsInstance(body, list,
                              f"Ответ batch должен быть массивом, получен: {type(body)}")
        self.assertEqual(len(body), 3,
                         f"Ожидалось 3 токена, получено: {len(body)}")
        
        for item in body:
            self.assertIn("token", item, "Элемент batch без token")

    def test_02_batch_results(self):
        """Batch submissions: все задачи выполняются корректно."""
        submissions = [
            {"source_code": 'print("r0")', "language_id": 71,
             "expected_output": "r0\n"},
            {"source_code": 'print("r1")', "language_id": 71,
             "expected_output": "r1\n"},
        ]
        
        body, code = judge0_post(
            "/submissions/batch?base64_encoded=false",
            {"submissions": submissions}
        )
        
        if code not in [200, 201]:
            self.skipTest(f"Batch create вернул HTTP {code}")
        
        tokens = [item["token"] for item in body]
        
        time.sleep(5)
        
        tokens_str = ",".join(tokens)
        results = judge0_get(
            f"/submissions/batch?tokens={tokens_str}&base64_encoded=false"
        )
        
        self.assertIn("submissions", results)
        for i, sub in enumerate(results["submissions"]):
            status_id = sub.get("status", {}).get("id", 0)
            self.assertEqual(status_id, 3,
                             f"Batch #{i}: ожидался Accepted, получен: {status_id}")


class TestLimitsAndConstraints(unittest.TestCase):
    """Тесты лимитов времени и памяти."""

    def test_01_custom_time_limit_accepted(self):
        """Программа укладывается в кастомный CPU time limit."""
        result = create_submission(
            source_code='import time; time.sleep(0.5); print("ok")',
            language_id=71,
            expected_output="ok\n",
            cpu_time_limit=3
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Ожидался Accepted, получен: {status_id}")

    def test_02_custom_time_limit_exceeded(self):
        """Программа НЕ укладывается в кастомный CPU time limit."""
        result = create_submission(
            source_code='while True: pass',
            language_id=71,
            cpu_time_limit=1
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 5,
                         f"Ожидался TLE (5), получен: {status_id}")

    def test_03_response_contains_time_and_memory(self):
        """Ответ Judge0 содержит время выполнения и использованную память."""
        result = create_submission(
            source_code='print("metrics")',
            language_id=71,
            expected_output="metrics\n"
        )
        self.assertIn("time", result,
                       "Ответ не содержит поле 'time'")
        self.assertIn("memory", result,
                       "Ответ не содержит поле 'memory'")

    def test_04_stderr_returned_on_error(self):
        """При ошибке выполнения возвращается stderr."""
        result = create_submission(
            source_code='import sys; print("error!", file=sys.stderr); raise ValueError("test")',
            language_id=71
        )
        stderr = result.get("stderr", "")
        self.assertTrue(
            stderr is not None and len(str(stderr)) > 0,
            "stderr пуст при ошибке выполнения"
        )


class TestPluginScenarios(unittest.TestCase):
    """Тесты, имитирующие работу плагина qtype_judge0 — конкатенация"""

    def test_01_checker_code_pattern(self):
        """Шаблон плагина: код студента + checker_code + expected_output."""
        student_code = 'def add(a, b):\n    return a + b\n'
        checker_code = 'print(add(2, 3))'
        
        full_code = student_code + "\n" + checker_code
        
        result = create_submission(
            source_code=full_code,
            language_id=71,
            expected_output="5\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Checker code pattern: ожидался Accepted, получен: {status_id}")

    def test_02_checker_code_wrong_student(self):
        """Студент реализовал функцию неправильно → Wrong Answer."""
        student_code = 'def add(a, b):\n    return a * b\n' 
        checker_code = 'print(add(2, 3))'
        
        full_code = student_code + "\n" + checker_code
        
        result = create_submission(
            source_code=full_code,
            language_id=71,
            expected_output="5\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 4,
                         f"Wrong student code: ожидался Wrong Answer (4), получен: {status_id}")

    def test_03_checker_code_class_pattern(self):
        """Студент реализует класс, checker вызывает его методы."""
        student_code = '''
class Calculator:
    def __init__(self):
        self.history = []
    
    def add(self, a, b):
        result = a + b
        self.history.append(result)
        return result
    
    def get_history(self):
        return self.history
'''
        checker_code = '''
calc = Calculator()
print(calc.add(1, 2))
print(calc.add(10, 20))
print(calc.get_history())
'''
        full_code = student_code + "\n" + checker_code
        
        result = create_submission(
            source_code=full_code,
            language_id=71,
            expected_output="3\n30\n[3, 30]\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Class pattern: ожидался Accepted, получен: {status_id}")

    def test_04_multiple_test_cases_simulation(self):
        """Имитация множественных тест-кейсов через один checker_code."""
        student_code = 'def factorial(n):\n    return 1 if n <= 1 else n * factorial(n-1)\n'
        checker_code = '''
tests = [(0, 1), (1, 1), (5, 120), (10, 3628800)]
all_passed = True
for n, expected in tests:
    result = factorial(n)
    if result != expected:
        print(f"FAIL: factorial({n}) = {result}, expected {expected}")
        all_passed = False
if all_passed:
    print("ALL TESTS PASSED")
'''
        full_code = student_code + "\n" + checker_code
        
        result = create_submission(
            source_code=full_code,
            language_id=71,
            expected_output="ALL TESTS PASSED\n"
        )
        status_id = result.get("status", {}).get("id")
        self.assertEqual(status_id, 3,
                         f"Multiple tests: ожидался Accepted, получен: {status_id}")

if __name__ == "__main__":
    print(f"\n{'='*60}")
    print(f"  Judge0 API Integration Tests")
    print(f"  URL: {JUDGE0_URL}")
    print(f"{'='*60}\n")
    unittest.main(verbosity=2)
