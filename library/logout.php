<?php
require_once __DIR__ . '/inc/db.php';
session_destroy();
header('Location: index.php');
exit;