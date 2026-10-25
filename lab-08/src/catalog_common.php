<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function catalogPath($format) {
    if ($format === 'xml') {
        return __DIR__ . '/xml/book.xml';
    }
    if ($format === 'json') {
        return __DIR__ . '/json/book.json';
    }
    throw new RuntimeException('Невідомий формат каталогу.');
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrf() {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function validateBook($input) {
    $errors = array();
    $title = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';
    $author = isset($input['author']) && is_string($input['author']) ? trim($input['author']) : '';
    $yearRaw = isset($input['year']) ? (string)$input['year'] : '';

    if ($title === '') {
        $errors[] = 'Введіть назву книжки.';
    } elseif (mb_strlen($title, 'UTF-8') > 200) {
        $errors[] = 'Назва має містити не більше 200 символів.';
    }

    if ($author === '') {
        $errors[] = 'Введіть автора книжки.';
    } elseif (mb_strlen($author, 'UTF-8') > 100) {
        $errors[] = 'Ім’я автора має містити не більше 100 символів.';
    }

    if ($yearRaw === '' || !preg_match('/^\d{4}$/', $yearRaw)) {
        $errors[] = 'Рік має бути цілим числом із чотирьох цифр.';
        $year = 0;
    } else {
        $year = (int)$yearRaw;
        if ($year < 1450 || $year > (int)date('Y')) {
            $errors[] = 'Рік має бути від 1450 до ' . date('Y') . '.';
        }
    }

    return array(
        'book' => array('title' => $title, 'author' => $author, 'year' => $year),
        'errors' => $errors
    );
}

function validateStoredBooks($books) {
    if (!is_array($books)) {
        throw new RuntimeException('Файл каталогу має неправильну структуру.');
    }

    $ids = array();
    foreach ($books as $book) {
        if (!is_array($book)
            || !isset($book['id'], $book['title'], $book['author'], $book['year'])
            || !is_string($book['id'])
            || !preg_match('/^[a-f0-9]{16}$/', $book['id'])
            || !is_string($book['title'])
            || trim($book['title']) === ''
            || mb_strlen($book['title'], 'UTF-8') > 200
            || !is_string($book['author'])
            || trim($book['author']) === ''
            || mb_strlen($book['author'], 'UTF-8') > 100
            || !is_int($book['year'])
            || $book['year'] < 1450
            || $book['year'] > (int)date('Y')) {
            throw new RuntimeException('У файлі каталогу знайдено некоректний запис.');
        }
        if (isset($ids[$book['id']])) {
            throw new RuntimeException('У файлі каталогу є повторний ID.');
        }
        $ids[$book['id']] = true;
    }
    return $books;
}

function readBooks($format) {
    $path = catalogPath($format);
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Файл каталогу не знайдено або його неможливо прочитати. Створіть файл під час підготовки.');
    }
    $text = file_get_contents($path);
    if ($text === false || trim($text) === '') {
        throw new RuntimeException('Файл каталогу порожній або пошкоджений.');
    }

    if ($format === 'json') {
        $books = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($books)) {
            throw new RuntimeException('Некоректний JSON у файлі каталогу.');
        }
        return validateStoredBooks($books);
    }

    if (stripos($text, '<!DOCTYPE') !== false) {
        throw new RuntimeException('DOCTYPE не дозволено.');
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        if (!$dom->loadXML($text, LIBXML_NONET)) {
            throw new RuntimeException('Некоректний XML у файлі каталогу.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    $root = $dom->documentElement;
    if (!$root || $root->tagName !== 'books' || $root->hasAttributes()) {
        throw new RuntimeException('Неправильний кореневий елемент XML.');
    }

    $books = array();
    foreach ($root->childNodes as $node) {
        if ($node instanceof DOMText && trim($node->textContent) === '') {
            continue;
        }
        if (!($node instanceof DOMElement) || $node->tagName !== 'book' || $node->hasAttributes()) {
            throw new RuntimeException('Неправильний елемент у XML-каталозі.');
        }

        $item = array();
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }
            if (!($child instanceof DOMElement) || $child->hasAttributes() || $child->childElementCount > 0) {
                throw new RuntimeException('Неправильне поле книжки в XML.');
            }
            if (!in_array($child->tagName, array('id', 'title', 'author', 'year'), true)
                || array_key_exists($child->tagName, $item)) {
                throw new RuntimeException('Невідоме або повторне поле книжки.');
            }
            $item[$child->tagName] = $child->textContent;
        }

        if (!isset($item['id'], $item['title'], $item['author'], $item['year'])
            || !preg_match('/^\d+$/', $item['year'])) {
            throw new RuntimeException('У XML-записі бракує полів або рік неправильний.');
        }
        $item['year'] = (int)$item['year'];
        $books[] = $item;
    }
    return validateStoredBooks($books);
}

function saveBooks($format, $books) {
    $books = validateStoredBooks($books);
    $path = catalogPath($format);
    if (!is_file($path) || !is_writable($path)) {
        throw new RuntimeException('Файл каталогу відсутній або недоступний для запису.');
    }

    if ($format === 'json') {
        $content = json_encode($books, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            throw new RuntimeException('Не вдалося сформувати JSON.');
        }
        $content .= PHP_EOL;
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false || !rename($tmp, $path)) {
            if (is_file($tmp)) { unlink($tmp); }
            throw new RuntimeException('Не вдалося зберегти JSON-файл.');
        }
        return true;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $root = $dom->createElement('books');
    $dom->appendChild($root);

    foreach ($books as $book) {
        $bookNode = $dom->createElement('book');
        foreach (array('id', 'title', 'author', 'year') as $field) {
            $fieldNode = $dom->createElement($field);
            $fieldNode->appendChild($dom->createTextNode((string)$book[$field]));
            $bookNode->appendChild($fieldNode);
        }
        $root->appendChild($bookNode);
    }

    $tmp = $path . '.tmp';
    if ($dom->save($tmp) === false || !rename($tmp, $path)) {
        if (is_file($tmp)) { unlink($tmp); }
        throw new RuntimeException('Не вдалося зберегти XML-файл.');
    }
    return true;
}

function generateBookId($books) {
    do {
        $id = bin2hex(random_bytes(8));
        $exists = false;
        foreach ($books as $book) {
            if ($book['id'] === $id) {
                $exists = true;
                break;
            }
        }
    } while ($exists);
    return $id;
}

function renderHeader($title) {
    echo '<!DOCTYPE html><html lang="uk"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . '</title></head><body>';
    echo '<h1>' . h($title) . '</h1>';
}

function renderFooter() {
    echo '</body></html>';
}

function renderBooks($format, $books, $error = '') {
    renderHeader('Бібліотека ' . strtoupper($format));
    if ($error !== '') {
        echo '<p role="alert">' . h($error) . '</p>';
    }
    echo '<p><a href="add.php">Додати книжку</a></p>';
    if (!$books) {
        echo '<p>Каталог поки що порожній.</p>';
    } else {
        echo '<table border="1" cellpadding="8">';
        echo '<thead><tr><th>ID</th><th>Назва</th><th>Автор</th><th>Рік</th><th>Дія</th></tr></thead><tbody>';
        foreach ($books as $book) {
            $url = 'edit.php?id=' . rawurlencode($book['id']);
            echo '<tr><td>' . h($book['id']) . '</td><td>' . h($book['title']) . '</td>';
            echo '<td>' . h($book['author']) . '</td><td>' . h($book['year']) . '</td>';
            echo '<td><a href="' . h($url) . '">Редагувати</a></td></tr>';
        }
        echo '</tbody></table>';
    }
    renderFooter();
}

function renderBookForm($format, $actionTitle, $values, $errors = array(), $id = '') {
    renderHeader($actionTitle . ' — ' . strtoupper($format));
    if ($errors) {
        echo '<div role="alert"><p>Перевірте введені дані:</p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . h($error) . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
    if ($id !== '') {
        echo '<input type="hidden" name="id" value="' . h($id) . '">';
    }
    echo '<p><label for="title">Назва книжки:</label><br>';
    echo '<input id="title" name="title" type="text" maxlength="200" required value="' . h(isset($values['title']) ? $values['title'] : '') . '"></p>';
    echo '<p><label for="author">Автор:</label><br>';
    echo '<input id="author" name="author" type="text" maxlength="100" required value="' . h(isset($values['author']) ? $values['author'] : '') . '"></p>';
    echo '<p><label for="year">Рік видання:</label><br>';
    echo '<input id="year" name="year" type="number" min="1450" max="' . h(date('Y')) . '" required value="' . h(isset($values['year']) ? $values['year'] : '') . '"></p>';
    echo '<button type="submit">Зберегти</button> ';
    echo '<a href="index.php">Скасувати</a>';
    echo '</form>';
    renderFooter();
}
