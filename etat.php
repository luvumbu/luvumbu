<?php
/* ═══════════════════════════════════════════════════════════════════════
   ÉTAT DU DÉPLOIEMENT — à ouvrir après chaque mise en ligne.

   Le même incident s'est répété trois fois : un fichier de configuration
   volontairement exclu de Git (il porte un mot de passe) n'existe pas sur le
   serveur, l'application démarre avec ses valeurs de développement, et casse
   — parfois discrètement, des semaines plus tard, sur une page qu'on n'avait
   pas rouverte depuis.

   Un « git push » ne peut PAS emporter ces fichiers : c'est voulu. Ce qui
   manquait, c'est un endroit qui dise lesquels sont absents. Cette page ne
   fait que ça : elle regarde le disque du serveur, app par app, et renvoie
   vers l'assistant de chacune.

   Volontairement bête et incassable : aucune connexion à une base, aucun
   bootstrap d'application. Elle doit répondre même quand tout le reste est
   par terre.
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

session_start();

/* Réservé à l'admin : la liste des fichiers manquants est une carte des
   faiblesses du serveur. On réutilise la session posée par admin.php, qui sait
   se rabattre sur les identifiants MySQL quand le SSO n'est pas actif. */
if (empty($_SESSION['pf_admin'])) {
    header('Location: admin.php');
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────
   Ce que chaque application attend sur le serveur.

   'fichiers'  chemin (depuis la racine du site) => description
   'optionnel' fichiers dont l'absence dégrade sans casser
   'assistant' page web qui sait le créer (null = à déposer à la main)
   ───────────────────────────────────────────────────────────────────────── */
$APPS = [
    [
        'nom'       => 'Luvumbu ID — connexion unique',
        'url'       => 'sso/',
        'assistant' => 'sso/install.php',
        'fichiers'  => ['sso/secret.local.php' => 'clé de signature des jetons — sans elle, aucune connexion possible'],
        'optionnel' => ['sso/accounts.local.php' => 'annuaire des comptes (se crée à la première connexion)'],
        'note'      => 'Tant que ce fichier manque, admin.php et le gestionnaire retombent sur leur ancien formulaire.',
    ],
    [
        'nom'       => 'Espace admin + gestionnaire de fichiers',
        'url'       => '_gestion/',
        'assistant' => null,
        'fichiers'  => [],
        'optionnel' => [
            '_gestion/apikey.local.php'   => 'clé d’API (lecture/écriture des fichiers à distance)',
            '_gestion/password.local.php' => 'mot de passe propre au gestionnaire',
        ],
    ],
    [
        'nom'       => 'PhotoSync',
        'url'       => 'dropbox/public_html/web/gallery.php',
        'assistant' => 'dropbox/public_html/install.php',
        'fichiers'  => ['dropbox/public_html/lib/db.config.php' => 'identifiants MySQL'],
    ],
    [
        'nom'       => 'DualCam',
        'url'       => 'DualCam/web/dualcam.php',
        'assistant' => 'DualCam/install.php',
        'fichiers'  => ['DualCam/lib/db.config.php' => 'identifiants MySQL'],
        'note'      => 'Ce fichier est versionné : le mot de passe MySQL est donc lisible sur GitHub. À sortir du dépôt et à changer.',
    ],
    [
        'nom'       => 'Blog / articles',
        'url'       => 'articles/blog/',
        'assistant' => 'articles/blog/install.php',
        'fichiers'  => ['articles/blog/config/config.php' => 'identifiants MySQL'],
    ],
    [
        'nom'       => 'CV Luvumbu',
        'url'       => 'cv_luvumbu/',
        'assistant' => 'cv_luvumbu/install.php',
        'fichiers'  => ['cv_luvumbu/config/config.php' => 'identifiants MySQL'],
        'optionnel' => ['cv_luvumbu/includes/google_secrets.local.php' => 'connexion Google propre à l’app'],
    ],
    [
        'nom'       => 'Compétitions d’athlétisme',
        'url'       => 'ATHLE_COMPETITION/',
        'assistant' => 'ATHLE_COMPETITION/install.php',
        'fichiers'  => ['ATHLE_COMPETITION/config/config.local.php' => 'identifiants MySQL'],
    ],
    [
        'nom'       => 'Athlétisme (app)',
        'url'       => 'athletisme_app/',
        'assistant' => 'athletisme_app/install.php',
        'fichiers'  => ['athletisme_app/config/config.local.php' => 'identifiants MySQL'],
    ],
    [
        'nom'       => 'Tamagotchi',
        'url'       => 'tamagotchi/public/',
        'assistant' => 'tamagotchi/public/install.php',
        'fichiers'  => ['tamagotchi/config/config.php' => 'réglages du jeu + identifiants MySQL'],
    ],
    [
        'nom'       => 'RPN',
        'url'       => 'rpn/',
        'assistant' => null,
        'fichiers'  => [
            'rpn/config/config.php' => 'configuration générale',
            'rpn/config/db.php'     => 'identifiants MySQL',
        ],
    ],
    [
        'nom'       => 'Bad Place',
        'url'       => 'bad_place/',
        'assistant' => null,
        'fichiers'  => ['bad_place/config/.env' => 'variables d’environnement (base, clés)'],
    ],
    [
        'nom'       => 'Anniversaire',
        'url'       => 'anniversaire/',
        'assistant' => null,
        'fichiers'  => ['anniversaire/config.php' => 'configuration (comptes, Google)'],
    ],
];

/* ─────────────────────────────────────────────────────────────────────────
   Contrôle
   ───────────────────────────────────────────────────────────────────────── */

$root = __DIR__;

/** @return array{etat:string,detail:string} */
function verifier(string $root, string $rel, bool $requis): array
{
    $abs = $root . '/' . $rel;
    if (is_file($abs)) {
        $taille = @filesize($abs);
        return ['etat' => 'ok', 'detail' => 'présent' . ($taille !== false ? ' (' . $taille . ' o)' : '')];
    }
    return ['etat' => $requis ? 'ko' : 'warn', 'detail' => 'absent'];
}

$resultats = [];
$nbBloquants = 0;
$nbAvertissements = 0;

foreach ($APPS as $app) {
    $deployee = is_dir($root . '/' . explode('/', trim($app['url'], '/'))[0]);
    $lignes = [];

    foreach ($app['fichiers'] ?? [] as $rel => $desc) {
        $v = verifier($root, $rel, true);
        if ($v['etat'] === 'ko') $nbBloquants++;
        $lignes[] = ['rel' => $rel, 'desc' => $desc, 'requis' => true] + $v;
    }
    foreach ($app['optionnel'] ?? [] as $rel => $desc) {
        $v = verifier($root, $rel, false);
        if ($v['etat'] === 'warn') $nbAvertissements++;
        $lignes[] = ['rel' => $rel, 'desc' => $desc, 'requis' => false] + $v;
    }

    $resultats[] = $app + ['deployee' => $deployee, 'lignes' => $lignes];
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>État du déploiement — luvumbu.com</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#0e1526;color:#eaf0ff;padding:26px 20px;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  .wrap{max-width:900px;margin:0 auto}
  h1{font-size:1.3rem;margin:0 0 6px}
  .sub{color:#9fb0d0;font-size:.9rem;margin:0 0 22px;line-height:1.55}
  .verdict{padding:14px 16px;border-radius:12px;margin-bottom:22px;font-size:.92rem;line-height:1.5}
  .verdict.ok{background:rgba(62,201,138,.13);border:1px solid #3ec98a;color:#a8f0cd}
  .verdict.ko{background:rgba(255,93,108,.12);border:1px solid #ff5d6c;color:#ffb0b8}
  .app{background:#151d31;border:1px solid #2a3450;border-radius:14px;padding:16px 18px;margin-bottom:14px}
  .app.absent{opacity:.5}
  .app h2{font-size:1rem;margin:0 0 3px;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
  .app .links{font-size:.82rem;color:#7d8db0;margin:0 0 12px}
  .app .links a{color:#8fb0ff;margin-right:12px}
  .pill{font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:600}
  .pill.ok{background:rgba(62,201,138,.18);color:#7ee2a8}
  .pill.ko{background:rgba(255,93,108,.18);color:#ffb0b8}
  .pill.warn{background:rgba(255,184,77,.16);color:#ffca7a}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  td{padding:6px 6px;border-bottom:1px solid #21294a;vertical-align:top}
  td.f{font-family:ui-monospace,Consolas,monospace;font-size:.82rem;color:#c3d0ec;width:42%;word-break:break-all}
  td.s{width:13%;white-space:nowrap}
  td.d{color:#8b98bd}
  .ok{color:#7ee2a8}.ko{color:#ffb0b8}.warn{color:#ffca7a}
  .note{margin-top:11px;font-size:.82rem;color:#ffca7a;line-height:1.5}
  .foot{color:#6e7a99;font-size:.8rem;margin-top:24px;line-height:1.6}
  a{color:#8fb0ff}
</style>
</head>
<body>
<div class="wrap">
  <h1>État du déploiement</h1>
  <p class="sub">
    Les fichiers ci-dessous portent des mots de passe : ils sont exclus de Git exprès, donc
    <b>un « git push » ne les emporte jamais</b>. Après chaque mise en ligne, cette page dit
    lesquels manquent sur le serveur — avant que tu ne le découvres sur une page cassée.
  </p>

  <?php if ($nbBloquants === 0): ?>
    <div class="verdict ok">✓ Rien ne manque.
      <?= $nbAvertissements ? $nbAvertissements . ' fichier(s) facultatif(s) absent(s) — voir plus bas.' : '' ?></div>
  <?php else: ?>
    <div class="verdict ko">✗ <b><?= $nbBloquants ?></b> fichier(s) indispensable(s) manquant(s) :
      l'application concernée ne fonctionne pas, ou tourne avec ses réglages de développement.</div>
  <?php endif; ?>

  <?php foreach ($resultats as $a): ?>
    <?php
      $pires = array_column($a['lignes'], 'etat');
      $etat  = in_array('ko', $pires, true) ? 'ko' : (in_array('warn', $pires, true) ? 'warn' : 'ok');
      $libelle = ['ok' => 'configurée', 'warn' => 'incomplète', 'ko' => 'non configurée'][$etat];
    ?>
    <div class="app<?= $a['deployee'] ? '' : ' absent' ?>">
      <h2><?= $h($a['nom']) ?>
        <?php if (!$a['deployee']): ?><span class="pill warn">pas sur ce serveur</span>
        <?php elseif ($a['lignes']): ?><span class="pill <?= $etat ?>"><?= $libelle ?></span><?php endif; ?>
      </h2>
      <p class="links">
        <a href="<?= $h($a['url']) ?>">ouvrir l'application</a>
        <?php if (!empty($a['assistant'])): ?>
          <?php if (is_file($root . '/' . $a['assistant'])): ?>
            <a href="<?= $h($a['assistant']) ?>">assistant de configuration</a>
          <?php else: ?>
            <span>assistant absent du serveur</span>
          <?php endif; ?>
        <?php else: ?>
          <span>pas d'assistant — fichier à déposer à la main</span>
        <?php endif; ?>
      </p>

      <?php if ($a['lignes']): ?>
      <table>
        <?php foreach ($a['lignes'] as $l): ?>
        <tr>
          <td class="f"><?= $h($l['rel']) ?></td>
          <td class="s <?= $l['etat'] ?>">
            <?= $l['etat'] === 'ok' ? '✓ présent' : ($l['requis'] ? '✗ absent' : '– absent') ?>
          </td>
          <td class="d"><?= $h($l['desc']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>

      <?php if (!empty($a['note'])): ?><p class="note">⚠️ <?= $h($a['note']) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <p class="foot">
    Serveur : <?= $h($_SERVER['HTTP_HOST'] ?? '?') ?> · PHP <?= $h(PHP_VERSION) ?> ·
    racine <code><?= $h($root) ?></code><br>
    Un fichier manquant se règle soit par l'assistant de l'application, soit en le copiant
    depuis ta machine (gestionnaire de fichiers Hostinger ou FTP) — jamais par un commit.
  </p>
</div>
</body>
</html>
