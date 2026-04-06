<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';

requirePost();
logoutUser();

success(['message' => 'Logged out']);
