<?php

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function readText(string $path): string
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('File not found or not readable.');
    }

    $text = file_get_contents($path);

    if ($text === false || trim($text) === '') {
        throw new RuntimeException('File is empty or cannot be read.');
    }

    return $text;
}

function parseXml(string $text): DOMDocument
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->resolveExternals = false;
    $dom->substituteEntities = false;

    $previous = libxml_use_internal_errors(true);

    try {
        if (stripos($text, '<!DOCTYPE') !== false) {
            throw new RuntimeException('DOCTYPE is not allowed.');
        }

        if (!$dom->loadXML($text, LIBXML_NONET)) {
            throw new RuntimeException('Invalid XML document.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $dom;
}

function validatePeople($people): array
{
    if (!is_array($people)) {
        throw new RuntimeException('Expected a list of people.');
    }

    $keys = array_keys($people);

    if (count($people) > 0 &&
        $keys !== range(0, count($people) - 1)) {
        throw new RuntimeException('Expected a list of people.');
    }

    if (count($people) !== 3) {
        throw new RuntimeException('Expected exactly 3 people.');
    }

    foreach ($people as $person) {
        if (!is_array($person)) {
            throw new RuntimeException('Invalid person data.');
        }

        if (!isset($person['name']) ||
            !is_string($person['name']) ||
            trim($person['name']) === '') {
            throw new RuntimeException('Invalid person name.');
        }

        if (!isset($person['age']) ||
            !is_int($person['age']) ||
            $person['age'] < 0 ||
            $person['age'] > 120) {
            throw new RuntimeException('Invalid person age.');
        }
    }

    return $people;
}

function peopleFromXml(DOMDocument $dom): array
{
    $root = $dom->documentElement;

    if (!$root || $root->tagName !== 'people' ||
        $root->hasAttributes()) {
        throw new RuntimeException('Invalid XML root.');
    }

    $people = [];

    foreach ($root->childNodes as $node) {
        if ($node instanceof DOMText &&
            trim($node->textContent) === '') {
            continue;
        }

        if (!($node instanceof DOMElement) ||
            $node->tagName !== 'person' ||
            $node->hasAttributes()) {
            throw new RuntimeException('Invalid person element.');
        }

        $person = [];
        $nameFound = false;
        $ageFound = false;

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText &&
                trim($child->textContent) === '') {
                continue;
            }

            if (!($child instanceof DOMElement) ||
                $child->hasAttributes() ||
                $child->childElementCount > 0) {
                throw new RuntimeException('Invalid person field.');
            }

            if ($child->tagName === 'name' && !$nameFound) {
                $person['name'] = $child->textContent;
                $nameFound = true;
            } elseif ($child->tagName === 'age' && !$ageFound) {
                $ageText = trim($child->textContent);

                if (!preg_match('/^\d+$/', $ageText)) {
                    throw new RuntimeException('Invalid age value.');
                }

                $person['age'] = (int)$ageText;
                $ageFound = true;
            } else {
                throw new RuntimeException('Unexpected or duplicate field.');
            }
        }

        if (!$nameFound || !$ageFound) {
            throw new RuntimeException('Missing person fields.');
        }

        $people[] = $person;
    }

    return validatePeople($people);
}

function peopleToXml(array $people): DOMDocument
{
    $people = validatePeople($people);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    $root = $dom->createElement('people');
    $dom->appendChild($root);

    foreach ($people as $person) {
        $personNode = $dom->createElement('person');

        $nameNode = $dom->createElement('name');
        $nameNode->appendChild(
            $dom->createTextNode($person['name'])
        );

        $ageNode = $dom->createElement('age');
        $ageNode->appendChild(
            $dom->createTextNode((string)$person['age'])
        );

        $personNode->appendChild($nameNode);
        $personNode->appendChild($ageNode);
        $root->appendChild($personNode);
    }

    return $dom;
}

function renderPeople(
    array $people,
    string $format,
    ?string $error = null
): void {
    echo '<!DOCTYPE html>';
    echo '<html lang="uk">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Перелік людей</title>';
    echo '</head>';
    echo '<body>';

    echo '<h1>Дані з формату ' . e($format) . '</h1>';

    if ($error !== null) {
        echo '<p>' . e($error) . '</p>';
    } else {
        echo '<table border="1" cellpadding="8">';
        echo '<tr><th>Ім’я</th><th>Вік</th></tr>';

        foreach ($people as $person) {
            echo '<tr>';
            echo '<td>' . e($person['name']) . '</td>';
            echo '<td>' . e((string)$person['age']) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    echo '</body>';
    echo '</html>';
}