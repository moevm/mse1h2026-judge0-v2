
### 1. Тесты окружения (Bash)

Проверяет, что все Docker-контейнеры запущены и сервисы отвечают.

```bash
bash tests/test_environment.sh
```

Опции:
```bash
bash tests/test_environment.sh --judge0-url http://192.168.1.100:2358 --moodle-url http://192.168.1.100
```

### 2. Тесты Judge0 API (Python)

Интеграционные тесты REST API Judge0. **Требуют запущенных контейнеров.**

```bash
python3 -m pytest tests/test_judge0_api.py -v

JUDGE0_URL=http://192.168.1.100:2358 python3 -m pytest tests/test_judge0_api.py -v

python3 tests/test_judge0_api.py
```

**Что тестируется (25+ тестов):**
- Доступность эндпоинтов (`/languages`, `/system_info`)
- Статусы: Accepted, Wrong Answer, Compilation Error, Runtime Error, TLE
- Мультиязычность: Python, C++, Java, Bash
- Асинхронный режим (`wait=false` + polling)
- Batch submissions
- Лимиты: cpu_time_limit, memory, timeout
- Сценарии плагина: checker_code, классы, множественные тест-кейсы

### 3. Acceptance-тесты плагина (Python)

**Не требуют Docker** — статический анализ кода плагина `judge0_plug/`.

```bash
python3 -m pytest tests/test_plugin_acceptance.py -v
```

**Что тестируется (35+ тестов):**

| Категория | Примеры проверок |
|-----------|-----------------|
| Структура | Все обязательные файлы Moodle-плагина |
| БД | install.xml: поля, ключи, FK |
| Логика | grade_response, вызов Judge0 API |
| Мультиязычность | language_id не захардкожен |
| Безопасность | Нет паролей в коде, обработка ошибок cURL |
| Рендеринг | Нет дублирования запросов к Judge0 |
| Async-готовность | Движение к wait=false (warnings) |
| Код | Нет отладочного вывода, MOODLE_INTERNAL |



## Зависимости

- **Python 3.8+** (стандартная библиотека, без pip install)
- **pytest** (опционально, для красивого вывода): `pip install pytest`
- **Bash 4+**, `curl`, `docker` CLI — для тестов окружения
