<?php
// Email settings now live under System Information > Email (stored in the database).
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();

header('Location: settings.php?tab=email');
exit();
