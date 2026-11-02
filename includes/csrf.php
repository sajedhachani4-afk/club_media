<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Protection CSRF : un jeton aléatoire par session, vérifié sur chaque POST.
 */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** À appeler au début du traitement de tout POST. Interrompt la requête si le jeton est invalide. */
function csrf_require(): void
{
    if (csrf_verify()) {
        return;
    }
    http_response_code(419);
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch');
    if ($isAjax) {
        json_response(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 419);
    }
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Session expirée</title>'
        . '<body style="font-family:system-ui;background:#09090F;color:#F8FAFC;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<div style="max-width:480px;padding:24px"><h1>Session expirée</h1>'
        . '<p>Le formulaire n\'est plus valide (jeton de sécurité manquant ou expiré). Revenez en arrière, rechargez la page et réessayez.</p></div></body></html>';
    exit;
}
