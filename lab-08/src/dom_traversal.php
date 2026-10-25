<?php

$xmlFile = __DIR__ . '/persons.xml';

$dom = new DOMDocument('1.0', 'UTF-8');

if (!$dom->load($xmlFile, LIBXML_NONET)) {
    die('Помилка завантаження XML-файлу.');
}

$root = $dom->documentElement;

echo '<!DOCTYPE html>';
echo '<html lang="uk">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>Обхід DOM</title>';
echo '</head>';
echo '<body>';

echo '<h1>Обхід XML-документа за допомогою DOM</h1>';

echo '<p>Кореневий елемент: ' .
    htmlspecialchars($root->tagName, ENT_QUOTES, 'UTF-8') .
    '</p>';

echo '<table border="1" cellpadding="8">';
echo '<tr><th>№</th><th>Ім’я</th><th>Вік</th></tr>';

$persons = $root->getElementsByTagName('person');

$number = 1;

foreach ($persons as $person) {
    $name = $person->getElementsByTagName('name')->item(0)->textContent;
    $age = $person->getElementsByTagName('age')->item(0)->textContent;

    echo '<tr>';
    echo '<td>' . $number . '</td>';
    echo '<td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($age, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';

    $number++;
}

echo '</table>';
echo '</body>';
echo '</html>';

?>