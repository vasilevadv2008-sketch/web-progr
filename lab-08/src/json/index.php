<?php
require_once __DIR__ . '/../catalog_common.php';
try {
    $books = readBooks('json');
    renderBooks('json', $books);
} catch (Exception $e) {
    renderBooks('json', array(), $e->getMessage());
}
