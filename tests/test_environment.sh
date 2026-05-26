#!/bin/bash
###############################################################################
#
#
#
###############################################################################

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color
BOLD='\033[1m'

JUDGE0_URL="${JUDGE0_URL:-http://localhost:2358}"
MOODLE_URL="${MOODLE_URL:-http://localhost}"

while [[ $# -gt 0 ]]; do
    case $1 in
        --judge0-url) JUDGE0_URL="$2"; shift 2;;
        --moodle-url) MOODLE_URL="$2"; shift 2;;
        *) echo "Unknown option: $1"; exit 1;;
    esac
done

TOTAL=0
PASSED=0
FAILED=0
SKIPPED=0

pass() {
    TOTAL=$((TOTAL + 1))
    PASSED=$((PASSED + 1))
    echo -e "  ${GREEN}✓ PASS${NC} $1"
}

fail() {
    TOTAL=$((TOTAL + 1))
    FAILED=$((FAILED + 1))
    echo -e "  ${RED}✗ FAIL${NC} $1"
    if [ -n "${2:-}" ]; then
        echo -e "         ${RED}→ $2${NC}"
    fi
}

skip() {
    TOTAL=$((TOTAL + 1))
    SKIPPED=$((SKIPPED + 1))
    echo -e "  ${YELLOW}⊘ SKIP${NC} $1 — $2"
}

header() {
    echo ""
    echo -e "${BOLD}${BLUE}═══ $1 ═══${NC}"
}
# ═════════════════════════════════════════════════════════════════════════════
# ═════════════════════════════════════════════════════════════════════════════

header "1. Проверка Docker-контейнеров"

if ! command -v docker &>/dev/null; then
    fail "Docker CLI не установлен"
    echo -e "${RED}Невозможно продолжить без Docker. Выход.${NC}"
    exit 1
fi

EXPECTED_SERVICES=(
    "judge0/judge0"         # server + worker
    "postgres"              # db (Judge0)
    "redis"                 # redis (Judge0)
    "mariadb"               # mariadb (Moodle)
    "moodle"                # moodle
)

for svc in "${EXPECTED_SERVICES[@]}"; do
    count=$(docker ps --filter "ancestor=$svc" --format '{{.Names}}' 2>/dev/null | wc -l | tr -d ' ')
    if [ "$count" -gt 0 ]; then
        names=$(docker ps --filter "ancestor=$svc" --format '{{.Names}}' 2>/dev/null | tr '\n' ', ' | sed 's/,$//')
        pass "Контейнер(ы) на базе '$svc' запущен(ы): $names"
    else
        count2=$(docker ps --format '{{.Names}}' 2>/dev/null | grep -ic "${svc##*/}" || true)
        if [ "$count2" -gt 0 ]; then
            names2=$(docker ps --format '{{.Names}}' 2>/dev/null | grep -i "${svc##*/}" | tr '\n' ', ' | sed 's/,$//')
            pass "Контейнер(ы) '$svc' найден(ы) по имени: $names2"
        else
            fail "Контейнер на базе '$svc' НЕ запущен" "Запустите: docker compose up -d"
        fi
    fi
done

worker_count=$(docker ps --format '{{.Command}}' 2>/dev/null | grep -c "workers" || true)
if [ "$worker_count" -gt 0 ]; then
    pass "Judge0 Worker запущен (найден контейнер с командой 'workers')"
else
    skip "Judge0 Worker" "Не удалось определить - проверьте вручную"
fi

# ═════════════════════════════════════════════════════════════════════════════
# ═════════════════════════════════════════════════════════════════════════════

header "2. Проверка сетевой доступности сервисов"

check_port() {
    local host=$1
    local port=$2
    local name=$3
    if command -v nc &>/dev/null; then
        if nc -z -w 3 "$host" "$port" 2>/dev/null; then
            pass "$name доступен на $host:$port"
        else
            fail "$name НЕ доступен на $host:$port"
        fi
    elif command -v curl &>/dev/null; then
        if curl -s --connect-timeout 3 "http://$host:$port" >/dev/null 2>&1; then
            pass "$name доступен на $host:$port"
        else
            fail "$name НЕ доступен на $host:$port"
        fi
    else
        skip "$name" "Нет утилит nc или curl для проверки порта"
    fi
}

check_port "localhost" 2358 "Judge0 API"
check_port "localhost" 80   "Moodle"

# ═════════════════════════════════════════════════════════════════════════════
# ═════════════════════════════════════════════════════════════════════════════

header "3. Проверка Judge0 REST API"

echo -e "  ${BLUE}→ GET ${JUDGE0_URL}/languages${NC}"
LANG_RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 --max-time 10 "${JUDGE0_URL}/languages" 2>/dev/null || echo -e "\n000")
LANG_HTTP_CODE=$(echo "$LANG_RESPONSE" | tail -1)
LANG_BODY=$(echo "$LANG_RESPONSE" | sed '$d')

if [ "$LANG_HTTP_CODE" = "200" ]; then
    pass "GET /languages - HTTP 200 OK"
    
    if echo "$LANG_BODY" | grep -q '"id":71'; then
        pass "Python 3.8.1 (id: 71) присутствует в списке языков"
    else
        fail "Python 3 (id: 71) НЕ найден в /languages"
    fi
    
    lang_count=$(echo "$LANG_BODY" | grep -o '"id":' | wc -l | tr -d ' ')
    if [ "$lang_count" -gt 0 ]; then
        pass "Найдено языков: $lang_count"
    else
        fail "Список языков пуст"
    fi
else
    fail "GET /languages — HTTP $LANG_HTTP_CODE (ожидался 200)" "Judge0 API недоступен"
fi

echo ""
echo -e "  ${BLUE}→ GET ${JUDGE0_URL}/system_info${NC}"
SYSINFO_RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 --max-time 10 "${JUDGE0_URL}/system_info" 2>/dev/null || echo -e "\n000")
SYSINFO_HTTP_CODE=$(echo "$SYSINFO_RESPONSE" | tail -1)

if [ "$SYSINFO_HTTP_CODE" = "200" ]; then
    pass "GET /system_info — HTTP 200 OK"
else
    if [ "$SYSINFO_HTTP_CODE" = "401" ] || [ "$SYSINFO_HTTP_CODE" = "403" ]; then
        skip "GET /system_info" "Требуется авторизация (HTTP $SYSINFO_HTTP_CODE)"
    else
        fail "GET /system_info — HTTP $SYSINFO_HTTP_CODE"
    fi
fi

echo ""
echo -e "  ${BLUE}→ POST ${JUDGE0_URL}/submissions (тест: Python print)${NC}"
SUBMIT_RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 --max-time 30 \
    -X POST "${JUDGE0_URL}/submissions?base64_encoded=false&wait=true" \
    -H "Content-Type: application/json" \
    -d '{"source_code":"print(\"hello\")","language_id":71,"expected_output":"hello\n"}' \
    2>/dev/null || echo -e "\n000")
SUBMIT_HTTP_CODE=$(echo "$SUBMIT_RESPONSE" | tail -1)
SUBMIT_BODY=$(echo "$SUBMIT_RESPONSE" | sed '$d')

if [ "$SUBMIT_HTTP_CODE" = "200" ] || [ "$SUBMIT_HTTP_CODE" = "201" ]; then
    STATUS_ID=$(echo "$SUBMIT_BODY" | grep -o '"id":[0-9]*' | head -2 | tail -1 | cut -d: -f2)
    if command -v python3 &>/dev/null; then
        STATUS_ID=$(python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('status',{}).get('id','?'))" <<< "$SUBMIT_BODY" 2>/dev/null || echo "?")
    fi
    
    if [ "$STATUS_ID" = "3" ]; then
        pass "Submission Python print('hello') — Accepted (status.id=3)"
    else
        fail "Submission Python — неожиданный status.id=$STATUS_ID (ожидался 3)" "$SUBMIT_BODY"
    fi
else
    fail "POST /submissions — HTTP $SUBMIT_HTTP_CODE" "Judge0 не обработал submission"
fi

echo ""
echo -e "  ${BLUE}→ POST ${JUDGE0_URL}/submissions (async, wait=false)${NC}"
ASYNC_RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 5 --max-time 10 \
    -X POST "${JUDGE0_URL}/submissions?base64_encoded=false&wait=false" \
    -H "Content-Type: application/json" \
    -d '{"source_code":"print(42)","language_id":71}' \
    2>/dev/null || echo -e "\n000")
ASYNC_HTTP_CODE=$(echo "$ASYNC_RESPONSE" | tail -1)
ASYNC_BODY=$(echo "$ASYNC_RESPONSE" | sed '$d')

if [ "$ASYNC_HTTP_CODE" = "200" ] || [ "$ASYNC_HTTP_CODE" = "201" ]; then
    TOKEN=""
    if command -v python3 &>/dev/null; then
        TOKEN=$(python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('token',''))" <<< "$ASYNC_BODY" 2>/dev/null || echo "")
    fi
    
    if [ -n "$TOKEN" ] && [ "$TOKEN" != "" ]; then
        pass "Async submission создан, получен token: ${TOKEN:0:16}..."
        
        sleep 3
        POLL_RESPONSE=$(curl -s --connect-timeout 5 --max-time 10 \
            "${JUDGE0_URL}/submissions/${TOKEN}?base64_encoded=false" 2>/dev/null || echo "{}")
        
        if command -v python3 &>/dev/null; then
            POLL_STATUS=$(python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(d.get('status',{}).get('id','?'))" <<< "$POLL_RESPONSE" 2>/dev/null || echo "?")
        fi
        
        if [ "${POLL_STATUS:-?}" = "3" ] || [ "${POLL_STATUS:-?}" -ge 1 ] 2>/dev/null; then
            pass "Async polling - результат получен (status.id=$POLL_STATUS)"
        else
            fail "Async polling - не удалось получить результат" "$POLL_RESPONSE"
        fi
    else
        fail "Async submission - token не получен" "$ASYNC_BODY"
    fi
else
    fail "Async submission - HTTP $ASYNC_HTTP_CODE"
fi

# ═════════════════════════════════════════════════════════════════════════════
# ═════════════════════════════════════════════════════════════════════════════

header "4. Проверка доступности Moodle"

echo -e "  ${BLUE} GET ${MOODLE_URL}${NC}"
MOODLE_RESPONSE=$(curl -s -w "\n%{http_code}" --connect-timeout 10 --max-time 20 -L "${MOODLE_URL}" 2>/dev/null || echo -e "\n000")
MOODLE_HTTP_CODE=$(echo "$MOODLE_RESPONSE" | tail -1)
MOODLE_BODY=$(echo "$MOODLE_RESPONSE" | sed '$d')

if [ "$MOODLE_HTTP_CODE" = "200" ]; then
    pass "Moodle - HTTP 200 OK"
    
    if echo "$MOODLE_BODY" | grep -qi "moodle"; then
        pass "Moodle - страница содержит 'Moodle'"
    else
        fail "Moodle - страница не содержит 'Moodle'" "Возможно, это не Moodle"
    fi
else
    fail "Moodle - HTTP $MOODLE_HTTP_CODE (ожидался 200)" "Moodle недоступен на ${MOODLE_URL}"
fi

# ═════════════════════════════════════════════════════════════════════════════
# ═════════════════════════════════════════════════════════════════════════════

echo ""
echo -e "${BOLD}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BOLD}  ИТОГИ ТЕСТИРОВАНИЯ ОКРУЖЕНИЯ${NC}"
echo -e "${BOLD}═══════════════════════════════════════════════════════════${NC}"
echo -e "  Всего тестов:    ${BOLD}$TOTAL${NC}"
echo -e "  ${GREEN}Пройдено:      $PASSED${NC}"
echo -e "  ${RED}Провалено:     $FAILED${NC}"
echo -e "  ${YELLOW}Пропущено:     $SKIPPED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "  ${GREEN}${BOLD}Окружение настроено корректно!${NC}"
    exit 0
else
    echo -e "  ${RED}${BOLD}Обнаружены проблемы с окружением ($FAILED ошибок)${NC}"
    exit 1
fi
