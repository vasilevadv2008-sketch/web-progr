<?php

if (PHP_SAPI !== 'cli') {
    exit('Запускайте цей файл через термінал.');
}

$file = __DIR__ . '/persons_copy.json';

$json = file_get_contents($file);

if ($json === false) {
    exit("Не вдалося прочитати JSON-файл.\n");
}

$people = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE ||
    !is_array($people)) {
    exit("Помилка читання JSON.\n");
}

$people[] = array(
    'name' => 'Марія',
    'age' => 19
);

$newJson = json_encode(
    $people,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);

if ($newJson === false) {
    exit("Не вдалося створити JSON.\n");
}

$bytes = file_put_contents($file, $newJson);

if ($bytes === false) {
    exit("Не вдалося зберегти JSON.\n");
}

echo "До JSON додано четвертий запис.\n";
echo "Розмір записаного файла: " . filesize($file) . " байтів.\n";

?>