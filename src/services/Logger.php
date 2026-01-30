<?php

class Logger
{
    public static function log(string $message, array $context = []): void
    {
        $config = require __DIR__ . '/../config/app.php';
        $path = $config['log_path'];

        $line = '[' . date('c') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;

        @file_put_contents($path, $line, FILE_APPEND);
    }
}