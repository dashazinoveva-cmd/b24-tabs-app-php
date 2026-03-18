<?php

return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('DB_PORT') ?: '5432',
    'db_name' => getenv('DB_NAME') ?: 'b24_tabs',
    'db_user' => getenv('DB_USER') ?: 'b24_tabs_user',
    'db_pass' => getenv('DB_PASS') ?: '',

    'log_path' => __DIR__ . '/../../storage/app.log',

    'b24_client_id' => 'ТВОЙ_CLIENT_ID_ИЗ_КАБИНЕТА',
    'b24_client_secret' => 'ТВОЙ_CLIENT_SECRET_ИЗ_КАБИНЕТА',

    'app_url' => getenv('APP_URL') ?: 'https://dev.calendar.consult-info.ru',
];