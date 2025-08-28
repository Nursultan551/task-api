<?php
$db = require __DIR__ . '/db.php';
// Use isolated SQLite DB for tests (no external service required)
$db['dsn'] = 'sqlite:@app/runtime/test.sqlite';
unset($db['username'], $db['password']);

return $db;
