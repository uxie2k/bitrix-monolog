<?php

declare(strict_types=1);

namespace Uxie2k\BitrixMonolog\Handler;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Data\DataManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

class HighloadBlockHandler extends AbstractProcessingHandler
{
    private string $hlBlockName;
    private ?DataManager $dataClass = null;

    public function __construct(string $hlBlockName, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->hlBlockName = $hlBlockName;
    }

    /**
     * Ленивая инициализация ORM.
     * Не дергаем базу в конструкторе, потому что логов может и не быть на странице,
     * а лишний запрос к БД нам не нужен.
     */
    private function getDataClass(): DataManager
    {
        if ($this->dataClass === null) {
            // Без этого модуля дальше ехать некуда
            if (!Loader::includeModule('highloadblock')) {
                throw new \RuntimeException('Module highloadblock is required.');
            }

            // Ищем блок по имени. По ID искать нельзя — на проде и деве они разные.
            $hlblock = HighloadBlockTable::getList([
                'filter' => ['=NAME' => $this->hlBlockName]
            ])->fetch();

            if (!$hlblock) {
                // Если блока нет — падаем. Инсталлер надо было запускать :)
                throw new \RuntimeException(sprintf('HLBlock "%s" not found. Did you run the installer?', $this->hlBlockName));
            }

            // Магия Битрикса: компилируем сущность на лету
            $entity = HighloadBlockTable::compileEntity($hlblock);
            $this->dataClass = $entity->getDataClass();
        }

        return $this->dataClass;
    }

    protected function write(LogRecord $record): void
    {
        // Раскладываем данные Монолога по полям HL-блока
        $data = [
            'UF_DATE'    => \Bitrix\Main\Type\DateTime::createFromTimestamp($record->datetime->getTimestamp()),
            'UF_LEVEL'   => $record->level->getName(),
            'UF_MESSAGE' => $record->message,
            // Если контекст пустой, пишем null или пустую строку, чтобы базу не засорять
            'UF_CONTEXT' => !empty($record->context) ? json_encode($record->context, JSON_UNESCAPED_UNICODE) : '',
            'UF_EXTRA'   => !empty($record->extra) ? json_encode($record->extra, JSON_UNESCAPED_UNICODE) : '',
        ];

        try {
            $this->getDataClass()::add($data);
        } catch (\Throwable $e) {
            // Самый плохой кейс: логгер сломался.
            // Не валим весь сайт, просто пишем в нативный лог сервера, чтобы админ увидел.
            error_log('Monolog HLHandler Failed: ' . $e->getMessage());
        }
    }
}