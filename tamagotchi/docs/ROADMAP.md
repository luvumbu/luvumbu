# 🐣 Roadmap — Tamagotchi Éducatif

Boucle de jeu visée :
**Apprendre → gagner des points → acheter → nourrir → faire évoluer → débloquer**

---

## ✅ Déjà en place
- Architecture PHP (API) + JS + MySQL
- Créature : nourrir / jouer / dormir, temps qui passe
- Galerie des évolutions (9 lignées façon planche doodle)

---

## 🔨 Phase 1 — Fondations éducatives  ← EN COURS
- [x] Nouvelles stats : Santé ❤️, Connaissance 🧠 + monnaie Points 💰, Niveau
- [x] Module **Apprendre** : questions générées côté serveur (anti-triche)
  - Niveau 1 : couleurs & formes
  - Niveau 2 : compter
  - Niveau 3 : addition / soustraction
  - Niveau 4 : multiplication / division
- [x] Bonne réponse → points + connaissance + bonheur
- [x] Interface enfant : hub avec 5 gros boutons (Nourrir, Apprendre, Jouer, Boutique, Maison)

## 🛒 Phase 2 — Économie & nutrition  ✅
- [x] Boutique : acheter de la nourriture avec les points
- [x] Chaque aliment : prix, énergie, effet santé/bonheur (6 aliments)
- [x] Équilibre alimentaire : réaction si trop de sucre / bonus repas varié
- [ ] (option) Aliments périssables → reporté

## 🐉 Phase 3 — Évolution & progression
- [ ] Montée de niveau qui débloque les apprentissages
- [ ] Évolution auto de la créature selon connaissance/âge
- [ ] Monde évolutif (terrain → maison → monde magique)

## 👑 Phase 4 — Maîtrise
- [ ] Défis parfaits (0 erreur → 🌟 Point Parfait)
- [ ] Rang Maître + maintien (compteur 30 jours)
- [ ] Boutique Maître (objets permanents, créatures rares)

## 🎨 Transverse
- [ ] Animations & expressions du personnage (😊😴😟🤩)
- [ ] Sons, décorations de la maison
- [ ] Comptes enfants (auth) + suivi parental
