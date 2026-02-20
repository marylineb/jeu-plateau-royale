# 🌸 Sakura no Ban — 桜の盤

> *Plateau de cerisier* — Un jeu d'échecs & dames au thème japonais, développé en PHP pur.

![PHP](https://img.shields.io/badge/PHP-8.0+-C4607A?style=flat-square&logo=php&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Animations-F2A7BB?style=flat-square&logo=css3&logoColor=white)
![Sessions](https://img.shields.io/badge/Sessions-PHP-8B1A2F?style=flat-square)
![Licence](https://img.shields.io/badge/Licence-Pédagogique-FDE8EF?style=flat-square)

---

## ✨ Présentation

**Sakura no Ban** est un jeu de plateau jouable dans le navigateur, développé sans framework ni base de données. L'interface s'inspire de l'esthétique japonaise — papier washi, laque rouge, pétales de cerisier — avec une pluie de pétales SVG générés et animés entièrement en PHP + CSS.

Le projet couvre deux jeux classiques :

| Jeu | Plateau | Règles |
|-----|---------|--------|
| ♟ Échecs | 8 × 8 | Règles de base (roi, dame, tour, fou, cavalier, pion) |
| ⬤ Dames | 10 × 10 | Règles françaises (prise obligatoire, promotion) |

---

## 🎮 Fonctionnalités

- **2 jeux** sélectionnables depuis la page d'accueil
- **2 modes de jeu** — 2 joueurs sur le même écran ou solo contre l'IA
- **IA fonctionnelle** — stratégie de capture prioritaire, coups aléatoires sinon
- **Prise obligatoire** respectée aux dames
- **Promotion** automatique (pion → dame aux dames / pion → reine aux échecs)
- **Détection de fin de partie** — échec et mat, pat, victoire aux dames
- **Historique des coups** affiché en notation algébrique
- **Pluie de pétales SVG** sur l'accueil — zéro JavaScript, 100% PHP + CSS
- **Design responsive** — jouable sur mobile
- Interface **thème Sakura** 🌸 — rose, laque, washi, typographie japonisante

---

## 🛠️ Technologies

| Technologie | Usage |
|-------------|-------|
| **PHP 8+** | Logique de jeu, sessions, génération des pétales |
| **HTML5** | Structure, SVG inline pour les pétales |
| **CSS3** | Thème, animations `@keyframes`, CSS Grid, variables custom |
| **JavaScript** | Uniquement pour la synchro visuelle des boutons radio (3 lignes) |
| **Sessions PHP** | Stockage de l'état complet du plateau entre chaque requête |

> ⚠️ Pas de base de données, pas de framework, pas de dépendances npm.

---

## 📁 Structure du projet

```
sakura-no-ban/
│
├── index.php      ← Accueil : choix du jeu, du mode, pluie de pétales
├── game.php       ← Moteur de jeu (plateau, règles, IA, rendu HTML)
├── ai.php         ← Fonctions de l'IA (inclus par game.php)
├── reset.php      ← Réinitialise la session et redirige
├── style.css      ← Thème Sakura complet
└── README.md
```

---

## 🚀 Installation

### Prérequis
- PHP 8.0 ou supérieur
- Un serveur local (XAMPP, WAMP, Laragon) **ou** le serveur intégré PHP

### Lancer le projet

```bash
# Cloner le dépôt
git clone https://github.com/ton-username/sakura-no-ban.git
cd sakura-no-ban

# Lancer le serveur PHP intégré
php -S localhost:8000
```

Puis ouvrir dans le navigateur :

```
http://localhost:8000
```

### Avec XAMPP / WAMP / Laragon

Copier le dossier dans `htdocs/` (XAMPP) ou `www/` (WAMP), puis accéder à :

```
http://localhost/sakura-no-ban
```

---

## 🧠 Concepts techniques abordés

### Gestion d'état sans base de données

PHP est stateless — il repart de zéro à chaque requête. Tout l'état du jeu (plateau, tour, pièces capturées, historique) est stocké en `$_SESSION` et persisté entre les clics.

### Pattern PRG (Post / Redirect / Get)

Chaque clic sur une case envoie un `POST` → PHP traite le coup → `header('Location: game.php')` → le navigateur fait un `GET`. Cela empêche le rejeu du coup au `F5`.

```
Joueur clique → POST → PHP traite → redirect → GET → page affichée
```

### Pétales SVG sans JavaScript

PHP génère 24 éléments `<span>` avec des propriétés aléatoires injectées comme variables CSS custom (`--x`, `--delay`, `--duration`, `--sway`…). Les `@keyframes` CSS lisent ces variables pour animer chaque pétale indépendamment. Zéro JavaScript pour les animations.

```php
// PHP génère, CSS anime
$petals[] = [
    'x'        => rand(0, 100),        // position horizontale aléatoire
    'delay'    => rand(0, 140) / 10,   // décalage avant apparition
    'duration' => rand(80, 160) / 10,  // vitesse de chute
    // ...
];
```

### IA (niveau débutant)

L'IA liste tous ses coups possibles, priorise les captures (stratégiquement meilleur), puis choisit aléatoirement parmi les options restantes. Pas de calcul de profondeur (pas de minimax).

---

## 🎯 Ce que ce projet m'a appris

- Gérer l'**état d'une application** sans base de données via les sessions PHP
- Implémenter une **logique de jeu complexe** (règles d'échecs, prise obligatoire, détection d'échec et mat)
- Maîtriser les **animations CSS** avec variables custom et `@keyframes`
- Concevoir une **interface responsive** avec CSS Grid
- Appliquer le pattern **PRG** pour éviter les duplications de requêtes
- Créer une **IA simple** basée sur des heuristiques de priorité

---

## 👩🏽‍💻 Autrice

**Maryline Biloa**
Étudiante en développement web — passionnée par le frontend, le game design et les projets créatifs 💖

---

## 📄 Licence

Projet personnel à but pédagogique.
Libre d'utilisation pour l'apprentissage.