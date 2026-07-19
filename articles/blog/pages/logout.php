<?php
require_once __DIR__ . '/../includes/bootstrap.php';

unset($_SESSION['user_id']);
session_regenerate_id(true);

flash_set('success', 'Tu es déconnecté.');
redirect(base_url('index.php'));
