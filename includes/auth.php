<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Authentification de l'administration : sessions, limitation de tentatives,
 * régénération de l'identifiant de session après connexion.
 */
declare(strict_types=1);

// Hash factice : permet de comparer en temps constant même si l'utilisateur n'existe pas.
const AUTH_DUMMY_HASH = '$2y$10$I3DPm8P0rOou0nFo4EqmVeuSToFK4vEvpv84dL4dRGRBLuiSorNeO';

function admin_clear_session(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_last'], $_SESSION['admin_ua']);
}

/** Administrateur connecté (tableau id/username/email) ou null. */
function admin_current(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;

    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if (isset($_SESSION['admin_last']) && (time() - (int) $_SESSION['admin_last']) > ADMIN_IDLE_TIMEOUT) {
        admin_clear_session();
        flash('info', 'Votre session a expiré. Veuillez vous reconnecter.');
        return null;
    }
    $ua = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!hash_equals((string) ($_SESSION['admin_ua'] ?? ''), $ua)) {
        admin_clear_session();
        return null;
    }
    $st = db()->prepare('SELECT id, username, email FROM admins WHERE id = ?');
    $st->execute([(int) $_SESSION['admin_id']]);
    $row = $st->fetch();
    if (!$row) {
        admin_clear_session();
        return null;
    }
    $_SESSION['admin_last'] = time();
    $cached = $row;
    return $cached;
}

/** Protège une page d'administration. Les requêtes AJAX reçoivent un JSON 401. */
function require_admin(): array
{
    $admin = admin_current();
    if ($admin !== null) {
        return $admin;
    }
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch');
    if ($isAjax) {
        json_response(['ok' => false, 'message' => 'Session expirée. Reconnectez-vous.'], 401);
    }
    if (empty($_SESSION['flash'])) {
        flash('warning', 'Veuillez vous connecter pour accéder à l\'administration.');
    }
    redirect('admin.php');
    return [];
}

function admin_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

/**
 * Tente une connexion.
 * @return array{ok:bool,message:string}
 */
function admin_login(string $login, string $password): array
{
    $login   = mb_substr(trim($login), 0, 150);
    $userKey = 'login_user:' . substr(hash('sha256', mb_strtolower($login)), 0, 32);

    if (rate_limited('login_ip', 10, 900) || rate_limited($userKey, 5, 900)) {
        return ['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
    }

    $st = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $admin = $st->fetch();

    $hash = $admin['password_hash'] ?? AUTH_DUMMY_HASH;
    $ok   = password_verify($password, $hash) && $admin;

    if (!$ok) {
        rate_limit_hit('login_ip');
        rate_limit_hit($userKey);
        usleep(random_int(150000, 400000));
        log_activity('Échec de connexion', 'Identifiant : ' . mb_substr($login, 0, 60), null);
        return ['ok' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int) $admin['id'];
    $_SESSION['admin_last'] = time();
    $_SESSION['admin_ua']   = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    unset($_SESSION['csrf_token']);

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
    log_activity('Connexion', 'Connexion réussie', (int) $admin['id']);

    return ['ok' => true, 'message' => ''];
}

function admin_logout(): void
{
    admin_clear_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

/** Règles de mot de passe pour les administrateurs. Retourne un message d'erreur ou null. */
function admin_password_error(string $password): ?string
{
    if (mb_strlen($password) < 10) {
        return 'Le mot de passe doit contenir au moins 10 caractères.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }
    return null;
}
