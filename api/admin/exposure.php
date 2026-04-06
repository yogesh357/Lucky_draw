<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
requireRole('ADMIN');

success([
    'data' => getExposureSnapshot(),
]);
