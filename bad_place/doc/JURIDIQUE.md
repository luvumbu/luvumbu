# Dispositif juridique

> ⚠️ **Avertissement.** Les pages légales fournies sont des **modèles génériques** (champs entre crochets à compléter). Elles **doivent être relues et validées par un professionnel du droit** avant toute mise en ligne réelle. Ce document décrit le dispositif technique mis en place, pas un conseil juridique.

## Principe directeur

**Informer sur des tendances, jamais accuser sans nuance.** L'application présente des *témoignages*, pas des faits établis, et affiche un **indice de vigilance** plutôt qu'un jugement.

## 1. Nature des contenus

- Vocabulaire neutre : « témoignage », « non vérifié », « indice de vigilance ».
- Clause de non-responsabilité affichée sur les pages clés (accueil, carte, formulaire).
- L'indice 🟢🟡🔴 reflète le **nombre de témoignages**, pas une conclusion sur l'établissement.

## 2. Modération (charte)

- Compte obligatoire pour publier.
- Statuts transparents : `en attente` → `publié` / `rejeté`, puis `masqué` / `retiré` possible.
- En dev : `REPORTS_AUTO_PUBLISH=true`. En prod : `false` (passage par la file de modération).
- Journal d'audit des actions (`moderation_actions`).

## 3. Droit de réponse (LCEN)

- Page `pages/contestation.html` + endpoint `POST /api/v1/contestations`.
- Accessible **sans compte** (les organisations n'en ont pas forcément).
- L'adresse email du requérant est **chiffrée au repos** (AES-256-GCM).
- Rattachement possible à un signalement précis ; référence de suivi générée.

## 4. Signalement de contenu illicite (LCEN)

- Endpoint `POST /api/v1/reports/{uuid}/abuse`.
- Motifs : diffamation, fausse information, haine, spam, atteinte à la vie privée, autre.
- Alimente la file de modération (`abuse_reports`).

## 5. RGPD

| Exigence | Mise en œuvre |
|----------|---------------|
| Base légale & finalités | Décrites dans `confidentialite.html` |
| Consentement | Enregistré à l'inscription (table `consents`, horodaté) |
| Anonymat public | `is_anonymous` : nom masqué publiquement, identité connue en interne |
| Minimisation | IP **pseudonymisée** (HMAC), jamais stockée en clair |
| Chiffrement | Données sensibles chiffrées au repos ; mots de passe hachés |
| Droits (accès, effacement, portabilité) | Table `rgpd_requests` (traitement à finaliser côté admin) |
| Cookies | Bandeau de consentement ; uniquement du stockage fonctionnel, aucun traçage |

## 6. Lutte contre les abus

- Compte requis pour publier.
- Unicité des votes (par utilisateur **et** par IP pseudonymisée).
- Rate-limiting par IP et par endpoint.
- Dédup des entités et détection des doublons (à renforcer avec l'IA en évolution future).

## Pages légales fournies

| Page | Fichier |
|------|---------|
| Mentions légales | `public/pages/mentions-legales.html` |
| CGU | `public/pages/cgu.html` |
| Politique de confidentialité (RGPD) | `public/pages/confidentialite.html` |
| Charte de modération | `public/pages/charte-moderation.html` |
| Droit de réponse (formulaire) | `public/pages/contestation.html` |
