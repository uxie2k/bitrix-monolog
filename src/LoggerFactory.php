<?php

declare(strict_types=1);

namespace Uxie2k\BitrixMonolog;

use Monolog\Logger;
use Uxie2k\BitrixMonolog\Handler\HighloadBlockHandler;
use Uxie2k\BitrixMonolog\Processor\BitrixContextProcessor;

class LoggerFactory
{
    public static function create(string $channel, string $hlBlockName): Logger
    {
        $logger = new Logger($channel);
        
        // Подключаем наш кастомный хендлер
        $logger->pushHandler(new HighloadBlockHandler($hlBlockName));
        
        // Добавляем процессор контекста (IP, User ID) ко всем записям
        $logger->pushProcessor(new BitrixContextProcessor());

        return $logger;
    }
}