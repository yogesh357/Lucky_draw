<?php

require 'db.php';

try {
    $pdo = db();
    echo "✅ Database connected successfully!";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}