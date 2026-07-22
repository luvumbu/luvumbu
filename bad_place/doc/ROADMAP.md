# Feuille de route

## ✅ Réalisé

1. **Socle technique** — micro-framework PHP, routeur, middlewares, JWT, chiffrement, base de données (20 tables), migrations & seeds.
2. **Comptes** — inscription, connexion, session (access + refresh), RBAC, menu utilisateur.
3. **Signalements** — création réservée aux membres, publication anonyme, entités (dédup), pièces jointes (upload + vignettes), géocodage.
4. **Autocomplétion d'adresse** — via Nominatim (validation de vraies adresses).
5. **Carte interactive** — Leaflet + OSM (local), indice de vigilance 🟢🟡🔴, filtres, heatmap, temps réel.
6. **Front responsive** — design system clair/sombre, accueil piloté par les données.
7. **Cadre juridique** — droit de réponse, signalement de contenu illicite, pages légales (modèles), RGPD, consentement, bandeau cookies.
8. **Liste & détail** — liste filtrable/paginée des signalements + page détail (lieu, cause, médias, chiffres neutres).
9. **Vigilance de zone** — agrégation par ville, cercles 🟢🟡🔴 sur la carte, panneau des zones sous vigilance.
10. **Connexion Google** — OAuth (Google Identity Services) en plus de l'email.

## 🔜 À faire

| Priorité | Fonctionnalité |
|----------|----------------|
| Haute | **Modération / admin** (traiter contestations et signalements d'abus) |
| Moyenne | **Votes & commentaires** (interface) |
| Moyenne | **Expériences positives** (champ `report_type`, vision équilibrée) |
| Moyenne | **Statistiques** (graphiques Chart.js) |
| Moyenne | **RGPD** (export / suppression des données côté utilisateur) |
| Basse | **Notifications** (in-app, email, push) |
| Basse | **OAuth** Google / Apple |
| Basse | **Extension Chrome** (distribution « Load unpacked ») |

## Évolutions futures (cahier des charges)

- Applications Android / iOS
- API publique
- Export CSV / PDF
- IA : détection de doublons et de signalements frauduleux
- Génération automatique de rapports
- Système de badges pour les contributeurs
- Mode hors ligne avec synchronisation
