<?php
require_once __DIR__ . '/../catalog_common.php';

$values = array('title' => '', 'author' => '', 'year' => '');
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array(
        'title' => isset($_POST['title']) && is_string($_POST['title']) ? trim($_POST['title']) : '',
        'author' => isset($_POST['author']) && is_string($_POST['author']) ? trim($_POST['author']) : '',
        'year' => isset($_POST['year']) && is_string($_POST['year']) ? trim($_POST['year']) : ''
    );

    if (!checkCsrf()) {
        $errors[] = 'Перевірка безпеки не пройдена. Оновіть форму й спробуйте ще раз.';
    } else {
        $checked = validateBook($values);
        $errors = $checked['errors'];
        try {
            if (!$errors) {
                $books = readBooks('json');
                $book = $checked['book'];
                $book['id'] = generateBookId($books);
                $books[] = $book;
                saveBooks('json', $books);
                $saved = readBooks('json');
                $found = false;
                foreach ($saved as $item) {
                    if ($item['id'] === $book['id']) { $found = true; break; }
                }
                if (!$found) { throw new RuntimeException('Не вдалося підтвердити збереження запису.'); }
                header('Location: index.php', true, 303);
                exit;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
renderBookForm('json', 'Додавання книжки', $values, $errors);
