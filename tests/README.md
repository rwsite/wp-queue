# Тесты WP Queue

## Структура тестов

```
tests/
├── Unit/              # Unit-тесты (изолированные, без WordPress)
├── Feature/           # E2E/Integration тесты (с реальным WordPress)
├── Pest.php           # Конфигурация Pest для unit-тестов
├── bootstrap.php      # Bootstrap для unit-тестов (Brain Monkey)
└── bootstrap-e2e.php  # Bootstrap для E2E тестов (WordPress)
```

## Типы тестов

### Unit-тесты

- **Назначение**: Тестирование отдельных классов и методов в изоляции
- **Окружение**: Brain Monkey (мок WordPress функций)
- **Скорость**: Быстрые (~1-2 секунды)
- **Запуск**: `composer test:unit`

### E2E/Integration тесты

- **Назначение**: Тестирование полного цикла работы плагина
- **Окружение**: Реальный WordPress
- **Скорость**: Медленные (~10-30 секунд)
- **Запуск**: `composer test:e2e`

## Запуск тестов

### Все тесты

```bash
composer test
```

### Только unit-тесты

```bash
composer test:unit
```

### Только E2E тесты

```bash
composer test:e2e
```

### С покрытием кода

```bash
composer test:coverage
```

### Конкретный тест

```bash
vendor/bin/pest tests/Feature/QueueIntegrationTest.php
```

### Конкретный тест-кейс

```bash
vendor/bin/pest --filter="полный цикл"
```

## E2E тесты

### QueueIntegrationTest.php

Тестирует основной функционал очередей:

- ✅ Полный цикл: dispatch → queue → process → complete
- ✅ Задачи с delay
- ✅ Повторные попытки при ошибках
- ✅ FIFO порядок обработки
- ✅ Независимые очереди
- ✅ Pause/Resume очередей
- ✅ Clear/Cancel очередей
- ✅ Синхронное выполнение (dispatchSync)
- ✅ Лимиты worker (maxJobs, maxTime)
- ✅ Логирование (успех/ошибка)
- ✅ Сериализация пользовательских данных

### SchedulerIntegrationTest.php

Тестирует планировщик задач:

- ✅ Регистрация задач с атрибутом #[Schedule]
- ✅ Стандартные интервалы (hourly, daily, weekly, monthly)
- ✅ Кастомные интервалы
- ✅ Запуск по расписанию
- ✅ Методы планировщика (at, cron, dailyAt, everyMinute и т.д.)
- ✅ Отмена запланированных задач

### RestApiIntegrationTest.php

Тестирует REST API эндпоинты:

- ✅ GET /queues - список очередей
- ✅ GET /queues/{queue} - информация об очереди
- ✅ POST /queues/{queue}/pause - пауза очереди
- ✅ POST /queues/{queue}/resume - возобновление очереди
- ✅ POST /queues/{queue}/clear - очистка очереди
- ✅ GET /queues/{queue}/jobs - список задач
- ✅ GET /logs - логи выполнения
- ✅ GET /stats - статистика
- ✅ POST /queues/{queue}/process - запуск обработки
- ✅ Аутентификация и права доступа

### CliIntegrationTest.php

Тестирует WP-CLI команды:

- ✅ queue:work - обработка очереди
- ✅ queue:list - список очередей
- ✅ queue:clear - очистка очереди
- ✅ queue:pause - пауза очереди
- ✅ queue:resume - возобновление очереди
- ✅ queue:stats - статистика
- ✅ queue:failed - проваленные задачи
- ✅ queue:retry - повтор задачи
- ✅ queue:flush - очистка всех очередей
- ✅ queue:monitor - мониторинг
- ✅ cron:list - список cron задач
- ✅ cron:run - запуск cron задач

## Требования для E2E тестов

1. **WordPress установлен**: Тесты требуют реальную установку WordPress
2. **База данных**: Доступна база данных WordPress
3. **Права доступа**: Права на запись в wp_options

## Настройка окружения

### Локальная разработка

Для локальной разработки достаточно unit-тестов:

```bash
composer test:unit
```

### CI/CD (GitHub Actions)

E2E тесты запускаются автоматически в GitHub Actions:

- WordPress latest + PHP 8.3
- WordPress 6.6 + PHP 8.3

Конфигурация в `.github/workflows/ci.yml`.

## Отладка тестов

### Вывод дополнительной информации

```bash
vendor/bin/pest --verbose
```

### Остановка на первой ошибке

```bash
vendor/bin/pest --stop-on-failure
```

### Запуск с отладочной информацией

```bash
vendor/bin/pest --debug
```

## Покрытие кода

Цель: **80%+ покрытие**

Текущее покрытие:

- Unit-тесты: ~60%
- E2E тесты: ~90%
- Общее: ~75%

## Лучшие практики

1. **Изоляция**: Каждый тест должен быть независимым
2. **Очистка**: Используйте beforeEach/afterEach для очистки данных
3. **Читаемость**: Названия тестов на русском, описывают что тестируется
4. **Покрытие**: Тестируйте edge cases и ошибки
5. **Скорость**: Unit-тесты быстрые, E2E только для критичного функционала

## Troubleshooting

### Ошибка: WordPress не найден

```bash
# Проверьте путь к WordPress в bootstrap-e2e.php
$wp_root = dirname(__DIR__, 4);
```

### Ошибка: База данных недоступна

```bash
# Проверьте wp-config.php
# Убедитесь что база данных запущена
```

### Ошибка: Недостаточно прав

```bash
# Проверьте права на запись
chmod -R 755 wp-content/plugins/wp-queue
```

### Тесты падают случайно

```bash
# Очистите кеш
rm -rf .phpunit.cache
composer test:e2e
```
