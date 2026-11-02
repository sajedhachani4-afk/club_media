<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Amorçage de l'application : configuration, sessions, gestion d'erreurs,
 * chargement des fichiers communs. Chaque page commence par require de ce fichier.
 */
declare(strict_types=1);

if (defined('CM_BOOTSTRAPPED')) {
    return;
}
define('CM_BOOTSTRAPPED', true);

/* ------------------------------------------------------------------ */
/* Réglages généraux                                                    */
/* ------------------------------------------------------------------ */
define('APP_NAME', 'Club Media ISET Gafsa');
define('APP_TIMEZONE', 'Africa/Tunis');
define('APP_DEBUG', false);          // true : affiche le détail technique, uniquement depuis localhost
define('SETUP_KEY', '');             // Optionnel : si renseigné, exigé par setup.php (utile sur un hébergement)

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Limites d'envoi (le serveur PHP peut imposer des limites plus basses : voir README)
define('MAX_IMAGE_BYTES', 8 * 1024 * 1024);      // 8 Mo par image
define('MAX_VIDEO_BYTES', 200 * 1024 * 1024);    // 200 Mo par vidéo
define('MAX_IMAGE_PIXELS', 24000000);            // 24 mégapixels
define('MAX_TICKETS_PER_RESERVATION', 10);
define('ITEMS_PER_PAGE_EVENTS', 9);
define('ITEMS_PER_PAGE_PHOTOS', 24);
define('ITEMS_PER_PAGE_VIDEOS', 12);
define('ADMIN_IDLE_TIMEOUT', 7200);              // 2 h d'inactivité

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

/* ------------------------------------------------------------------ */
/* Erreurs : jamais affichées aux visiteurs, écrites dans logs/        */
/* ------------------------------------------------------------------ */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (is_dir(LOG_PATH) && is_writable(LOG_PATH)) {
    ini_set('error_log', LOG_PATH . '/app.log');
}
error_reporting(E_ALL);

/* ------------------------------------------------------------------ */
/* Chemin de base (ex. /club-media) détecté automatiquement             */
/* ------------------------------------------------------------------ */
(function (): void {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $root    = realpath(ROOT_PATH);
    $base    = '';
    if ($docRoot !== false && $root !== false) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $root    = str_replace('\\', '/', $root);
        if (strpos($root, $docRoot) === 0) {
            $base = substr($root, strlen($docRoot));
        }
    }
    if ($base === '' && isset($_SERVER['SCRIPT_NAME'])) {
        // Repli : dérive la base depuis le script courant (pages à la racine ou dans /admin).
        $dir  = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
        $base = preg_replace('#/admin(/.*)?$#', '', $dir) ?? '';
    }
    $base = '/' . trim($base, '/');
    define('BASE_PATH', $base === '/' ? '' : $base);
})();

/* ------------------------------------------------------------------ */
/* Sessions sécurisées                                                  */
/* ------------------------------------------------------------------ */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('cmsess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------ */
/* Fichiers communs                                                     */
/* ------------------------------------------------------------------ */
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/uploads.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/tickets.php';
require_once ROOT_PATH . '/includes/admin_layout.php';

/* ------------------------------------------------------------------ */
/* Gestionnaire global d'exceptions                                     */
/* ------------------------------------------------------------------ */
set_exception_handler(function (Throwable $e): void {
    error_log('[CLUB-MEDIA] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch');
    if ($isAjax) {
        echo json_encode(['ok' => false, 'message' => 'Erreur serveur.']);
        exit;
    }
    render_error_page($e);
    exit;
});
