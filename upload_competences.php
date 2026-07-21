<?php
/**
 * Script JETABLE — envoie competences.html (+ .php) vers luvumbu.com par FTP.
 * À SUPPRIMER après usage.
 *
 * 1) Remplis FTP_USER et FTP_PASS ci-dessous (identifiants FTP Hostinger).
 * 2) Vérifie FTP_BASE (dossier racine du site sur le serveur).
 * 3) Lance dans un terminal :
 *      C:\xampp\php\php.exe upload_competences.php
 */

// ─── À REMPLIR ────────────────────────────────────────────────
const FTP_HOST = 'ftp.luvumbu.com';    // hôte FTP (souvent ftp.luvumbu.com)
const FTP_USER = 'VOTRE_UTILISATEUR';  // identifiant FTP
const FTP_PASS = 'VOTRE_MOT_DE_PASSE'; // mot de passe FTP
const FTP_PORT = 21;                   // 21 par défaut
const FTP_SSL  = false;                // true si FTPS explicite

// Dossier DISTANT = racine du site (celui qui contient competences.html en ligne).
// Chez Hostinger c'est presque toujours '/public_html'.
const FTP_BASE = '/public_html';
// ──────────────────────────────────────────────────────────────

// Fichiers à envoyer : [local => distant (relatif à FTP_BASE)]
$files = [
    __DIR__ . '/competences.html' => 'competences.html',
    __DIR__ . '/competences.php'  => 'competences.php',
];

echo "Connexion à " . FTP_HOST . " ...\n";
$conn = FTP_SSL ? ftp_ssl_connect(FTP_HOST, FTP_PORT, 15) : ftp_connect(FTP_HOST, FTP_PORT, 15);
if (!$conn) { fwrite(STDERR, "ERREUR: connexion FTP impossible.\n"); exit(1); }
if (!ftp_login($conn, FTP_USER, FTP_PASS)) { fwrite(STDERR, "ERREUR: identifiants FTP refusés.\n"); ftp_close($conn); exit(1); }
ftp_pasv($conn, true);
echo "Connecté.\n";

$ok = 0;
foreach ($files as $local => $remoteRel) {
    if (!is_file($local)) { echo "  IGNORÉ (introuvable en local) : $local\n"; continue; }
    $remote = rtrim(FTP_BASE, '/') . '/' . $remoteRel;
    if (ftp_put($conn, $remote, $local, FTP_BINARY)) {
        echo "  ✔ envoyé : $remote\n";
        $ok++;
    } else {
        echo "  ✗ ÉCHEC : $remote  (vérifie FTP_BASE / droits)\n";
    }
}
ftp_close($conn);
echo "Terminé : $ok/" . count($files) . " fichier(s) envoyé(s).\n";
echo "Pense à SUPPRIMER upload_competences.php ensuite.\n";
