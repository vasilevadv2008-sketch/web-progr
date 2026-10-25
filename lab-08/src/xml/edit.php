<?php
require_once __DIR__ . '/../catalog_common.php';

$id = '';
if (isset($_GET['id']) && is_string($_GET['id'])) {
    $id = $_GET['id'];
} elseif (isset($_POST['id']) && is_string($_POST['id'])) {
    $id = $_POST['id'];
}
if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
    renderHeader('Редагування книжки');
    echo '<p role="alert">Невірний ID.</p><p><a href="index.php">Повернутися до каталогу</a></p>';
    renderFooter();
    exit;
}

try {
    $books = readBooks('xml');
} catch (Exception $e) {
    renderHeader('Редагування книжки');
    echo '<p role="alert">' . h($e->getMessage()) . '</p><p><a href="index.php">Повернутися до каталогу</a></p>';
    renderFooter();
    exit;
}

$index = -1;
foreach ($books as $i => $book) {
    if ($book['id'] === $id) { $index = $i; break; }
}
if ($index < 0) {
    renderHeader('Редагування книжки');
    echo '<p role="alert">Запис із таким ID не знайдено.</p><p><a href="index.php">Повернутися до каталогу</a></p>';
    renderFooter();
    exit;
}

$values = array('title' => $books[$index]['title'], 'author' => $books[$index]['author'], 'year' => (string)$books[$index]['year']);
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
        if (!$errors) {
            try {
                $books = readBooks('xml');
                $index = -1;
                foreach ($books as $i => $book) {
                    if ($book['id'] === $id) { $index = $i; break; }
                }
                if ($index < 0) { throw new RuntimeException('Запис більше не існує.'); }
                $books[$index]['title'] = $checked['book']['title'];
                $books[$index]['author'] = $checked['book']['author'];
                $books[$index]['year'] = $checked['book']['year'];
                saveBooks('xml', $books);
                readBooks('xml');
                header('Location: index.php', true, 303);
                exit;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
renderBookForm('xml', 'Редагування книжки', $values, $errors, $id);
