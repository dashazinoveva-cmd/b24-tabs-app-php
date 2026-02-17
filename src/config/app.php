<?php

return [
    // БД (пока SQLite файл)
    'db_path' => __DIR__ . '/../../storage/app.db',

    // Логи (для отладки install)
    'log_path' => __DIR__ . '/../../storage/app.log',

    // Эти значения потом даст кабинет разработчика Bitrix24
    'b24_client_id' => getenv('B24_CLIENT_ID') ?: '',
    'b24_client_secret' => getenv('B24_CLIENT_SECRET') ?: '',
];