<?php
/**
 * GAME.PHP — Moteur de jeu principal
 * Gère : Échecs (8x8) et Dames françaises (10x10)
 * Modes : PvP (2 joueurs) et PvA (contre IA)
 *
 * ARCHITECTURE :
 * Tout l'état du jeu est stocké en $_SESSION.
 * Chaque action (clic cellule) est un POST → redirect (PRG pattern).
 * Cela évite le rechargement dupliqué des actions.
 */

session_start();
require_once 'ai.php';

// ── INITIALISATION DES PARAMÈTRES ────────────────────────────────────────────
$jeu  = $_GET['jeu']  ?? $_SESSION['jeu']  ?? 'echecs';
$mode = $_GET['mode'] ?? $_SESSION['mode'] ?? 'pvp';

// Si on reçoit un nouveau jeu/mode via GET, on réinitialise
if (isset($_GET['jeu'])) {
    unset($_SESSION['plateau'], $_SESSION['selected'], $_SESSION['historique'],
          $_SESSION['tour'], $_SESSION['fin'], $_SESSION['captures'],
          $_SESSION['last_move'], $_SESSION['message']);
    $_SESSION['jeu']  = $jeu;
    $_SESSION['mode'] = $mode;
}

// ── FONCTIONS : PLATEAU ───────────────────────────────────────────────────────

/**
 * Génère le plateau initial des ÉCHECS (8x8)
 * Notation : "BK" = Blanc Roi, "NP" = Noir Pion, etc.
 * Première lettre : couleur (B/N)
 * Deuxième lettre : pièce (K=roi, Q=dame, R=tour, B=fou, N=cavalier, P=pion)
 */
function plateauEchecsInitial(): array {
    $b = array_fill(0, 8, array_fill(0, 8, null));

    $ordre = ['R','N','B','Q','K','B','N','R'];
    for ($c = 0; $c < 8; $c++) {
        $b[0][$c] = 'N' . $ordre[$c]; // Noirs rangée 0
        $b[1][$c] = 'NP';             // Pions noirs
        $b[6][$c] = 'BP';             // Pions blancs
        $b[7][$c] = 'B' . $ordre[$c]; // Blancs rangée 7
    }
    return $b;
}

/**
 * Génère le plateau initial des DAMES (10x10)
 * Notation : "BP" = Blanc Pion, "ND" = Noir Dame
 * Les pions se placent sur les cases noires uniquement.
 */
function plateauDamesInitial(): array {
    $b = array_fill(0, 10, array_fill(0, 10, null));

    for ($r = 0; $r < 10; $r++) {
        for ($c = 0; $c < 10; $c++) {
            // Case noire = (r+c) impair
            if (($r + $c) % 2 === 1) {
                if ($r < 4)  $b[$r][$c] = 'NP'; // Noirs : rangées 0-3
                if ($r > 5)  $b[$r][$c] = 'BP'; // Blancs : rangées 6-9
            }
        }
    }
    return $b;
}

// ── INITIALISATION SESSION ────────────────────────────────────────────────────
if (!isset($_SESSION['plateau'])) {
    $_SESSION['plateau']    = $jeu === 'echecs' ? plateauEchecsInitial() : plateauDamesInitial();
    $_SESSION['tour']       = 'B'; // Blancs commencent
    $_SESSION['selected']   = null;
    $_SESSION['historique'] = [];
    $_SESSION['fin']        = null;
    $_SESSION['captures']   = ['B' => [], 'N' => []];
    $_SESSION['last_move']  = null;
    $_SESSION['message']    = null;
    $_SESSION['jeu']        = $jeu;
    $_SESSION['mode']       = $mode;
}

// Raccourcis
$plateau   = &$_SESSION['plateau'];
$tour      = &$_SESSION['tour'];
$selected  = &$_SESSION['selected'];
$fin       = &$_SESSION['fin'];
$captures  = &$_SESSION['captures'];
$lastMove  = &$_SESSION['last_move'];
$message   = &$_SESSION['message'];
$jeuType   = $_SESSION['jeu'];
$modeJeu   = $_SESSION['mode'];
$taille    = $jeuType === 'echecs' ? 8 : 10;

// ── FONCTIONS : MOUVEMENTS ÉCHECS ─────────────────────────────────────────────

/**
 * Retourne toutes les cases où une pièce peut se déplacer (échecs).
 * Ne vérifie PAS l'échec résultant (règles de base).
 */
function getMouvementsEchecs(array $board, int $r, int $c, string $couleur): array {
    $piece = $board[$r][$c];
    $type  = substr($piece, 1, 1);
    $adv   = $couleur === 'B' ? 'N' : 'B';
    $mvs   = [];

    $ajout = function(int $tr, int $tc) use ($board, $couleur, &$mvs) {
        if ($tr < 0 || $tr > 7 || $tc < 0 || $tc > 7) return false;
        $cible = $board[$tr][$tc];
        if ($cible && substr($cible, 0, 1) === $couleur) return false; // bloqué par allié
        $mvs[] = [$tr, $tc];
        return !$cible; // true = case vide (peut continuer en glissant)
    };

    switch ($type) {
        case 'P': // Pion
            $dir = ($couleur === 'B') ? -1 : 1; // Blancs montent (vers 0), Noirs descendent
            $startRow = ($couleur === 'B') ? 6 : 1;
            // Avancer
            if (!$board[$r + $dir][$c] ?? false) {
                $mvs[] = [$r + $dir, $c];
                // Double pas initial
                if ($r === $startRow && !($board[$r + 2*$dir][$c] ?? true)) {
                    $mvs[] = [$r + 2*$dir, $c];
                }
            }
            // Captures en diagonale
            foreach ([-1, 1] as $dc) {
                $tr = $r + $dir; $tc = $c + $dc;
                if ($tr >= 0 && $tr <= 7 && $tc >= 0 && $tc <= 7) {
                    $cible = $board[$tr][$tc];
                    if ($cible && substr($cible, 0, 1) === $adv) {
                        $mvs[] = [$tr, $tc];
                    }
                }
            }
            break;

        case 'R': // Tour — horizontale et verticale
            foreach ([[-1,0],[1,0],[0,-1],[0,1]] as [$dr,$dc]) {
                $tr = $r + $dr; $tc = $c + $dc;
                while ($ajout($tr, $tc)) { $tr += $dr; $tc += $dc; }
            }
            break;

        case 'B': // Fou — diagonales
            foreach ([[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
                $tr = $r + $dr; $tc = $c + $dc;
                while ($ajout($tr, $tc)) { $tr += $dr; $tc += $dc; }
            }
            break;

        case 'Q': // Dame = Tour + Fou
            foreach ([[-1,0],[1,0],[0,-1],[0,1],[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
                $tr = $r + $dr; $tc = $c + $dc;
                while ($ajout($tr, $tc)) { $tr += $dr; $tc += $dc; }
            }
            break;

        case 'K': // Roi — 1 case toutes directions
            foreach ([[-1,0],[1,0],[0,-1],[0,1],[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
                $ajout($r + $dr, $c + $dc);
            }
            break;

        case 'N': // Cavalier — en L
            foreach ([[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]] as [$dr,$dc]) {
                $ajout($r + $dr, $c + $dc);
            }
            break;
    }

    return $mvs;
}

/**
 * Vérifie si le roi de la couleur donnée est en échec.
 */
function estEnEchec(array $board, string $couleur): bool {
    // Trouver le roi
    $kr = $kc = -1;
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if ($board[$r][$c] === $couleur . 'K') { $kr = $r; $kc = $c; break 2; }
        }
    }
    if ($kr === -1) return false;

    // Vérifier si une pièce adverse peut atteindre le roi
    $adv = $couleur === 'B' ? 'N' : 'B';
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if ($board[$r][$c] && substr($board[$r][$c], 0, 1) === $adv) {
                $mvs = getMouvementsEchecs($board, $r, $c, $adv);
                foreach ($mvs as [$tr, $tc]) {
                    if ($tr === $kr && $tc === $kc) return true;
                }
            }
        }
    }
    return false;
}

/**
 * Mouvements légaux (filtrés pour ne pas laisser le roi en échec).
 */
function getMouvementsLegaux(array $board, int $r, int $c, string $couleur): array {
    $candidats = getMouvementsEchecs($board, $r, $c, $couleur);
    $legaux = [];
    foreach ($candidats as [$tr, $tc]) {
        $sim = $board;
        $sim[$tr][$tc] = $sim[$r][$c];
        $sim[$r][$c]   = null;
        if (!estEnEchec($sim, $couleur)) {
            $legaux[] = [$tr, $tc];
        }
    }
    return $legaux;
}

/**
 * Vérifie l'échec et mat ou le pat.
 * Retourne 'mat', 'pat', ou null.
 */
function etatFinEchecs(array $board, string $couleur): ?string {
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if ($board[$r][$c] && substr($board[$r][$c], 0, 1) === $couleur) {
                if (!empty(getMouvementsLegaux($board, $r, $c, $couleur))) return null;
            }
        }
    }
    return estEnEchec($board, $couleur) ? 'mat' : 'pat';
}

// ── FONCTIONS : MOUVEMENTS DAMES ──────────────────────────────────────────────

/**
 * Mouvements d'un pion aux dames.
 * Retourne un tableau de mouvements, chacun avec 'to' et optionnellement 'capture'.
 */
function getMouvementsPionDames(array $board, int $r, int $c, string $couleur): array {
    $adv  = $couleur === 'B' ? 'N' : 'B';
    $dir  = $couleur === 'B' ? -1 : 1; // Blancs montent, Noirs descendent
    $mvs  = [];

    // Avancer (sans prise)
    foreach ([-1, 1] as $dc) {
        $tr = $r + $dir; $tc = $c + $dc;
        if ($tr >= 0 && $tr < 10 && $tc >= 0 && $tc < 10 && !$board[$tr][$tc]) {
            $mvs[] = ['to' => [$tr, $tc]];
        }
    }

    // Prises (dans les 4 diagonales — la prise arrière est permise aux dames françaises)
    foreach ([[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
        $tr = $r + $dr; $tc = $c + $dc; // case adverse
        $lr = $r + 2*$dr; $lc = $c + 2*$dc; // case d'atterrissage
        if ($lr >= 0 && $lr < 10 && $lc >= 0 && $lc < 10) {
            $cible = $board[$tr][$tc] ?? null;
            if ($cible && substr($cible, 0, 1) === $adv && !$board[$lr][$lc]) {
                $mvs[] = ['to' => [$lr, $lc], 'capture' => [$tr, $tc]];
            }
        }
    }

    return $mvs;
}

/**
 * Mouvements d'une dame aux dames (glisse sur toute la diagonale).
 */
function getMouvementsDame(array $board, int $r, int $c, string $couleur): array {
    $adv = $couleur === 'B' ? 'N' : 'B';
    $mvs = [];

    foreach ([[-1,-1],[-1,1],[1,-1],[1,1]] as [$dr,$dc]) {
        $tr = $r + $dr; $tc = $c + $dc;
        $captured = null;
        while ($tr >= 0 && $tr < 10 && $tc >= 0 && $tc < 10) {
            $cible = $board[$tr][$tc];
            if ($cible) {
                if (!$captured && substr($cible, 0, 1) === $adv) {
                    // On peut sauter cette pièce
                    $captured = [$tr, $tc];
                    $tr += $dr; $tc += $dc;
                    // Les cases après la prise
                    while ($tr >= 0 && $tr < 10 && $tc >= 0 && $tc < 10 && !$board[$tr][$tc]) {
                        $mvs[] = ['to' => [$tr, $tc], 'capture' => $captured];
                        $tr += $dr; $tc += $dc;
                    }
                }
                break; // bloqué
            } else {
                if (!$captured) $mvs[] = ['to' => [$tr, $tc]]; // déplacement simple
            }
            $tr += $dr; $tc += $dc;
        }
    }
    return $mvs;
}

/**
 * Y a-t-il des prises obligatoires pour cette couleur ?
 */
function aPrisesObligatoires(array $board, string $couleur): bool {
    for ($r = 0; $r < 10; $r++) {
        for ($c = 0; $c < 10; $c++) {
            $piece = $board[$r][$c];
            if (!$piece || substr($piece, 0, 1) !== $couleur) continue;
            $type = substr($piece, 1, 1);
            $mvs  = $type === 'D'
                ? getMouvementsDame($board, $r, $c, $couleur)
                : getMouvementsPionDames($board, $r, $c, $couleur);
            foreach ($mvs as $mv) {
                if (!empty($mv['capture'])) return true;
            }
        }
    }
    return false;
}

/**
 * Vérifie la fin de partie aux dames.
 * Retourne 'victoire' si l'adversaire n'a plus de pièces ou de mouvements, null sinon.
 */
function etatFinDames(array $board, string $couleurAdverse): ?string {
    $aDesCoups = false;
    for ($r = 0; $r < 10; $r++) {
        for ($c = 0; $c < 10; $c++) {
            $piece = $board[$r][$c];
            if (!$piece || substr($piece, 0, 1) !== $couleurAdverse) continue;
            $type = substr($piece, 1, 1);
            $mvs  = $type === 'D'
                ? getMouvementsDame($board, $r, $c, $couleurAdverse)
                : getMouvementsPionDames($board, $r, $c, $couleurAdverse);
            if (!empty($mvs)) { $aDesCoups = true; break 2; }
        }
    }
    return $aDesCoups ? null : 'victoire';
}

// ── TRAITEMENT DES ACTIONS POST ───────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$fin) {
    $action = $_POST['action'] ?? '';

    // ── RESET ─────────────────────────────────────────────────────────────────
    if ($action === 'reset') {
        session_destroy(); session_start();
        header('Location: index.php'); exit;
    }

    // ── CLIC SUR UNE CASE ─────────────────────────────────────────────────────
    if ($action === 'click') {
        $clickR = (int)$_POST['r'];
        $clickC = (int)$_POST['c'];
        $pieceClic = $plateau[$clickR][$clickC];

        if ($jeuType === 'echecs') {
            // ── ÉCHECS : sélection / déplacement ──────────────────────────────
            if ($selected === null) {
                // Sélectionner une pièce de sa couleur
                if ($pieceClic && substr($pieceClic, 0, 1) === $tour) {
                    $selected = [$clickR, $clickC];
                }
            } else {
                [$sr, $sc] = $selected;
                $mouvements = getMouvementsLegaux($plateau, $sr, $sc, $tour);

                // Vérifier si le clic est un mouvement valide
                $estValide = false;
                foreach ($mouvements as [$mr, $mc]) {
                    if ($mr === $clickR && $mc === $clickC) { $estValide = true; break; }
                }

                if ($estValide) {
                    // Capturer la pièce adverse si présente
                    $cible = $plateau[$clickR][$clickC];
                    if ($cible) {
                        $coulAdv = substr($cible, 0, 1);
                        $captures[$tour][] = $cible;
                    }

                    // Déplacer
                    $plateau[$clickR][$clickC] = $plateau[$sr][$sc];
                    $plateau[$sr][$sc] = null;

                    // Promotion du pion
                    if ($plateau[$clickR][$clickC] === 'BPP' || ($plateau[$clickR][$clickC] === 'BP' && $clickR === 0)) {
                        $plateau[$clickR][$clickC] = 'BQ';
                    }
                    if ($plateau[$clickR][$clickC] === 'NP' && $clickR === 7) {
                        $plateau[$clickR][$clickC] = 'NQ';
                    }

                    // Notation simplifiée pour l'historique
                    $cols = ['a','b','c','d','e','f','g','h'];
                    $notation = $cols[$sc] . (8 - $sr) . '→' . $cols[$clickC] . (8 - $clickR);
                    $_SESSION['historique'][] = $notation;
                    $lastMove = [[$sr,$sc],[$clickR,$clickC]];

                    // Changer de tour
                    $adv = $tour === 'B' ? 'N' : 'B';
                    $tour = $adv;
                    $selected = null;
                    $message = null;

                    // Vérifier fin de partie
                    $etat = etatFinEchecs($plateau, $tour);
                    if ($etat === 'mat') {
                        $gagnant = $tour === 'B' ? 'Noirs' : 'Blancs';
                        $fin = "Échec et mat ! Les $gagnant ont perdu. 🏆";
                    } elseif ($etat === 'pat') {
                        $fin = "Pat ! Match nul. 🤝";
                    } elseif (estEnEchec($plateau, $tour)) {
                        $message = "♔ Échec !";
                    }

                } elseif ($pieceClic && substr($pieceClic, 0, 1) === $tour) {
                    // Resélectionner une autre pièce alliée
                    $selected = [$clickR, $clickC];
                } else {
                    // Clic invalide → désélectionner
                    $selected = null;
                }
            }

        } else {
            // ── DAMES : sélection / déplacement ───────────────────────────────
            $aObligatoires = aPrisesObligatoires($plateau, $tour);

            if ($selected === null) {
                // Sélectionner
                if ($pieceClic && substr($pieceClic, 0, 1) === $tour) {
                    // Si prises obligatoires, ne sélectionner que les pièces qui peuvent prendre
                    if ($aObligatoires) {
                        $type = substr($pieceClic, 1, 1);
                        $mvs  = $type === 'D'
                            ? getMouvementsDame($plateau, $clickR, $clickC, $tour)
                            : getMouvementsPionDames($plateau, $clickR, $clickC, $tour);
                        $peutPrendre = array_filter($mvs, fn($mv) => !empty($mv['capture']));
                        if (!empty($peutPrendre)) $selected = [$clickR, $clickC];
                        else $message = "⚠ Prise obligatoire ! Choisissez une pièce qui peut prendre.";
                    } else {
                        $selected = [$clickR, $clickC];
                    }
                }
            } else {
                [$sr, $sc] = $selected;
                $pieceSel = $plateau[$sr][$sc];
                $typeSel  = substr($pieceSel, 1, 1);

                $mouvements = $typeSel === 'D'
                    ? getMouvementsDame($plateau, $sr, $sc, $tour)
                    : getMouvementsPionDames($plateau, $sr, $sc, $tour);

                // Si prises obligatoires, ne garder que les prises
                if ($aObligatoires) {
                    $mouvements = array_values(array_filter($mouvements, fn($mv) => !empty($mv['capture'])));
                }

                // Trouver le mouvement correspondant au clic
                $mvChoisi = null;
                foreach ($mouvements as $mv) {
                    if ($mv['to'][0] === $clickR && $mv['to'][1] === $clickC) {
                        $mvChoisi = $mv; break;
                    }
                }

                if ($mvChoisi) {
                    // Effectuer la prise si applicable
                    if (!empty($mvChoisi['capture'])) {
                        [$cr, $cc] = $mvChoisi['capture'];
                        $pieceCapturee = $plateau[$cr][$cc];
                        $captures[$tour][] = $pieceCapturee;
                        $plateau[$cr][$cc] = null;
                    }

                    // Déplacer
                    $plateau[$clickR][$clickC] = $plateau[$sr][$sc];
                    $plateau[$sr][$sc] = null;

                    // Promotion en dame (dames)
                    if ($plateau[$clickR][$clickC] === 'BP' && $clickR === 0) {
                        $plateau[$clickR][$clickC] = 'BD';
                    }
                    if ($plateau[$clickR][$clickC] === 'NP' && $clickR === 9) {
                        $plateau[$clickR][$clickC] = 'ND';
                    }

                    $lastMove = [[$sr,$sc],[$clickR,$clickC]];
                    $_SESSION['historique'][] = chr(65+$sc).(10-$sr).'→'.chr(65+$clickC).(10-$clickR);

                    // Changer de tour
                    $adv = $tour === 'B' ? 'N' : 'B';
                    $tour = $adv;
                    $selected = null;
                    $message = null;

                    // Vérifier fin de partie
                    $etat = etatFinDames($plateau, $tour);
                    if ($etat === 'victoire') {
                        $gagnant = $adv === 'B' ? 'Blancs' : 'Noirs';
                        $fin = "Victoire des $gagnant ! 🏆";
                    }

                } elseif ($pieceClic && substr($pieceClic, 0, 1) === $tour) {
                    // Resélectionner
                    $selected = [$clickR, $clickC];
                } else {
                    $selected = null;
                }
            }
        }

        // ── COUP DE L'IA ──────────────────────────────────────────────────────
        // Si mode IA et c'est au tour des Noirs (IA = Noirs) et pas de fin
        if ($modeJeu === 'ai' && $tour === 'N' && !$fin) {
            if ($jeuType === 'echecs') {
                $coup = aiChoisirCoupEchecs($plateau, 'N');
            } else {
                $coup = aiChoisirCoupDames($plateau, 'N');
            }

            if ($coup) {
                [$ar, $ac] = $coup['from'];
                [$tr, $tc] = $coup['to'];

                if ($jeuType === 'echecs') {
                    $cible = $plateau[$tr][$tc];
                    if ($cible) $captures['N'][] = $cible;
                    $plateau[$tr][$tc] = $plateau[$ar][$ac];
                    $plateau[$ar][$ac] = null;
                    // Promotion pion noir
                    if ($plateau[$tr][$tc] === 'NP' && $tr === 7) $plateau[$tr][$tc] = 'NQ';

                    $lastMove = [[$ar,$ac],[$tr,$tc]];
                    $tour = 'B';

                    $etat = etatFinEchecs($plateau, 'B');
                    if ($etat === 'mat') $fin = "Échec et mat ! Les Blancs ont perdu. 🤖";
                    elseif ($etat === 'pat') $fin = "Pat ! Match nul. 🤝";
                    elseif (estEnEchec($plateau, 'B')) $message = "♔ Échec !";

                } else {
                    // Dames IA
                    $pieceSel = $plateau[$ar][$ac];
                    $type     = substr($pieceSel, 1, 1);
                    $mvsDispo = $type === 'D'
                        ? getMouvementsDame($plateau, $ar, $ac, 'N')
                        : getMouvementsPionDames($plateau, $ar, $ac, 'N');

                    $mvIA = null;
                    foreach ($mvsDispo as $mv) {
                        if ($mv['to'][0] === $tr && $mv['to'][1] === $tc) { $mvIA = $mv; break; }
                    }

                    if ($mvIA && !empty($mvIA['capture'])) {
                        [$cr, $cc] = $mvIA['capture'];
                        $captures['N'][] = $plateau[$cr][$cc];
                        $plateau[$cr][$cc] = null;
                    }

                    $plateau[$tr][$tc] = $plateau[$ar][$ac];
                    $plateau[$ar][$ac] = null;
                    if ($plateau[$tr][$tc] === 'NP' && $tr === 9) $plateau[$tr][$tc] = 'ND';

                    $lastMove = [[$ar,$ac],[$tr,$tc]];
                    $tour = 'B';

                    $etat = etatFinDames($plateau, 'B');
                    if ($etat === 'victoire') $fin = "Victoire des Noirs (IA) ! 🤖";
                }
            }
        }

        // PRG : redirect pour éviter le rechargement dupliqué
        header('Location: game.php');
        exit;
    }
}

// ── CALCUL DES MOUVEMENTS POSSIBLES POUR L'AFFICHAGE ─────────────────────────
$mouvementsPossibles = [];
if ($selected && !$fin) {
    [$sr, $sc] = $selected;
    if ($jeuType === 'echecs') {
        $mvs = getMouvementsLegaux($plateau, $sr, $sc, $tour);
        foreach ($mvs as [$mr, $mc]) $mouvementsPossibles["$mr-$mc"] = true;
    } else {
        $pieceSel = $plateau[$sr][$sc];
        $typeSel  = substr($pieceSel, 1, 1);
        $mvs = $typeSel === 'D'
            ? getMouvementsDame($plateau, $sr, $sc, $tour)
            : getMouvementsPionDames($plateau, $sr, $sc, $tour);
        $aObl = aPrisesObligatoires($plateau, $tour);
        foreach ($mvs as $mv) {
            if (!$aObl || !empty($mv['capture'])) {
                $mouvementsPossibles[$mv['to'][0].'-'.$mv['to'][1]] = true;
            }
        }
    }
}

// ── EMOJIS DES PIÈCES ─────────────────────────────────────────────────────────
$emojisEchecs = [
    'BK' => '♔', 'BQ' => '♕', 'BR' => '♖', 'BB' => '♗', 'BN' => '♘', 'BP' => '♙',
    'NK' => '♚', 'NQ' => '♛', 'NR' => '♜', 'NB' => '♝', 'NN' => '♞', 'NP' => '♟',
];

$emojisDames = [
    'BP' => '⬤', 'NP' => '⬤',
    'BD' => '♛', 'ND' => '♛',
];

// Couleur des emojis dames (CSS)
function couleurDame(string $piece): string {
    $c = substr($piece, 0, 1);
    return $c === 'B'
        ? 'filter: brightness(2) sepia(1) hue-rotate(5deg);color:#f0d9b5;'
        : 'color:#1a0000;text-shadow:0 0 2px #000;';
}

// Roi en échec ?
$roiEnEchecPos = null;
if ($jeuType === 'echecs' && estEnEchec($plateau, $tour)) {
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            if ($plateau[$r][$c] === $tour . 'K') { $roiEnEchecPos = "$r-$c"; break 2; }
        }
    }
}

$nomJoueur1 = $modeJeu === 'ai' ? 'Vous (Blancs)' : 'Joueur 1 (Blancs)';
$nomJoueur2 = $modeJeu === 'ai' ? 'IA (Noirs)'    : 'Joueur 2 (Noirs)';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plateau Royal — <?= $jeuType === 'echecs' ? 'Échecs' : 'Dames' ?></title>
  <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
</head>
<body>

<?php if ($fin): ?>
<!-- ── MODAL FIN DE PARTIE ──────────────────────────────────────────────────── -->
<div class="overlay">
  <div class="modal">
    <span class="trophy"><?= str_contains($fin,'mat') || str_contains($fin,'Victoire') ? '🏆' : (str_contains($fin,'🤖') ? '🤖' : '🤝') ?></span>
    <h2>Partie terminée</h2>
    <p><?= htmlspecialchars($fin) ?></p>
    <div class="modal-buttons">
      <form method="POST"><input type="hidden" name="action" value="reset">
        <button type="submit" class="start-btn">↺ Rejouer</button>
      </form>
      <a href="index.php" class="start-btn" style="background:linear-gradient(135deg,#5c3317,#8b5e3c);">⟵ Accueil</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="game-wrapper">

  <!-- ── EN-TÊTE ────────────────────────────────────────────────────────────── -->
  <header class="game-header">
    <div class="game-title">
      <?= $jeuType === 'echecs' ? '♟ Échecs' : '⬤ Dames' ?>
      <span><?= $modeJeu === 'pvp' ? '2 Joueurs' : 'Contre l\'IA' ?></span>
    </div>
    <div class="header-actions">
      <?php if ($message): ?>
        <span style="color:var(--or);font-family:'Playfair Display',serif;font-size:1rem;">
          <?= htmlspecialchars($message) ?>
        </span>
      <?php endif; ?>
      <span style="font-size:.82rem;color:rgba(250,250,245,0.3);letter-spacing:2px;text-transform:uppercase;align-self:center;">
        Tour <?= count($_SESSION['historique']) + 1 ?>
      </span>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn-action danger">✕ Abandonner</button>
      </form>
      <a href="index.php" class="btn-action">⟵ Accueil</a>
    </div>
  </header>

  <!-- ── PANNEAU JOUEUR GAUCHE (Blancs) ─────────────────────────────────────── -->
  <div class="player-panel left <?= $tour === 'B' ? 'active' : '' ?>">
    <div class="player-name"><?= $nomJoueur1 ?></div>
    <div class="player-color-badge"><span class="dot blanc"></span> Blancs</div>
    <div class="player-turn">▶ À votre tour</div>
    <div class="captured-pieces">
      <?php foreach ($captures['B'] as $p):
        echo $jeuType === 'echecs' ? ($emojisEchecs[$p] ?? '?') : '⬤ ';
      endforeach; ?>
    </div>
  </div>

  <!-- ── PLATEAU ────────────────────────────────────────────────────────────── -->
  <div class="board-container">
    <div class="board-frame">

      <!-- Coordonnées haut -->
      <div class="board-coords-top" style="padding-left:<?= $taille === 8 ? '22px' : '22px' ?>">
        <?php for ($c = 0; $c < $taille; $c++):
          $label = $taille === 8 ? chr(97+$c) : chr(65+$c);
          $w     = $taille === 8 ? 70 : 60;
        ?>
          <div class="coord" style="width:<?= $w ?>px"><?= $label ?></div>
        <?php endfor; ?>
      </div>

      <div class="board-inner">
        <!-- Coordonnées gauche -->
        <div class="board-coords-left">
          <?php for ($r = 0; $r < $taille; $r++):
            $num = $taille - $r;
            $h   = $taille === 8 ? 70 : 60;
          ?>
            <div class="coord" style="height:<?= $h ?>px"><?= $num ?></div>
          <?php endfor; ?>
        </div>

        <!-- Plateau principal -->
        <div class="board board-<?= $taille ?>">
          <?php for ($r = 0; $r < $taille; $r++): for ($c = 0; $c < $taille; $c++):
            $coulCase   = ($r + $c) % 2 === 0 ? 'blanc' : 'noir';
            $key        = "$r-$c";
            $isSelected = $selected && $selected[0] === $r && $selected[1] === $c;
            $isPossible = isset($mouvementsPossibles[$key]);
            $isLast     = $lastMove && (
              ($lastMove[0][0] === $r && $lastMove[0][1] === $c) ||
              ($lastMove[1][0] === $r && $lastMove[1][1] === $c)
            );
            $isEchec    = $roiEnEchecPos === $key;
            $piece      = $plateau[$r][$c];
            $hasOccupied = $piece && $isPossible;

            $classes = "cell $coulCase";
            if ($isSelected)  $classes .= ' selected';
            if ($isPossible)  $classes .= ' possible';
            if ($hasOccupied) $classes .= ' occupied';
            if ($isLast && !$isSelected) $classes .= ' last-move';
            if ($isEchec)     $classes .= ' en-echec';

            // Pour les dames, seules les cases noires sont jouables
            $cliquable = $jeuType === 'echecs' || $coulCase === 'noir';
          ?>
          <form method="POST" style="display:contents;">
            <input type="hidden" name="action" value="click">
            <input type="hidden" name="r" value="<?= $r ?>">
            <input type="hidden" name="c" value="<?= $c ?>">
            <button type="<?= $cliquable ? 'submit' : 'button' ?>"
                    class="<?= $classes ?>"
                    <?= !$cliquable ? 'disabled style="cursor:default"' : '' ?>>
              <?php if ($piece):
                if ($jeuType === 'echecs') {
                    $emoji = $emojisEchecs[$piece] ?? '?';
                    $coulCls = substr($piece,0,1) === 'B' ? 'blanc' : 'noir';
                    echo "<span class='piece' style='color:" . (substr($piece,0,1)==='B' ? '#f0d9b5' : '#1a0000') . ";text-shadow:1px 2px 4px rgba(0,0,0,0.7);'>$emoji</span>";
                } else {
                    $emoji = $emojisDames[$piece] ?? '?';
                    $isD   = substr($piece,1,1) === 'D';
                    $style = couleurDame($piece);
                    echo "<span class='piece" . ($isD ? " dame" : "") . "' style='$style font-size:" . ($taille===8?'2.4':'2.0') . "rem;'>$emoji</span>";
                }
              endif; ?>
            </button>
          </form>
          <?php endfor; endfor; ?>
        </div>

        <!-- Coordonnées droite -->
        <div class="board-coords-right">
          <?php for ($r = 0; $r < $taille; $r++):
            $h = $taille === 8 ? 70 : 60;
          ?>
            <div class="coord" style="height:<?= $h ?>px"><?= $taille - $r ?></div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Coordonnées bas -->
      <div class="board-coords-bottom" style="padding-left:22px;">
        <?php for ($c = 0; $c < $taille; $c++):
          $label = $taille === 8 ? chr(97+$c) : chr(65+$c);
          $w     = $taille === 8 ? 70 : 60;
        ?>
          <div class="coord" style="width:<?= $w ?>px"><?= $label ?></div>
        <?php endfor; ?>
      </div>

    </div><!-- .board-frame -->
  </div><!-- .board-container -->

  <!-- ── PANNEAU JOUEUR DROITE (Noirs) ─────────────────────────────────────── -->
  <div class="player-panel right <?= $tour === 'N' ? 'active' : '' ?>">
    <div class="player-name"><?= $nomJoueur2 ?></div>
    <div class="player-color-badge"><span class="dot noir"></span> Noirs</div>
    <div class="player-turn">
      <?= $modeJeu === 'ai' ? '🤖 L\'IA réfléchit…' : '▶ À votre tour' ?>
    </div>
    <div class="captured-pieces">
      <?php foreach ($captures['N'] as $p):
        echo $jeuType === 'echecs' ? ($emojisEchecs[$p] ?? '?') : '⬤ ';
      endforeach; ?>
    </div>
  </div>

  <!-- ── BARRE D'INFOS ──────────────────────────────────────────────────────── -->
  <div class="info-panel">

    <div class="status-box highlight">
      <div class="label">Tour actuel</div>
      <div class="value"><?= $tour === 'B' ? '⬜ Blancs' : '⬛ Noirs' ?></div>
    </div>

    <div class="status-box">
      <div class="label">Coups joués</div>
      <div class="value"><?= count($_SESSION['historique']) ?></div>
    </div>

    <?php if (!empty($_SESSION['historique'])): ?>
    <div class="history-panel">
      <div class="history-list">
        <?php $hist = $_SESSION['historique'];
              foreach ($hist as $i => $coup): ?>
          <span class="history-move <?= $i === count($hist)-1 ? 'last' : '' ?>">
            <?= htmlspecialchars($coup) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($jeuType === 'dames'): ?>
    <div class="status-box">
      <div class="label">Pièces restantes</div>
      <div class="value">
        <?php
          $nb = ['B'=>0,'N'=>0];
          for ($r=0;$r<10;$r++) for ($c=0;$c<10;$c++) {
            if ($plateau[$r][$c]) $nb[substr($plateau[$r][$c],0,1)]++;
          }
          echo "⬜ {$nb['B']} — ⬛ {$nb['N']}";
        ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- .info-panel -->

</div><!-- .game-wrapper -->

</body>
</html>
