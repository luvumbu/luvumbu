<?php
/* LUVUMBU ID — déconnexion globale : efface le cookie partagé.
   ?return=URL pour revenir ensuite (hôte validé). */
require __DIR__ . '/lib.php';
sso_cookie_clear();

$return = (string)($_GET['return'] ?? '');
$host = $return !== '' ? parse_url($return, PHP_URL_HOST) : null;
$allowed = ['luvumbu.com','www.luvumbu.com','bokonzi.com','www.bokonzi.com','localhost','127.0.0.1'];
if ($return !== '' && ($host === null && strpos($return, '/') === 0 || in_array(strtolower((string)$host), $allowed, true))) {
    header('Location: ' . $return);
} else {
    header('Location: index.php');
}
exit;
