<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Configuration centrale de la base de données.
 *
 * C'est le SEUL fichier à modifier pour connecter le site à MySQL / MariaDB.
 * Valeurs par défaut d'un XAMPP standard sous Windows :
 *   hôte localhost, port 3306, utilisateur root, mot de passe vide.
 * Toutes les installations n'utilisent pas ces valeurs : adaptez-les si besoin.
 */
declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_PORT    = 3306;
const DB_NAME    = 'club_media_db';
const DB_USER    = 'root';
const DB_PASS    = '';           // vide par défaut sous XAMPP
const DB_CHARSET = 'utf8mb4';

/**
 * Retourne la connexion PDO unique (créée à la première utilisation).
 * Toute erreur de connexion est interceptée par le gestionnaire global
 * (config/config.php) qui affiche une page d'aide à l'installation.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    // La Tunisie est en UTC+1 toute l'année.
    $pdo->exec("SET time_zone = '+01:00'");

    return $pdo;
}
