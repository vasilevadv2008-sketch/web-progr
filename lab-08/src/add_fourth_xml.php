<?php

if (PHP_SAPI !== 'cli') {
    exit('Запускайте цей файл через термінал.');
}

$file = __DIR__ . '/persons_copy.xml';

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

if (!$dom->load($file, LIBXML_NONET)) {
    exit("Помилка читання XML-файла.\n");
}

$root = $dom->documentElement;

if (!$root || $root->tagName !== 'people') {
    exit("Неправильна структура XML.\n");
}

$person = $dom->createElement('person');

$name = $dom->createElement('name');
$name->appendChild($dom->createTextNode('Марія'));

$age = $dom->createElement('age');
$age->appendChild($dom->createTextNode('19'));

$person->appendChild($name);
$person->appendChild($age);
$root->appendChild($person);

$bytes = $dom->save($file);

if ($bytes === false) {
    exit("Не вдалося зберегти XML.\n");
}

echo "До XML додано четвертий запис.\n";
echo "Розмір записаного файла: " . filesize($file) . " байтів.\n";

?>