# Circuit - Arduino Bouton + LED

## Description

Page web educative en PHP qui explique comment brancher et programmer un bouton poussoir avec une LED sur Arduino Uno. Le site couvre le materiel, le cablage, les schemas visuels (SVG inline) et 4 programmes Arduino progressifs.

## Stack

- **Backend** : PHP (XAMPP)
- **Frontend** : HTML, CSS, JS vanilla, SVG inline
- **Serveur** : Apache via XAMPP (`http://localhost/circuit/`)

## Structure

```
circuit/
  index.php    # Page unique contenant tout le contenu
  CLAUDE.md    # Ce fichier
```

## Contenu de la page

1. **Objectif** - Allumer une LED avec un bouton
2. **Materiel** - Liste des composants (Arduino Uno, bouton, LED, resistance 220 Ohm)
3. **Combinaisons possibles** - Table PHP generee (2^n combinaisons pour n pins)
4. **Bouton** - Numerotation des broches (B1-B4) et connexions internes
5. **Branchement** - Instructions de cablage + identification LED+/LED-
6. **Schemas** - Schema logique (texte) + schema visuel (SVG)
7. **4 programmes Arduino** :
   - Programme 1 : LED allumee tant qu'on appuie
   - Programme 2 : Toggle (appui = allume, re-appui = eteint)
   - Programme 3 : Double appui = clignotement
   - Programme 4 : Appui long (2s) = cycle 5s allume / 1s eteint

### Circuit 2 : Bouton + 2 LEDs
8. **Objectif** - Controler 2 LEDs avec un seul bouton (sequentiel)
9. **Materiel** - Arduino Uno, bouton, 2 LEDs (rouge + verte), 2 resistances
10. **Branchement** - Pin 2 = bouton, Pin 12 = LED rouge, Pin 13 = LED verte
11. **Schemas** - Schema logique + schema visuel SVG
12. **Tableau des etats** - Cycle : OFF/OFF -> rouge ON -> les 2 ON -> OFF/OFF
13. **Programme** : 2 LEDs sequentielles (1er appui = rouge, 2e = verte aussi, 3e = tout eteint)

## Conventions

- Langue : francais (sans accents dans le code/contenu)
- Theme sombre (Tailwind-like colors : `#0f172a`, `#1e293b`, `#60a5fa`, etc.)
- Coloration syntaxique Arduino via `preg_replace` PHP (mots-cles, fonctions, constantes, nombres, commentaires)
- Bouton "Copier" sur chaque bloc de code avec 3 fallbacks (Clipboard API, execCommand, prompt)
- Code Arduino stocke en heredoc PHP (`<<<'ARDUINO'`) puis transforme en HTML colore

## Commandes

- **Lancer** : Ouvrir `http://localhost/circuit/` dans le navigateur (XAMPP doit etre demarre)
- **Pas de build** : Le PHP est interprete directement par Apache
