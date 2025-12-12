<?php

declare(strict_types=1);

namespace Uxie2k\BitrixMonolog\Installer;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use CUserTypeEntity;

class LogSchemaInstaller
{
    /**
     * Создает или обновляет структуру БД.
     * Запускай хоть 10 раз подряд — дублей не будет.
     */
    public static function install(string $hlBlockName, ?string $tableName = null): void
    {
        Loader::includeModule('highloadblock');

        // Проверяем, жив ли блок
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $hlBlockName]
        ])->fetch();

        if ($hlblock) {
            echo "HLBlock '$hlBlockName' уже есть (ID: {$hlblock['ID']}). Пропускаем создание.\n";
            $hlBlockId = $hlblock['ID'];
        } else {
            // Если таблицы нет, генерим имя типа b_hlbd_systemlogs
            $tableName = $tableName ?? 'b_hlbd_' . strtolower($hlBlockName);
            
            $result = HighloadBlockTable::add([
                'NAME'       => $hlBlockName,
                'TABLE_NAME' => $tableName, 
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException('Не удалось создать HLBlock: ' . implode(', ', $result->getErrorMessages()));
            }

            $hlBlockId = $result->getId();
            echo "HLBlock '$hlBlockName' успешно создан (Table: $tableName).\n";
        }

        // Формируем ID сущности для привязки полей (формат всегда HLBLOCK_ + ID)
        $entityId = 'HLBLOCK_' . $hlBlockId;

        // Пробегаемся по полям. Если поля нет — создаст, если есть — пропустит.
        self::ensureField($entityId, 'UF_DATE', 'datetime', 'Дата');
        self::ensureField($entityId, 'UF_LEVEL', 'string', 'Уровень');
        self::ensureField($entityId, 'UF_MESSAGE', 'string', 'Сообщение');
        // JSON храним в строках, для логов этого за глаза
        self::ensureField($entityId, 'UF_CONTEXT', 'string', 'Context (JSON)');
        self::ensureField($entityId, 'UF_EXTRA', 'string', 'Extra Info (JSON)');
        
        echo "Структура БД готова к работе.\n";
    }

    private static function ensureField($entityId, $fieldName, $type, $label): void
    {
        // Старый добрый CUserTypeEntity, потому что D7 для полей еще сыроват/сложен
        $existing = CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId, 
            'FIELD_NAME' => $fieldName
        ])->Fetch();

        if ($existing) {
            // Поле есть — ничего не делаем, выходим
            return;
        }

        $oUserTypeEntity = new CUserTypeEntity();
        $arFields = [
            'ENTITY_ID'    => $entityId,
            'FIELD_NAME'   => $fieldName,
            'USER_TYPE_ID' => $type,
            'XML_ID'       => $fieldName,
            'SORT'         => 100,
            'MULTIPLE'     => 'N',      // Множественные поля для логов — это боль, не надо
            'MANDATORY'    => 'N',
            'SHOW_FILTER'  => 'N',
            'SHOW_IN_LIST' => 'Y',      // Чтобы в админке было видно
            'EDIT_IN_LIST' => 'N',      // Редактировать логи в списке? Зачем?
            'IS_SEARCHABLE'=> 'N',
            'EDIT_FORM_LABEL'   => ['ru' => $label, 'en' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label, 'en' => $label],
        ];

        if (!$oUserTypeEntity->Add($arFields)) {
             echo "ОШИБКА: Не смогли создать поле $fieldName.\n";
        } else {
             echo "Поле $fieldName создано.\n";
        }
    }
}