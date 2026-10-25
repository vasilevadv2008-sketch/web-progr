<?php
require_once __DIR__ . '/../catalog_common.php';
try {
    $books = readBooks('xml');
    renderBooks('xml', $books);
} catch (Exception $e) {
    renderBooks('xml', array(), $e->getMessage());
}
