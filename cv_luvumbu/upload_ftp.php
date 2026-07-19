<?php
/**
 * Script JETABLE — envoie les 2 fichiers modifiés vers le site en ligne par FTP.
 * À SUPPRIMER après usage.
 *
 * 1) Remplissez les 3 lignes ci-dessous (FTP_HOST, FTP_USER, FTP_PASS).
 * 2) Vérifiez FTP_BASE (dossier du site sur le serveur qui contient "cv_luvumbu").
 * 3) Lancez :  C:\xampp\php\php.exe upload_ftp.php
 */

// ─── À REMPLIR ────────────────────────────────────────────────
const FTP_HOST = 'ftp.luvumbu.com';   // hôte FTP de votre hébergeur
const FTP_USER = 'VOTRE_UTILISATEUR'; // identifiant FTP
const FTP_PASS = 'VOTRE_MOT_DE_PASSE';// mot de passe FTP
const FTP_PORT = 21;                  // 21 par défaut
const FTP_SSL  = false;               // true si FTP explicite (FTPS)

// Dossier DISTANT qui contient le dossier "cv_luvumbu".
// Souvent : '/'  ou  '/public_html'  ou  '/www'  ou  '/httpdocs'.
const FTP_BASE = '/public_html';
// ──────────────────────────────────────────────────────────────

// Fichiers à envoyer : [local => distant (relatif à FTP_BASE)]
$files = [
    __DIR__ . '/assets/js/cv-builder.js' => 'cv_luvumbu/assets/js/cv-builder.js',
    __DIR__ . '/cv_builder.php'          => 'cv_luvumbu/cv_builder.php',
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
        echo "  �‑ ÉCHEC : $remote  (le dossier distant existe-t-il ? vérifiez FTP_BASE)\n";
    }
}
ftp_close($conn);
echo "Terminé : $ok/" . count($files) . " fichier(s) envoyé(s).\n";
echo "Pensez à SUPPRIMER upload_ftp.php ensuite.\n";
