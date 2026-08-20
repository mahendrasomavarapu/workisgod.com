<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

logout_user();
header('Location: /');
exit;
