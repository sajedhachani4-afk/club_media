<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * En-tête commun des pages publiques.
 * Variables attendues (facultatives) : $pageTitle, $pageDesc, $activeNav, $bodyClass, $pageImage
 */
declare(strict_types=1);

send_security_headers();

$siteName  = setting('site_name', APP_NAME);
$logoFile  = setting('logo');
$pageTitle = $pageTitle ?? '';
$fullTitle = $pageTitle !== '' ? $pageTitle . ' — ' . $siteName : $siteName . ' — Create. Capture. Inspire.';
$pageDesc  = $pageDesc ?? setting('club_description', 'Événements, photos et vidéos du Club Media ISET Gafsa.');
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';

$navItems = [
    'home'    => ['Accueil', 'index.php'],
    'events'  => ['Événements', 'events.php'],
    'gallery' => ['Galerie', null],
    'team'    => ['Notre équipe', 'team.php'],
    'about'   => ['À propos', 'about.php'],
    'contact' => ['Contact', 'contact.php'],
];
?><!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e(truncate($pageDesc, 160)) ?>">
    <meta name="theme-color" content="#09090F">
    <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e(truncate($pageDesc, 200)) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($pageImage)): ?><meta property="og:image" content="<?= e($pageImage) ?>"><?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Space+Grotesk:wght@500;600;700&amp;display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <noscript><style>[data-aos]{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Aller au contenu principal</a>

<header class="site-header" id="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($siteName) ?> — accueil">
            <?php if ($logoFile !== ''): ?>
                <img class="brand__logo" src="<?= e(upload_url('site', $logoFile)) ?>" alt="" width="40" height="40">
            <?php else: ?>
                <img class="brand__logo" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
            <?php endif; ?>
            <span class="brand__text"><strong>CLUB MEDIA</strong><small>ISET GAFSA</small></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Ouvrir le menu">
            <span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span>
        </button>

        <nav class="primary-nav" id="primary-nav" aria-label="Navigation principale">
            <ul class="primary-nav__list">
                <?php foreach ($navItems as $key => [$label, $href]): ?>
                    <?php if ($key === 'gallery'): ?>
                        <li class="has-dropdown<?= $activeNav === 'gallery' ? ' is-active' : '' ?>">
                            <button class="dropdown-toggle" type="button" aria-expanded="false" aria-controls="dropdown-gallery">
                                <?= e($label) ?> <i data-lucide="chevron-down" aria-hidden="true"></i>
                            </button>
                            <ul class="dropdown" id="dropdown-gallery">
                                <li><a href="<?= e(url('gallery.php')) ?>"><i data-lucide="image" aria-hidden="true"></i> Photos</a></li>
                                <li><a href="<?= e(url('videos.php')) ?>"><i data-lucide="film" aria-hidden="true"></i> Vidéos</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li<?= $activeNav === $key ? ' class="is-active"' : '' ?>>
                            <a href="<?= e(url($href)) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="primary-nav__mobile-only"><a href="<?= e(url('my-reservations.php')) ?>">Mes réservations</a></li>
            </ul>
            <div class="primary-nav__actions">
                <a class="icon-link" href="<?= e(url('my-reservations.php')) ?>" aria-label="Mes réservations" title="Mes réservations">
                    <i data-lucide="ticket" aria-hidden="true"></i>
                </a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('events.php')) ?>">Découvrir les événements</a>
            </div>
        </nav>
    </div>
</header>

<main id="main" tabindex="-1">
<?php $__flash = render_flashes(); if ($__flash !== ''): ?>
    <div class="container flash-zone"><?= $__flash ?></div>
<?php endif; ?>
