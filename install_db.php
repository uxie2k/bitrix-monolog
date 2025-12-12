<?php
// ВНИМАНИЕ: Если путь к сайту нестандартный, поправьте require ниже.

// Отключаем сбор статистики и проверку прав — нам нужно просто выполнить код
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);

// Пытаемся найти пролог. Обычно vendor лежит в local или в корне, 
// так что идем вверх пока не найдем bitrix.
// Для упрощения считаем, что запускаем из корня сайта или через composer script.
$docRoot = $_SERVER["DOCUMENT_ROOT"];
if (empty($docRoot)) {
    // Если запускаем из консоли, DOCUMENT_ROOT может быть пустым, хардкодим или ищем
    $docRoot = dirname(__DIR__, 4); // Выходим из vendor/myname/package/
}

if (file_exists($docRoot . "/bitrix/modules/main/include/prolog_before.php")) {
    require($docRoot . "/bitrix/modules/main/include/prolog_before.php");
} else {
    die("Error: Не могу найти bitrix/modules/main/include/prolog_before.php. Проверьте пути.\n");
}

// Подключаем автолоад, если он вдруг еще не подхвачен
require_once __DIR__ . '/vendor/autoload.php';

use Uxie2k\BitrixMonolog\Installer\LogSchemaInstaller;

try {
    echo "--- Start Installation ---\n";
    
    // Создаем блок SystemLogs и таблицу b_hl_system_logs
    LogSchemaInstaller::install('SystemLogs', 'b_hl_system_logs');
    
    echo "--- Success! ---\n";
} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}