<?php
/** Connexion à l'administration : http://localhost/club-media/admin.php */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

if (admin_count() === 0) {
    redirect('setup.php');          // aucun compte : installation initiale
}
if (admin_current() !== null) {
    redirect('admin/dashboard.php');
}

$error = '';
if (is_post()) {
    csrf_require();
    $res = admin_login(post('login'), (string) ($_POST['password'] ?? ''));
    if ($res['ok']) {
        redirect('admin/dashboard.php');
    }
    $error = $res['message'];
}

send_security_headers();
$siteName = setting('site_name', APP_NAME);
?><!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion administrateur — <?= e($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Space+Grotesk:wght@500;600;700&amp;display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<main class="auth-page" id="main">
    <div class="auth-card">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img class="brand__logo" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
            <span class="brand__text"><strong>CLUB MEDIA</strong><small>ISET GAFSA</small></span>
        </a>
        <h1>Connexion administrateur</h1>
        <p class="muted">Accès réservé aux responsables du club.</p>
        <?= render_flashes() ?>
        <?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="form" method="post" action="<?= e(url('admin.php')) ?>" autocomplete="on">
            <?= csrf_field() ?>
            <div class="field">
                <label for="login">Identifiant ou e-mail</label>
                <input type="text" id="login" name="login" value="<?= val('login') ?>" required autofocus autocomplete="username" maxlength="150">
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Se connecter</button>
        </form>
        <p style="margin:22px 0 0;text-align:center"><a href="<?= e(url('index.php')) ?>">← Retour au site</a></p>
    </div>
</main>
</body>
</html>
