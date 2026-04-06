<?php
require_once __DIR__ . '/core/db.php';

var_dump(config('db.host'));
var_dump(config('db.port'));
var_dump(config('db.database'));
var_dump(config('db.username'));

try {
    db();
    echo 'Connected';
} catch (Throwable $e) {
    echo $e->getMessage();
}
