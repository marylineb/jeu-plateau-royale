<?php
/**
 * RESET.PHP — Réinitialise la session et redirige vers l'accueil
 */
session_start();
session_destroy();
header('Location: index.php');
exit;
