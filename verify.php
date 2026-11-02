<?php
/**
 * Vérification de ticket : réservée aux administrateurs.
 * Cette adresse redirige vers le scanner de l'administration (connexion exigée).
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

require_admin();
redirect('admin/tickets.php');
