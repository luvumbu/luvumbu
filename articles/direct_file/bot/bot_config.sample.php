<?php
/**
 * Configuration du bot Claude.
 *
 * 1. Copiez ce fichier en "bot_config.php" (même dossier).
 * 2. Collez votre clé API dans 'api_key'.
 * 3. Mettez le(s) code(s) de discussion à surveiller dans 'codes'.
 *
 * NE PARTAGEZ JAMAIS votre clé API. Ne la mettez pas sur GitHub.
 */
return [
    // Clé API Anthropic (https://console.anthropic.com → API Keys). Commence par "sk-ant-".
    'api_key' => 'COLLEZ_VOTRE_CLE_ICI',

    // Modèle. Haiku = le moins cher, parfait pour un chat.
    'model'   => 'claude-haiku-4-5',

    // Codes des discussions où le bot doit répondre (ex: ['VG661']).
    // Laissez [] pour répondre dans TOUTES les discussions ouvertes.
    'codes'   => ['VG661'],

    // Pseudo affiché du bot.
    'pseudo'  => 'Claude 🤖',

    // Identité réservée du bot (sert à ne jamais répondre à ses propres messages).
    // Ne pas changer sauf raison précise.
    'bot_ip'  => 'BOT',

    // Combien de messages d'historique envoyer à Claude pour le contexte.
    'history' => 20,

    // Consigne de personnalité / comportement du bot.
    'system'  => "Tu es Claude, un participant sympathique d'un chat de groupe en français. "
               . "Réponds de façon courte, naturelle et utile, comme dans une vraie conversation. "
               . "Plusieurs personnes peuvent parler ; chaque message est préfixé par le pseudo de son auteur. "
               . "Tu réponds au(x) dernier(s) message(s). N'invente pas de pseudo, ne préfixe pas ta réponse par ton nom.",

    // Longueur max d'une réponse (en tokens).
    'max_tokens' => 400,
];
