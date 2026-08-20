<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';

unset($_SESSION['admin_id']);
header('Location: /admin/login.php');
exit;
