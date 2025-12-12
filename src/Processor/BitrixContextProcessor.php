<?php

declare(strict_types=1);

namespace Uxie2k\BitrixMonolog\Processor;

use Bitrix\Main\Application;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class BitrixContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        // Достаем данные запроса через D7, так чище, чем копаться в $_SERVER
        $server = Application::getInstance()->getContext()->getServer();
        $extra['url'] = $server->getRequestUri();
        $extra['method'] = $server->getRequestMethod();
        $extra['ip'] = $server->getRemoteAddr();

        // С юзером аккуратнее: на кроне или в консоли $USER может не быть
        global $USER;
        if (isset($USER) && $USER instanceof \CUser && $USER->GetID()) {
            $extra['user_id'] = (int)$USER->GetID();
        } else {
            $extra['user_id'] = 0; // 0 обычно значит "Гость" или "Система"
        }

        return $record->with(extra: $extra);
    }
}