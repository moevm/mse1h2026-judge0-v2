# mse1h2026-judge0-v2

## О проекте
Проект предполагает интеграцию системы judge0 на замену текущей coderunner, для тестирования ее работы и совместимости с moodle.

### Постановка задачи
Интеграция и тестирование judge0 в локальном контейнере moodle на нескольких задачах.

### Требования
#### Системные требования
- **Docker** (версии 20.10 или выше)
- **Docker Compose** (версии 2.0 или выше)
- Не менее 4 ГБ оперативной памяти.

### Сценарии использования
Создание задач по программированию и автоматическая их проверке

### Инструкция по запуску
#### Развертывание контейнеров
- Перейдите в ветку проекта ```master```
- Установить образ убуту [link](https://drive.google.com/drive/u/1/folders/1yaOkhyw99g63MnO0nDXtLBTjpZcPOfGz)
- Запустите скрипт judge0_script.sh. Затем скопируйте ip адрес, который скприпт выдаст
- Заруститите докер контейнер, который лежит рядом judge0_script.sh 
#### Настройка плагина в Moodle
- Откройте браузер и перейдите по адресу: [http://localhost](http://localhost).
- Авторизуйтесь под учетной записью администратора:
   - **Логин:** `admin`
   - **Пароль:** `bitnami1`
- Перейдите в раздел управления плагинами: `Site administration` -> `plugins` -> `install plugins` -> `Выбираем judge0_plug.zip` -> `Install plugin from zip_file` -> `Далее нажимаем на продолжить и обновить базу данных`

- Далее в настройке необзодимо указать ссылку который выдал скрипт (полностью), либо вставить эту ссылку https://ce.judge0.com, если возникли проблемы с виртуальной машиной 
- В Monaco Base Url указать - https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs

  
После успешной установки необходимо создать задачу с judge0

1) Создаем курс
2) Включаем Edit mode в правом верхнем углу
3) Нажимам на + под Announcements -> далее выбираем activity or resource -> Quize
4) Далее даем имя -> Нажимаем на question behavior -> В How questions behave выбираем Interactive with multiple tries.
5) Далее нажимаем на синюю кнопку Save and display
6) Нажимем на add question
7) Далее выбираем выпадющее окно Add (под параметром Shuffle) -> a new question -> Judge0 code evaluator -> Add
8) Указываем имя и текст задачи

Далее объясние логики работы

Есть два поля - Checker Code и Expected Output. 

1) Checker Code  - необходим чтобы вызвать функцию решение.
2) Expected Output - проверка вывода функции решения

Пример:

Решение студента:
```python3
def solve(x):
   print(x + x) 
```
Checker Code:

```
solve(2)
solve(5)
```
Expected Output:

```
4
10
```
Для простоты проверки работоспособности плагина достаточно просто Checker Code оставить пустым, в Expected Output написать `1`, а в решении просто написать ```print(1)```

Когда поля заполнены, далее:

9) Save changes
10) Выбираем вкладку quize и решаем задачу

Пример переноса реальной задачи из курса программирования: [demo/unrolled_list_variant3](demo/unrolled_list_variant3/)

Демонстрация работы примера: [видео на Google Drive](https://drive.google.com/file/d/1DYltL82k3tY1l9y3F-iG4EVFtAuO1PK5/view?usp=sharing)
