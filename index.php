<?php
/*
 * PÉTALES — générés par PHP, animés par CSS pur, zéro JavaScript
 *
 * LOGIQUE :
 * PHP génère 24 éléments <span class="petal"> avec des variables CSS
 * custom inline (--x, --delay, --duration, --size, --sway, --rot-start,
 * --rot-end, --opacity). Ces variables sont lues par les @keyframes CSS.
 * Chaque rechargement donne des pétales différents grâce à rand().
 * Aucun JS n'est nécessaire pour l'animation elle-même.
 */

$formes = [
    'M 0,-11 C 7,-7 9,-1 0,11 C -9,-1 -7,-7 0,-11 Z',       // ovale centré
    'M 1,-10 C 9,-4 8,3 0,11 C -8,3 -7,-5 1,-10 Z',          // pétale asymétrique
    'M 0,-13 C 6,-7 10,1 0,13 C -10,1 -6,-7 0,-13 Z',        // pétale allongé
];

$couleurs = [
    'rgba(242,167,187,OP)',   // rose sakura
    'rgba(255,192,210,OP)',   // rose pâle
    'rgba(233,140,162,OP)',   // rose soutenu
    'rgba(253,216,230,OP)',   // rose poudrée
    'rgba(248,180,200,OP)',   // rose doux
];

$petals = [];
for ($i = 0; $i < 24; $i++) {
    $opacity = rand(45, 75) / 100;
    $couleur = str_replace('OP', $opacity, $couleurs[rand(0, 4)]);
    $sway    = rand(40, 100) * (rand(0, 1) ? 1 : -1); // gauche ou droite
    $petals[] = [
        'x'         => rand(0, 100),        // position départ horizontal (vw)
        'delay'     => rand(0, 140) / 10,   // délai avant apparition (0–14s)
        'duration'  => rand(80, 160) / 10,  // durée de chute (8–16s)
        'size'      => rand(10, 22),        // taille (px)
        'sway'      => $sway,               // balancement horizontal (px)
        'rot_start' => rand(0, 360),        // rotation initiale
        'rot_end'   => rand(200, 600),      // rotation finale
        'opacity'   => $opacity,
        'forme'     => $formes[rand(0, 2)],
        'couleur'   => $couleur,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plateau Royal — Choisissez votre jeu</title>
  <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
  <style>

    /* ── Couche pétales ─────────────────────────────────────────────── */
    .petals-layer {
      position: fixed;
      inset: 0;
      pointer-events: none;   /* les clics passent à travers */
      z-index: 10;
      overflow: hidden;
    }

    .petal {
      position: absolute;
      top: -30px;                         /* commence hors écran en haut */
      left: calc(var(--x) * 1vw);        /* position aléatoire en largeur */
      width:  calc(var(--size) * 1px);
      height: calc(var(--size) * 1px);
      opacity: 0;
      will-change: transform, opacity;
      /*
       * 3 animations jouées en parallèle :
       *   petal-fall  → descend de -30px à 105vh, fade in/out
       *   petal-sway  → balancement latéral gauche/droite
       *   petal-spin  → rotation continue
       * Toutes utilisent le même delay pour rester synchronisées.
       */
      animation:
        petal-fall  calc(var(--duration) * 1s)        calc(var(--delay) * 1s)  infinite  ease-in,
        petal-sway  calc(var(--duration) * 0.55s)     calc(var(--delay) * 1s)  infinite  ease-in-out  alternate,
        petal-spin  calc(var(--duration) * 1s)        calc(var(--delay) * 1s)  infinite  linear;
    }

    /* Chute verticale avec fondu entrant et sortant */
    @keyframes petal-fall {
      0%   { transform: translateY(0);     opacity: 0; }
      8%   {                               opacity: var(--opacity); }
      88%  {                               opacity: var(--opacity); }
      100% { transform: translateY(105vh); opacity: 0; }
    }

    /* Balancement latéral (alternate = va et vient) */
    @keyframes petal-sway {
      from { margin-left: 0; }
      to   { margin-left: calc(var(--sway) * 1px); }
    }

    /* Rotation douce */
    @keyframes petal-spin {
      from { rotate: calc(var(--rot-start) * 1deg); }
      to   { rotate: calc(var(--rot-end)   * 1deg); }
    }

  </style>
</head>
<body>

<!-- ── PÉTALES (aria-hidden = invisibles aux lecteurs d'écran) ─── -->
<div class="petals-layer" aria-hidden="true">
<?php foreach ($petals as $p): ?>
  <span class="petal" style="
    --x:         <?= $p['x'] ?>;
    --delay:     <?= $p['delay'] ?>;
    --duration:  <?= $p['duration'] ?>;
    --size:      <?= $p['size'] ?>;
    --sway:      <?= $p['sway'] ?>;
    --rot-start: <?= $p['rot_start'] ?>;
    --rot-end:   <?= $p['rot_end'] ?>;
    --opacity:   <?= $p['opacity'] ?>;
  ">
    <svg viewBox="-14 -14 28 28" xmlns="http://www.w3.org/2000/svg"
         width="100%" height="100%">
      <path d="<?= $p['forme'] ?>" fill="<?= $p['couleur'] ?>"/>
    </svg>
  </span>
<?php endforeach; ?>
</div>

<!-- ── CONTENU PRINCIPAL ──────────────────────────────────────── -->
<div class="landing">

  <div class="landing-title">Plateau Royal</div>
  <div class="landing-kanji">桜の盤</div>
  <div class="landing-subtitle">Échecs &amp; Dames</div>

  <div class="choice-grid">

    <a href="game.php?jeu=echecs&mode=pvp" class="game-card">
      <span class="emoji">♟</span>
      <h2>Échecs</h2>
      <p>Roi, Dame, Tour, Fou, Cavalier, Pion.<br>Plateau 8×8 classique.</p>
    </a>

    <a href="game.php?jeu=dames&mode=pvp" class="game-card">
      <span class="emoji">⬤</span>
      <h2>Dames</h2>
      <p>Pions et dames sur plateau 10×10.<br>Règles françaises.</p>
    </a>

  </div>

  <div class="mode-section">
    <h3>Choisir le mode</h3>

    <form method="GET" action="game.php"
          style="display:flex;flex-direction:column;align-items:center;gap:20px;">

      <input type="hidden" name="jeu" id="jeu-input" value="echecs">

      <div class="mode-buttons">
        <label>
          <input type="radio" name="mode" value="pvp" style="display:none" checked>
          <span class="mode-btn selected">👥 2 Joueurs</span>
        </label>
        <label>
          <input type="radio" name="mode" value="ai" style="display:none">
          <span class="mode-btn">🤖 Contre l'IA</span>
        </label>
      </div>

      <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;">
        <button type="submit" class="start-btn"
                onclick="document.getElementById('jeu-input').value='echecs'">
          ♟ Jouer aux Échecs
        </button>
        <button type="submit" class="start-btn"
                onclick="document.getElementById('jeu-input').value='dames'"
                style="background:linear-gradient(135deg,var(--laque-dark),var(--laque));">
          ⬤ Jouer aux Dames
        </button>
      </div>

    </form>
  </div>

  <!-- Règles rapides -->
  <div style="margin-top:48px;max-width:600px;display:grid;grid-template-columns:1fr 1fr;gap:20px;text-align:left;">

    <div style="background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);border:1px solid rgba(242,167,187,0.3);border-radius:14px;padding:22px;">
      <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--laque);margin-bottom:12px;letter-spacing:1px;">
        ♟ Règles Échecs
      </div>
      <ul style="color:var(--encre-mid);font-size:0.83rem;line-height:2.1;list-style:none;opacity:0.85;">
        <li>♔ Roi — 1 case toutes directions</li>
        <li>♕ Dame — illimité toutes directions</li>
        <li>♖ Tour — horizontale / verticale</li>
        <li>♗ Fou — diagonales</li>
        <li>♘ Cavalier — en L (2+1)</li>
        <li>♙ Pion — avance, capture en diagonal</li>
        <li style="color:var(--sakura-deep);margin-top:6px;font-style:italic;">
          Objectif : échec et mat
        </li>
      </ul>
    </div>

    <div style="background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);border:1px solid rgba(242,167,187,0.3);border-radius:14px;padding:22px;">
      <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--laque);margin-bottom:12px;letter-spacing:1px;">
        ⬤ Règles Dames
      </div>
      <ul style="color:var(--encre-mid);font-size:0.83rem;line-height:2.1;list-style:none;opacity:0.85;">
        <li>⬤ Pion — avance en diagonal</li>
        <li>⬤ Prise — obligatoire si possible</li>
        <li>♛ Dame — dernière rangée adverse</li>
        <li>♛ Dame — diagonales libres</li>
        <li>Prises multiples en chaîne</li>
        <li style="color:var(--sakura-deep);margin-top:6px;font-style:italic;">
          Objectif : capturer toutes les pièces
        </li>
      </ul>
    </div>

  </div>

</div><!-- .landing -->

<script>
/* Seul JS présent : synchronisation visuelle des boutons radio de mode */
document.querySelectorAll('input[name="mode"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('selected'));
    radio.nextElementSibling.classList.add('selected');
  });
});
</script>

</body>
</html>