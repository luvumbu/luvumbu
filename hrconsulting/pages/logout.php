<?php
require_once __DIR__ . '/../config/bdd.php';
session_unset();
session_destroy();
session_start();
flash_set('success', 'Vous êtes déconnecté.');
header('Location: ' . BASE_URL . 'index.php');
exit;
