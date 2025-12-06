# План локализации WP Queue

## Обнаруженные русские строки для перевода

### 1. AdminPage.php (основной файл с интерфейсом)

#### Кнопки и действия

- "Возобновить" → "Resume" (строка 437, 2220)
- "Обработать сейчас" → "Process Now" (строка 1989)
- "Очистить все" → "Clear All" (строка 1995)
- "Очистить все логи" → "Clear All Logs" (строка 1983)

#### Статусы и метки

- "Неизвестно" → "Unknown" (строка 1264)
- "Отключён" → "Disabled" (строка 1364, 1869)
- "Активен" → "Active" (строка 1367, 1872)
- "Запланировано" → "Scheduled" (строка 1347)
- "Просрочено" → "Overdue" (строка 1351, 1446)
- "На паузе" → "Paused" (строка 1355)
- "Выполнено" → "Completed" (строка 1820)
- "Ошибок" → "Errors" (строка 1824)

#### Заголовки и разделы

- "События по источникам" → "Events by Source" (строка 1372)
- "Ближайшие события" → "Upcoming Events" (строка 1393)
- "Версии" → "Versions" (строка 1837)
- "Статус компонентов" → "Component Status" (строка 1856)
- "Время" → "Time" (строка 1918)
- "Инструменты" → "Tools" (строка 1977)
- "Быстрый старт" → "Quick Start" (строка 2037)
- "Отложенные задачи" → "Delayed Jobs" (строка 2056)
- "Именованные очереди" → "Named Queues" (строка 2076)
- "Повторные попытки" → "Retry Attempts" (строка 2094)
- "Планировщик" → "Scheduler" (строка 2115)
- "Полезные ссылки" → "Useful Links" (строка 2159)

#### Таблицы и колонки

- "Хук" → "Hook" (строка 1402, 2204)
- "Следующий запуск" → "Next Run" (строка 1403)
- "Источник" → "Source" (строка 1404)
- "Расписание" → "Schedule" (строка 2205)
- "Приостановлено" → "Paused Since" (строка 2206)
- "Действия" → "Actions" (строка 2207)
- "Всего событий" → "Total Events" (строка 1343)
- "Всего" → "Total" (строка 1442)
- "Драйвер очередей" → "Queue Driver" (строка 1860)
- "Время сервера" → "Server Time" (строка 1922)
- "Время WordPress" → "WordPress Time" (строка 1926)
- "Часовой пояс" → "Timezone" (строка 1930)
- "Версия" → "Version" (строка 1946)
- "Ожидающих" → "Pending" (строка 1951)
- "Использовано" → "Used" (строка 1907)

#### Описания и сообщения

- "Используйте системный cron" → "Use system cron" (строка 1365, 1870)
- "Нет запланированных событий" → "No scheduled events" (строка 1397)
- "Настройте системный cron" → "Configure system cron" (строка 1870)
- "Удаляет все логи выполнения задач" → "Deletes all job execution logs" (строка 1982)
- "Принудительно запускает обработку всех очередей" → "Forces processing of all queues" (строка 1988)
- "Удаляет все задачи из всех очередей" → "Deletes all jobs from all queues" (строка 1994)
- "Нет приостановленных событий" → "No paused events" (строка 2198)
- "Одноразовое" → "One-time" (строка 2214)
- "событий" → "events" (строка 1382)

#### Документация и примеры

- "Создайте класс задачи и отправьте её в очередь:" → "Create a job class and dispatch it to the queue:" (строка 2038)
- "Запланируйте выполнение задачи через определённое время:" → "Schedule a job to run after a specific time:" (строка 2057)
- "Распределяйте задачи по разным очередям:" → "Distribute jobs across different queues:" (строка 2077)
- "Настройте автоматические повторы при ошибках:" → "Configure automatic retries on errors:" (строка 2095)
- "Запускайте задачи по расписанию:" → "Run tasks on schedule:" (строка 2116)
- "Управляйте очередями из командной строки:" → "Manage queues from command line:" (строка 2139)
- "Документация на GitHub" → "Documentation on GitHub" (строка 2164)
- "Сообщить о проблеме" → "Report an Issue" (строка 2170)

#### Длинные описания (строка 1642)

- "Показывает информацию об окружении..." → "Displays environment information (PHP and WordPress versions, memory limits, execution time, timezone), WP-Cron status (disabled, alternative cron, loopback checks). If Action Scheduler is installed - its statistics. Server and WordPress time."

### 2. Комментарии в коде (не требуют локализации)

#### Scheduler.php

- "Проверка существования класса" → "Check class existence"
- "Логируем ошибку и возвращаем пустой ScheduledJob" → "Log error and return empty ScheduledJob"
- "Извлекаем короткое имя класса без ReflectionClass" → "Extract short class name without ReflectionClass"

#### WPQueue.php

- "Отключаем крон" → "Disable cron"
- "Удаляем таблицу логов при деактивации" → "Delete logs table on deactivation"

#### RestApi.php

- "Проверяем существование очереди" → "Check queue existence"
- "Очередь существует если есть задачи или это очередь default" → "Queue exists if it has jobs or is default queue"
- "Поддержка как объектов, так и массивов" → "Support both objects and arrays"
- "Получаем статистику из логов" → "Get statistics from logs"
- "Попробуем десериализовать payload" → "Try to deserialize payload"
- "Игнорируем ошибки десериализации" → "Ignore deserialization errors"
- "Находим все опции с джобами" → "Find all options with jobs"

## План действий

### Этап 1: Замена русских строк на английские

1. Заменить все строки в AdminPage.php на английские с функциями __()
2. Обновить комментарии в коде (по желанию, не влияют на локализацию)
3. Проверить consistency text domain 'wp-queue'

### Этап 2: Создание файлов локализации

1. Проверить наличие load_plugin_textdomain() в wp-queue.php
2. Создать .pot файл с помощью WP-CLI: `wp i18n make-pot . languages/wp-queue.pot`
3. Создать/обновить ru_RU.po файл с переводами
4. Скомпилировать .mo файл

### Этап 3: Тестирование

1. Проверить что все строки используют __() с правильным text domain
2. Убедиться что английский текст отображается по умолчанию
3. Проверить русскую локализацию при смене языка сайта

## Примечания

- Комментарии в коде можно оставить на русском - они не извлекаются в .pot
- Все строки интерфейса должны использовать функции локализации: __(), _e(), esc_html__()
- Text domain должен быть 'wp-queue' везде
- Файлы локализации должны находиться в папке /languages/
