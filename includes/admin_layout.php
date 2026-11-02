<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Mise en page commune de l'administration + petits composants (graphiques SVG, formulaires).
 */
declare(strict_types=1);

/** Ouvre une page d'administration (en-tête + menu latéral). */
function admin_header(string $title, string $active, array $adminUser, array $opts = []): void
{
    send_security_headers();
    $siteName = setting('site_name', APP_NAME);
    $pdo      = db();
    $unread   = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    $pending  = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending_payment'")->fetchColumn();

    $nav = [
        ['dashboard', 'Dashboard',    'admin/dashboard.php',            'layout-dashboard', 0],
        ['events',    'Événements',   'admin/events.php',               'calendar-days',    0],
        ['tickets',   'Tickets',      'admin/tickets.php',              'scan-line',        0],
        ['reservations', 'Réservations', 'admin/reservations.php',      'ticket',           $pending],
        ['videos',    'Vidéos',       'admin/videos.php',               'film',             0],
        ['photos',    'Photos',       'admin/photos.php',               'images',           0],
        ['members',   'Membres',      'admin/team.php?view=members',    'users',            0],
        ['team',      'Équipe',       'admin/team.php?view=team',       'badge-check',      0],
        ['messages',  'Messages',     'admin/messages.php',             'mail',             $unread],
        ['settings',  'Paramètres',   'admin/settings.php',             'settings',         0],
    ];
    ?><!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> — Administration <?= e($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Space+Grotesk:wght@500;600;700&amp;display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#main">Aller au contenu principal</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Menu d'administration">
        <a class="brand" href="<?= e(url('admin/dashboard.php')) ?>">
            <img class="brand__logo" src="<?= e(setting('logo') !== '' ? upload_url('site', setting('logo')) : asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
            <span class="brand__text"><strong>CLUB MEDIA</strong><small>ADMIN</small></span>
        </a>
        <nav>
            <ul class="admin-nav">
                <?php foreach ($nav as [$key, $label, $href, $icon, $badge]): ?>
                    <li><a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?>>
                        <i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
                        <?php if ($badge > 0): ?><span class="admin-nav__badge" aria-label="<?= (int) $badge ?> en attente"><?= (int) $badge ?></span><?php endif; ?>
                    </a></li>
                <?php endforeach; ?>
                <li>
                    <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="admin-nav__logout"><i data-lucide="log-out" aria-hidden="true"></i><span>Déconnexion</span></button>
                    </form>
                </li>
            </ul>
        </nav>
        <p class="admin-sidebar__foot"><a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i data-lucide="external-link" aria-hidden="true"></i> Voir le site</a></p>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-btn" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Ouvrir le menu"><i data-lucide="menu" aria-hidden="true"></i></button>
            <h1 class="admin-title"><?= e($title) ?></h1>
            <span class="admin-user"><i data-lucide="user-round" aria-hidden="true"></i> <?= e($adminUser['username']) ?></span>
        </header>
        <main id="main" tabindex="-1" class="admin-content">
            <?= render_flashes() ?>
    <?php
}

/** Ferme la page. $scripts : fichiers JS supplémentaires dans assets/js/. */
function admin_footer(array $scripts = []): void
{
    ?>
        </main>
    </div>
</div>
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php foreach ($scripts as $js): ?>
<script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}

/** Ancre de redirection sûre pour revenir à la liste en conservant les filtres. */
function admin_back(string $page, array $query = []): void
{
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== null && $v !== 0);
    redirect('admin/' . $page . ($query ? '?' . http_build_query($query) : ''));
}

/** Graphique en barres SVG (aucune librairie). $values : entiers ≥ 0. */
function svg_bar_chart(array $labels, array $values, string $ariaLabel): string
{
    $n   = max(1, count($values));
    $max = max(1, (int) max($values ?: [0]));
    $w   = 640;
    $h   = 220;
    $padL = 30;
    $padB = 34;
    $padT = 14;
    $bw   = ($w - $padL - 10) / $n;
    $out  = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($ariaLabel) . '" preserveAspectRatio="xMidYMid meet">';
    $out .= '<defs><linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#A78BFA"/><stop offset="1" stop-color="#6D28D9"/></linearGradient></defs>';
    for ($g = 0; $g <= 4; $g++) {
        $y = $padT + ($h - $padT - $padB) * $g / 4;
        $out .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - 6) . '" y2="' . round($y, 1) . '" stroke="#29293D" stroke-width="1"/>';
        $out .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end" fill="#A1A1AA" font-size="11">' . round($max * (4 - $g) / 4) . '</text>';
    }
    foreach ($values as $i => $v) {
        $bh = ($h - $padT - $padB) * ((int) $v / $max);
        $x  = $padL + $i * $bw + $bw * 0.18;
        $y  = $h - $padB - $bh;
        $out .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw * 0.64, 1) . '" height="' . round($bh, 1) . '" rx="4" fill="url(#barGrad)"><title>' . e((string) ($labels[$i] ?? '')) . ' : ' . (int) $v . '</title></rect>';
        if ($n <= 16 || $i % 2 === 0) {
            $out .= '<text x="' . round($x + $bw * 0.32, 1) . '" y="' . ($h - 12) . '" text-anchor="middle" fill="#A1A1AA" font-size="10.5">' . e((string) ($labels[$i] ?? '')) . '</text>';
        }
    }
    return $out . '</svg>';
}

/** Barres horizontales HTML (accessibles, sans SVG) : [['label'=>..., 'value'=>..., 'class'=>...]] */
function hbar_list(array $rows): string
{
    $max = 1;
    foreach ($rows as $r) {
        $max = max($max, (int) $r['value']);
    }
    $html = '<ul class="hbars">';
    foreach ($rows as $r) {
        $pct = (int) round(((int) $r['value']) * 100 / $max);
        $html .= '<li><span class="hbars__label">' . e($r['label']) . '</span>'
            . '<span class="hbars__track"><span class="hbars__fill' . (!empty($r['class']) ? ' ' . e($r['class']) : '') . '" style="width:' . $pct . '%"></span></span>'
            . '<span class="hbars__val">' . (int) $r['value'] . '</span></li>';
    }
    return $html . '</ul>';
}

/** Détecte un envoi dépassant post_max_size (PHP vide alors $_POST et $_FILES). */
function post_too_large(): bool
{
    return is_post() && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/** Entier positif depuis POST/GET, ou null. */
function int_or_null(string $value): ?int
{
    return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
}
