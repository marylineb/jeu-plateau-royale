# ♟ Plateau Royal — Échecs & Dames en PHP

> Jeu de plateau multi-modes en PHP procédural pur + CSS Grid.  
> Sans JavaScript (sauf UI cosmétique), sans base de données.

---

## 📁 Structure

```
jeu/
├── index.php    ← Accueil : choix du jeu et du mode
├── game.php     ← Moteur de jeu (plateau, règles, IA, rendu)
├── ai.php       ← Fonctions de l'IA (inclus par game.php)
├── reset.php    ← Réinitialise la session et redirige
└── style.css    ← Thème Bois & Marbre
```

---

## 🚀 Lancement

```bash
php -S localhost:8000
# puis ouvrir http://localhost:8000/index.php
```

---

## 🧠 Architecture et logique

### PHP stateless → tout dans `$_SESSION`

PHP ne "mémorise" rien entre deux requêtes. Chaque clic sur une case envoie un POST, PHP repart de zéro. La solution : stocker **tout l'état du jeu** en session :

```php
$_SESSION['plateau']    // tableau 2D représentant le plateau
$_SESSION['tour']       // 'B' ou 'N'
$_SESSION['selected']   // case sélectionnée [r, c] ou null
$_SESSION['historique'] // liste des coups en notation simplifiée
$_SESSION['fin']        // message de fin ou null
$_SESSION['captures']   // pièces capturées par chaque camp
$_SESSION['last_move']  // dernier coup pour le surlignage doré
```

### Pattern PRG (Post/Redirect/Get)

Chaque action (clic) est un POST → PHP traite → `header('Location: game.php')` → le navigateur fait un GET.

```
Joueur clique → POST game.php → PHP traite → redirect → GET game.php → HTML affiché
```

Cela évite le problème classique "F5 rejoue le dernier coup".

---

## ♟ Logique des Échecs

### Représentation du plateau

Tableau PHP 8x8. Chaque case = `null` (vide) ou une chaîne de 2 caractères :

```
'B' ou 'N'  +  'K','Q','R','B','N','P'
 couleur        Roi Dame Tour Fou Cavalier Pion
```

Exemples : `'BQ'` = Dame Blanche, `'NP'` = Pion Noir, `'BR'` = Tour Blanche.

### Calcul des mouvements (`getMouvementsEchecs`)

Chaque type de pièce a sa propre logique :

| Pièce | Logique |
|-------|---------|
| Pion | Avance selon sa direction, capture en diagonal |
| Tour | Glisse horizontalement/verticalement jusqu'à obstacle |
| Fou | Glisse en diagonal jusqu'à obstacle |
| Dame | Tour + Fou combinés |
| Roi | 1 case dans toutes les directions |
| Cavalier | Saut en L (2+1), passe par-dessus les pièces |

Les pièces "glissantes" (Tour, Fou, Dame) utilisent une boucle `while` qui s'arrête dès qu'elle rencontre une pièce :

```php
while ($ajout($tr, $tc)) { $tr += $dr; $tc += $dc; }
// $ajout retourne true si case vide (peut continuer), false si bloqué
```

### Légalité des coups (mouvements filtrés)

Un coup est **légal** s'il ne laisse pas son propre Roi en échec. Pour vérifier :

```php
// 1. Simuler le coup sur une copie du plateau
$sim = $board;
$sim[$tr][$tc] = $sim[$r][$c];
$sim[$r][$c]   = null;

// 2. Vérifier si le Roi est en échec sur la simulation
if (!estEnEchec($sim, $couleur)) {
    $legaux[] = [$tr, $tc]; // coup légal
}
```

`estEnEchec` parcourt toutes les pièces adverses et vérifie si l'une d'elles peut atteindre le Roi.

### Échec et mat / Pat

```php
function etatFinEchecs(array $board, string $couleur): ?string {
    // Chercher si la couleur a au moins 1 coup légal
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if (!empty(getMouvementsLegaux($board, $r, $c, $couleur))) return null;
        }
    }
    // Aucun coup légal →
    return estEnEchec($board, $couleur) ? 'mat' : 'pat';
    //  Roi attaqué = échec et mat     Roi en sécurité = pat (nul)
}
```

---

## ⬤ Logique des Dames (règles françaises, 10x10)

### Plateau 10x10

Seules les **cases noires** sont utilisées : `(r + c) % 2 === 1`.

Les pions se placent sur les 4 premières rangées (Noirs) et 4 dernières (Blancs).

### Prise obligatoire

Aux dames françaises, si une prise est possible, le joueur **doit** prendre. L'application le vérifie avant chaque sélection :

```php
function aPrisesObligatoires(array $board, string $couleur): bool {
    // Parcourir toutes les pièces et chercher une capture disponible
    foreach ($mvs as $mv) {
        if (!empty($mv['capture'])) return true;
    }
    return false;
}
```

Si des prises existent, seules les pièces pouvant prendre sont sélectionnables.

### Promotion

- **Dames** : un pion atteignant la dernière rangée adverse devient Dame (`'BD'` ou `'ND'`).
- **Échecs** : un pion atteignant la rangée opposée devient Dame (`'BQ'` ou `'NQ'`).

---

## 🤖 Intelligence Artificielle

L'IA joue toujours les **Noirs**. Après chaque coup du joueur (Blancs), si `$modeJeu === 'ai'` et `$tour === 'N'`, l'IA calcule et joue immédiatement son coup dans le même cycle PHP.

### Stratégie de l'IA (niveau débutant)

```php
// 1. Lister TOUS les coups possibles des Noirs
// 2. Séparer captures et déplacements normaux
// 3. Préférer une capture si disponible (stratégiquement meilleur)
// 4. Sinon, choisir un coup au hasard

$pool = !empty($captures) ? $captures : $normaux;
return $pool[array_rand($pool)];
```

C'est une IA de **niveau débutant** : elle ne calcule pas les conséquences futures (pas de minimax). Elle évite juste de "gaspiller" des captures.

---

## 🎨 Interface

### CSS Grid pour le plateau

```css
.board-8  { grid-template-columns: repeat(8, 70px); }
.board-10 { grid-template-columns: repeat(10, 60px); }
```

Chaque case est un `<button type="submit">` dans un mini formulaire. Cliquer une case = soumettre un formulaire POST avec `r` et `c`.

### Surligages visuels

| État | Couleur |
|------|---------|
| Sélectionné | Vert |
| Mouvement possible | Point vert semi-transparent |
| Dernier coup joué | Or pâle |
| Roi en échec | Rouge |

### Pourquoi des `<button>` au lieu de `<a>` ?

Les liens `<a>` font des GET. Pour envoyer des données (quelle case a été cliquée), on a besoin de POST. Chaque case est donc un bouton de formulaire.

---

## ➕ Améliorations possibles

| Fonctionnalité                        | Difficulté |
|----------------                       |-----------|
| Roque (échecs)                        | ⭐⭐ |
| En passant (échecs)                   | ⭐⭐⭐ |
| Prises multiples en chaîne (dames)    | ⭐⭐⭐ |
| IA minimax (vraie stratégie).         | ⭐⭐⭐⭐⭐ |
| Sauvegarde de parties (SQLite)        | ⭐⭐ |
| Notation PGN (export).                | ⭐⭐⭐ |
| Chronomètre par joueur                | ⭐⭐ (nécessite JS) |

---

*Plateau Royal — PHP procédural, sessions, CSS Grid.*
