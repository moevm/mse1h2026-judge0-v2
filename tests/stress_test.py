import requests
import concurrent.futures
import time
import random

TARGET_URL = "http://<enter  ip virtual_machine>:2358"
TOTAL_STUDENTS = 50
CONCURRENT_THREADS = 50

SCENARIOS = [
    {"name": "cpp_correct", "payload": {"language_id": 54, "source_code": "#include <iostream>\nusing namespace std;\nint main() { cout << \"Success\"; return 0; }"}},
    {"name": "python_correct", "payload": {"language_id": 71, "source_code": "print('Success')"}},
    {"name": "cpp_compile_error", "payload": {"language_id": 54, "source_code": "#include <iostream>\nint main() { cout << \"Forgot namespace\" return 0; }"}},
    {"name": "python_syntax_error", "payload": {"language_id": 71, "source_code": "print('Missing bracket'"}}
]

def student_submit(student_id):
    scenario = random.choice(SCENARIOS)
    try:
        post_start = time.time()
        
        resp = requests.post(
            f"{TARGET_URL}/submissions", 
            json=scenario["payload"], 
            timeout=90
        )
        resp.raise_for_status()
        
        token = resp.json()["token"]
        
        attempts = 0
        while attempts < 120:
            time.sleep(0.5)
            get_resp = requests.get(
                f"{TARGET_URL}/submissions/{token}", 
                timeout=90
            )
            data = get_resp.json()
            
            if "status" in data and data["status"].get("description") != "Processing":
                desc = data["status"].get("description", "Unknown")
                return (student_id, scenario["name"], desc, time.time() - post_start)
            
            attempts += 1
            
        return (student_id, scenario["name"], "Proxy Timeout", time.time() - post_start)

    except requests.exceptions.RequestException as e:
        return (student_id, "network_error", f"Network Error: {type(e).__name__}", 0)
    except Exception as e:
        return (student_id, "error", str(e), 0)

def main():
    print(f"Starting exam simulation: {TOTAL_STUDENTS} students.")
    start_test_time = time.time()
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=CONCURRENT_THREADS) as executor:
        futures = [executor.submit(student_submit, i) for i in range(TOTAL_STUDENTS)]
        
        completed = 0
        stats = {}
        
        for future in concurrent.futures.as_completed(futures):
            student_id, name, status, t = future.result()
            completed += 1
            
            stats[status] = stats.get(status, 0) + 1
            
            if completed % 10 == 0:
                print(f"Processed: {completed}/{TOTAL_STUDENTS}")

    total_time = time.time() - start_test_time
    
    print("\n--- Test Results ---")
    print(f"Total time: {total_time:.2f} s")
    print(f"Throughput: {TOTAL_STUDENTS / total_time:.2f} req/s\n")
    
    print("Verdicts:")
    for status, count in sorted(stats.items(), key=lambda x: x[1], reverse=True):
        print(f"  {status}: {count}")

if __name__ == "__main__":
    main()
