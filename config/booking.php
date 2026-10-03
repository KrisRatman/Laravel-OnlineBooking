<?php

return [

    // Витрина публичной страницы записи.
    'business' => [
        'name' => env('BOOKING_BUSINESS_NAME', 'Студия красоты «Ясень»'),
        'address' => env('BOOKING_BUSINESS_ADDRESS', 'Москва, ул. Садовая, 12'),
        'phone' => env('BOOKING_BUSINESS_PHONE', '+7 (495) 000-00-00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Часовой пояс бизнеса
    |--------------------------------------------------------------------------
    |
    | В БД все моменты времени хранятся в UTC (config/app.php → timezone).
    | В этом поясе строится расписание мастеров и показываются даты клиентам
    | и в админке.
    |
    */

    'timezone' => env('BOOKING_TIMEZONE', 'Europe/Moscow'),

    // Шаг сетки слотов в минутах: 10:00, 10:15, 10:30...
    'slot_step' => (int) env('BOOKING_SLOT_STEP', 15),

    // Не раньше, чем через столько минут от текущего момента.
    'min_lead_minutes' => (int) env('BOOKING_MIN_LEAD_MINUTES', 60),

    // Сколько записей можно создать с одного IP за 10 минут (защита от спама).
    // За HTTPS-прокси все посетители приходят с IP прокси — на демо лимит стоит поднять.
    'rate_limit' => (int) env('BOOKING_RATE_LIMIT', 5),

    // На сколько дней вперёд открыта запись.
    'horizon_days' => (int) env('BOOKING_HORIZON_DAYS', 30),

    // Клиент сам отменяет или переносит запись не позже, чем за столько минут до начала.
    'client_change_deadline_minutes' => (int) env('BOOKING_CLIENT_CHANGE_DEADLINE', 60),

    // Вход в личный кабинет по одноразовому коду.
    'login_code' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        // Сколько кодов можно запросить на один номер за 10 минут.
        'per_phone' => 3,
    ],

    // Напоминания: за сколько минут до начала записи.
    'reminders' => [
        'day' => 24 * 60,
        'hours' => 2 * 60,
    ],

    // Демо для портфолио: форма входа в админку заполнена этим доступом,
    // а в личный кабинет можно войти демо-клиентом без кода.
    'demo' => [
        'enabled' => (bool) env('BOOKING_DEMO', false),
        'email' => env('BOOKING_DEMO_EMAIL', 'admin@example.com'),
        'password' => env('BOOKING_DEMO_PASSWORD', 'password'),
        'client_phone' => env('BOOKING_DEMO_CLIENT_PHONE', '+79161000000'),
    ],

    // Разбирать очередь через планировщик (виртуальный хостинг без supervisor).
    'scheduled_queue_worker' => (bool) env('BOOKING_SCHEDULED_QUEUE_WORKER', false),

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME'),
        // Секрет в заголовке X-Telegram-Bot-Api-Secret-Token, которым Telegram подписывает webhook.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

];
