<?php
/**
 * AI.PHP — Intelligence Artificielle (niveau débutant)
 * 
 * Pour les ÉCHECS : IA aléatoire avec légère préférence pour les captures
 * Pour les DAMES  : IA aléatoire avec préférence pour les prises obligatoires
 * 
 * Inclus par game.php, jamais appelé directement.
 */

/**
 * Choisit le coup de l'IA pour les ÉCHECS
 * 
 * @param array  $board   Plateau 8x8
 * @param string $couleur 'N' (l'IA joue toujours noir)
 * @return array|null ['from'=>[r,c], 'to'=>[r,c]] ou null
 */
function aiChoisirCoupEchecs(array $board, string $couleur): ?array {
    $captures = [];
    $normaux  = [];

    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            $piece = $board[$r][$c];
            if (!$piece || substr($piece, 0, 1) !== $couleur) continue;

            $mouvements = getMouvementsEchecs($board, $r, $c, $couleur);

            foreach ($mouvements as [$tr, $tc]) {
                $cible = $board[$tr][$tc];
                if ($cible && substr($cible, 0, 1) !== $couleur) {
                    // Capture — prioritaire
                    $captures[] = ['from' => [$r, $c], 'to' => [$tr, $tc]];
                } else {
                    $normaux[] = ['from' => [$r, $c], 'to' => [$tr, $tc]];
                }
            }
        }
    }

    $pool = !empty($captures) ? $captures : $normaux;
    if (empty($pool)) return null;

    return $pool[array_rand($pool)];
}

/**
 * Choisit le coup de l'IA pour les DAMES
 *
 * Respecte la règle de prise obligatoire :
 * si une prise est possible, l'IA doit prendre.
 */
function aiChoisirCoupDames(array $board, string $couleur): ?array {
    $prises  = [];
    $normaux = [];

    for ($r = 0; $r < 10; $r++) {
        for ($c = 0; $c < 10; $c++) {
            $piece = $board[$r][$c];
            if (!$piece || substr($piece, 0, 1) !== $couleur) continue;

            $type = substr($piece, 1, 1); // 'P' pion, 'D' dame

            if ($type === 'D') {
                $mvs = getMouvementsDame($board, $r, $c, $couleur);
            } else {
                $mvs = getMouvementsPionDames($board, $r, $c, $couleur);
            }

            foreach ($mvs as $mv) {
                if (!empty($mv['capture'])) {
                    $prises[] = ['from' => [$r, $c], 'to' => [$mv['to'][0], $mv['to'][1]]];
                } else {
                    $normaux[] = ['from' => [$r, $c], 'to' => [$mv['to'][0], $mv['to'][1]]];
                }
            }
        }
    }

    // Règle : prise obligatoire
    $pool = !empty($prises) ? $prises : $normaux;
    if (empty($pool)) return null;

    return $pool[array_rand($pool)];
}
