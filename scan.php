<?php
/*

      Инструкция: https://github.com/vanyokor/scan.php/blob/main/README.md
    ❗Пожалуйста, не забывайте удалять скрипт, для сохранения безопасности сайта❗

*/

// GET параметр, который необходимо передать в скрипт, для его запуска
define('STARTER', 'run');
define('TIMEZONE', 'Europe/Moscow');

// исключить из поиска директории
const IGNORE_DIR = array(
    './.git',
    './cgi-bin',
    './stats',
    './bitrix/sounds',
    './bitrix/services',
    './bitrix/panel',
    './bitrix/otp',
    './bitrix/legal',
    './bitrix/blocks',
    './bitrix/fonts',
    './bitrix/themes',
    './bitrix/gadgets',
    './bitrix/tmp',
    './bitrix/backup',
    './bitrix/images',
    './bitrix/cache',
    './bitrix/managed_cache',
    './bitrix/html_pages',
    './bitrix/stack_cache',
    './bitrix/updates',
    './bitrix/modules',
    './bitrix/wizards',
    './upload/resize_cache',
    './upload/medialibrary',
    './upload/iblock',
    './upload/tmp',
    './upload/uf',
    './system/storage',
    './image/cache',
    './wp-content/cache',
    './core/cache',
    './assets/cache',
    './logs',
    './cache',
    './administrator/cache',
    './wa-cache',
    './var/cache',
    './wp-content/plugins/akeebabackupwp/app/tmp',
);

// исключить из поиска файлы
const IGNORE_FILE = array(
    './scan.php',
);


/*
    Экранирование названия файла
*/
function escape_name($name)
{
    return htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE);
}


/*
    Проверка на подходящий для сканирования файл
*/
function is_correct_file($file, $onlyphp)
{
    if (!in_array($file, IGNORE_FILE)) {
        if ($onlyphp) {
            if (substr($file, -4, 4) !== '.php') {
                return false;
            }
        }
        return true;
    }

    return false;
}

/*
    Рекурсивное сканирование файлов
*/
function scan_recursive($directory, $onlyphp)
{
    global $files, $start_time, $interrupted;

    if (is_readable($directory)) {
        $dir = array_diff(scandir($directory), array('.', '..'));
        foreach ($dir as $fname) {
            $filename = $directory.DIRECTORY_SEPARATOR.$fname;

            if (is_link($filename)) {
                continue;
            } elseif (is_dir($filename)) {
                if (!in_array($filename, IGNORE_DIR)) {
                    scan_recursive($filename, $onlyphp);
                }
            } elseif (is_correct_file($filename, $onlyphp)) {
                $filedate = filemtime($filename);
                $files[$filedate][] = escape_name($filename);
            }
            
            if ((time() - $start_time) > 55) {
                $interrupted = true;
                return;
            }
        }
    }
}


// Самоудаление скрипта, при попытке запуска, через сутки
if (time() > (filectime(__FILE__) + 86400)) {
    @unlink(__FILE__);
    exit('file timeout');
}
// Самоудаление скрипта по get запросу
if (isset($_GET['delete'])) {
    if (is_writable(__FILE__)) {
        unlink(__FILE__);
        exit('deleted');
    } else {
        exit('Error! no permission to delete');
    }
}

// Для запуска, не забудь добавить GET параметр в адресную строку
if (!isset($_GET[STARTER])) {
    die();
}

// Список найденых файлов
$files = array();

// запуск таймера, чтобы скрипт не сканировал больше минуты
$start_time = time();

// Выбор режима сканирования
$mode = 0;
if (!empty($_POST['mode'])) {
    $selected = (int) $_POST['mode'];
    if (($selected > 0) && ($selected < 3)) {
        $mode = $selected;
    }
    unset($selected);
}

// Флаг, прервано ли сканирование из-за таймаута
$interrupted = false;

// Защита от межсайтового скриптинга
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Установка рекомендуемого лимита оперативной памяти и времени выполнения
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '60');

// настройка для корректного отображения времени правки файла
date_default_timezone_set(TIMEZONE);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>scan.php</title>
<style>*,*:before,*:after{box-sizing: inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:800px;margin:10px auto 60px}form,p,h3,output{text-align:center;font-size:small;user-select:none}section{margin:5px 0;font-size:small;background:#f1f1f1;border-radius:3px;display:flex}h2{background:#d4d9dd;padding:5px 6px;border-radius:3px;font-size:medium;font-weight:normal;margin:0;flex-shrink:0}article{overflow-wrap: anywhere;padding: 5px}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px;display:block}</style>
</head>
<body>
<form method="POST">
<h5> Mode: 
<select name="mode">
<option value="1">scan all files</option>
<option value="2"<?php if ($mode == 2) echo ' selected';?>>scan only PHP</option>
</select> 
<button type="submit">start</button>
</h5>
<p> don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
</form>
<?php
if ($mode) {
    // Запуск сканера
    scan_recursive('.', $mode == 2 ? true : false);

    // Сортировка по дате модификации
    krsort($files);
?>
<main>
<?php
    foreach ($files as $filedate => $filenames) {
        foreach ($filenames as $filename) {
            echo "<section><h2>", date('d.m.y H:i:s', $filedate), "</h2><article>", $filename, "</article></section>";
        }
    }
?>
</main>
<?php
    if ($interrupted) {
        echo '<output>Scan time has expired!</output>';
    } else {
        echo '<h3>Scan completed</h3>';
    }
}
?>
</body>
</html>
