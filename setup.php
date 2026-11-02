<?php
/**
 * Installation initiale (une seule fois) : crée le premier compte administrateur.
 * Aucun identifiant par défaut n'existe. Dès qu'un administrateur est créé,
 * cette page se verrouille d'elle-même (vous pouvez aussi supprimer ce fichier).
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

send_security_headers();
$siteName = setting('site_name', APP_NAME);

function setup_page_open(string $title): void
{
    ?><!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> — Club Media ISET Gafsa</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<main class="auth-page"><div class="auth-card auth-card--wide">
    <a class="brand" href="<?= e(url('index.php')) ?>"><img class="brand__logo" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
    <span class="brand__text"><strong>CLUB MEDIA</strong><small>ISET GAFSA</small></span></a>
    <?php
}
function setup_page_close(): void
{
    echo '</div></main></body></html>';
}

/* Verrouillage : un administrateur existe déjà */
if (admin_count() > 0) {
    http_response_code(403);
    setup_page_open('Installation terminée');
    echo '<h1>Installation déjà effectuée</h1><p class="muted">Un compte administrateur existe déjà : cette page est verrouillée. '
        . 'Pour plus de sécurité, vous pouvez supprimer le fichier <code>setup.php</code> du serveur.</p>'
        . '<a class="btn btn-primary" href="' . e(url('admin.php')) . '">Aller à la connexion</a>';
    setup_page_close();
    exit;
}

/* Vérification de l'environnement */
$uploadDirs = ['events', 'photos', 'photos/thumbs', 'videos', 'thumbs', 'site', 'team'];
$checks = [];
$checks[] = [version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP ' . PHP_VERSION . ' (8.0 minimum)', true];
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd'] as $ext) {
    $checks[] = [extension_loaded($ext), 'Extension PHP « ' . $ext . ' »' . ($ext === 'gd' ? ' (traitement des images)' : ''), true];
}
$checks[] = [extension_loaded('gd') && function_exists('imagecreatefromwebp'), 'Prise en charge WebP (facultatif)', false];
foreach ($uploadDirs as $d) {
    $path = UPLOAD_PATH . '/' . $d;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $checks[] = [is_dir($path) && is_writable($path), 'Dossier uploads/' . $d . ' accessible en écriture', true];
}
$checks[] = [is_dir(LOG_PATH) && is_writable(LOG_PATH), 'Dossier logs/ accessible en écriture', false];
$blocking = false;
foreach ($checks as [$ok, , $required]) {
    if (!$ok && $required) {
        $blocking = true;
    }
}

$errors = [];
$keyNeeded = SETUP_KEY !== '';
if (is_post()) {
    csrf_require();
    $username = post('username');
    $email    = post('email');
    $pass     = (string) ($_POST['password'] ?? '');
    $pass2    = (string) ($_POST['password2'] ?? '');

    if (rate_limited('setup', 10, 3600)) {
        $errors['form'] = 'Trop de tentatives. Réessayez plus tard.';
    }
    if ($keyNeeded && !hash_equals(SETUP_KEY, (string) ($_POST['setup_key'] ?? ''))) {
        $errors['setup_key'] = 'Clé d\'installation incorrecte.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors['username'] = 'Identifiant : 3 à 50 caractères (lettres, chiffres, . _ -).';
    }
    if (!valid_email($email)) {
        $errors['email'] = 'Adresse e-mail invalide.';
    }
    if (($msg = admin_password_error($pass)) !== null) {
        $errors['password'] = $msg;
    } elseif ($pass !== $pass2) {
        $errors['password2'] = 'Les deux mots de passe ne correspondent pas.';
    }
    if ($blocking) {
        $errors['form'] = 'Corrigez d\'abord les points en rouge ci-dessus.';
    }

    if (!$errors) {
        rate_limit_hit('setup');
        // Insertion atomique : n'a lieu que si aucun administrateur n'existe (course entre deux visiteurs)
        $st = db()->prepare(
            'INSERT INTO admins (username, email, password_hash)
             SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admins)'
        );
        $st->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        if ($st->rowCount() === 1) {
            log_activity('Installation', 'Premier administrateur créé', (int) db()->lastInsertId());
            flash('success', 'Compte administrateur créé. Vous pouvez vous connecter.');
            redirect('admin.php');
        }
        $errors['form'] = 'Un administrateur existe déjà.';
    }
}

setup_page_open('Installation');
?>
    <h1>Installation — premier administrateur</h1>
    <p class="muted">Créez le compte qui gérera le site. Il n'existe aucun identifiant par défaut.</p>

    <h2 style="font-size:1.05rem;margin-top:22px">Vérification du serveur</h2>
    <ul class="checks">
        <?php foreach ($checks as [$ok, $label, $required]): ?>
            <li class="<?= $ok ? 'ok' : ($required ? 'ko' : 'warn') ?>"><span aria-hidden="true"><?= $ok ? '✔' : ($required ? '✘' : '⚠') ?></span>
                <span><?= e($label) ?><span class="sr-only"><?= $ok ? ' : OK' : ($required ? ' : manquant' : ' : non disponible') ?></span></span></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($blocking): ?>
        <div class="alert alert--error" role="alert">Un élément obligatoire est manquant. Sous XAMPP : ouvrez <code>php.ini</code>, retirez le « ; » devant <code>extension=gd</code> (et <code>fileinfo</code>, <code>mbstring</code>), puis redémarrez Apache.</div>
    <?php endif; ?>

    <?php if (!empty($errors['form'])): ?><div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form class="form" method="post" action="<?= e(url('setup.php')) ?>" novalidate data-once>
        <?= csrf_field() ?>
        <?php if ($keyNeeded): ?>
            <div class="field">
                <label for="setup_key">Clé d'installation (définie dans config/config.php)</label>
                <input type="password" id="setup_key" name="setup_key" required autocomplete="off"<?= isset($errors['setup_key']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($errors, 'setup_key') ?>
            </div>
        <?php endif; ?>
        <div class="form-row">
            <div class="field">
                <label for="username">Identifiant</label>
                <input type="text" id="username" name="username" value="<?= val('username') ?>" required maxlength="50" autocomplete="username"<?= isset($errors['username']) ? ' aria-invalid="true" aria-describedby="err-username"' : '' ?>>
                <?= field_error($errors, 'username') ?>
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= val('email') ?>" required maxlength="150" autocomplete="email"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                <?= field_error($errors, 'email') ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="new-password"<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="err-password"' : '' ?>>
                <p class="field-hint">10 caractères minimum, avec au moins une lettre et un chiffre.</p>
                <?= field_error($errors, 'password') ?>
            </div>
            <div class="field">
                <label for="password2">Confirmer le mot de passe</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password"<?= isset($errors['password2']) ? ' aria-invalid="true" aria-describedby="err-password2"' : '' ?>>
                <?= field_error($errors, 'password2') ?>
            </div>
        </div>
        <button class="btn btn-primary btn-lg" type="submit"<?= $blocking ? ' disabled' : '' ?>>Créer l'administrateur</button>
    </form>
<?php setup_page_close(); ?>
