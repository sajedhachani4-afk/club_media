<?php
/** Détail d'un événement */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

sync_event_statuses();
$id = (int) get('id', '0');
$ev = $id > 0 ? fetch_event($id) : null;
if ($ev === null) {
    http_response_code(404);
    $pageTitle = 'Événement introuvable';
    $activeNav = 'events';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty"><i data-lucide="calendar-x" aria-hidden="true"></i><h1 style="font-size:1.6rem">Événement introuvable</h1>'
        . '<p>Cet événement n\'existe pas ou n\'est plus publié.</p><a class="btn btn-primary" href="' . e(url('events.php')) . '">Voir les événements</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = db();

// Médias liés : photos des albums de l'événement + vidéos publiées
$ph = $pdo->prepare('SELECT p.* FROM photos p JOIN albums a ON a.id = p.album_id WHERE a.event_id = ? ORDER BY p.sort_order, p.id LIMIT 12');
$ph->execute([$id]);
$photos = $ph->fetchAll();

$vd = $pdo->prepare('SELECT v.*, NULL AS event_title FROM videos v WHERE v.event_id = ? AND v.is_published = 1 ORDER BY v.created_at DESC LIMIT 6');
$vd->execute([$id]);
$videos = $vd->fetchAll();

// Événements similaires : même catégorie d'abord, puis les plus proches
$sim = $pdo->prepare(
    "SELECT e.*, c.name AS category_name FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
     WHERE e.is_published = 1 AND e.id <> ? AND e.status <> 'cancelled'
     ORDER BY (e.category_id <=> ?) DESC, (e.status IN ('upcoming','ongoing')) DESC, ABS(DATEDIFF(e.start_date, ?)) ASC LIMIT 3"
);
$sim->execute([$id, $ev['category_id'], $ev['start_date']]);
$similar = $sim->fetchAll();

$bookable = event_is_bookable($ev);
$isPaid   = $ev['ticket_type'] === 'paid' && (float) $ev['ticket_price'] > 0;
$taken    = max(0, (int) $ev['capacity'] - (int) $ev['available_seats']);
$pct      = (int) $ev['capacity'] > 0 ? min(100, (int) round($taken * 100 / (int) $ev['capacity'])) : 0;

$pageTitle = $ev['title'];
$pageDesc  = truncate($ev['description'], 160);
$pageImage = !empty($ev['cover_image']) ? (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . event_cover_url($ev) : null;
$activeNav = 'events';
require __DIR__ . '/includes/header.php';
?>
<div class="event-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane" style="justify-content:flex-start"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><a href="<?= e(url('events.php')) ?>">Événements</a><span aria-hidden="true">/</span><span><?= e(truncate($ev['title'], 40)) ?></span></nav>
        <img class="event-hero__img" src="<?= e(event_cover_url($ev)) ?>" alt="Affiche de l'événement <?= e($ev['title']) ?>" width="1200" height="514">
    </div>
</div>

<div class="container event-layout">
    <article>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?= render_status_badge($ev['status']) ?>
            <?php if (!empty($ev['category_name'])): ?><span class="badge badge--category"><?= e($ev['category_name']) ?></span><?php endif; ?>
        </div>
        <h1><?= e($ev['title']) ?></h1>
        <div class="prose"><?= paragraphs($ev['description']) ?></div>

        <?php if ($photos): ?>
            <h2 style="margin-top:48px">Photos de l'événement</h2>
            <div class="masonry">
                <?php foreach ($photos as $p): ?>
                    <button type="button" class="masonry__item" data-lightbox="event" data-src="<?= e(photo_url($p)) ?>" data-caption="<?= e((string) ($p['caption'] ?? '')) ?>" aria-label="Agrandir la photo">
                        <img src="<?= e(photo_thumb_url($p)) ?>" alt="<?= e((string) ($p['caption'] ?? '')) ?>" loading="lazy" width="<?= (int) $p['width'] ?>" height="<?= (int) $p['height'] ?>">
                        <?php if (!empty($p['caption'])): ?><span class="masonry__cap"><?= e($p['caption']) ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($videos): ?>
            <h2 style="margin-top:48px">Vidéos de l'événement</h2>
            <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
                <?php foreach ($videos as $i => $v): ?><?= render_video_card($v, $i, false) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <aside>
        <div class="card side-card">
            <span class="price"><?= e(event_price_label($ev)) ?></span>
            <span class="muted"><?= $isPaid ? 'par ticket' : 'Entrée libre sur réservation' ?></span>

            <ul class="info-list">
                <li><i data-lucide="calendar" aria-hidden="true"></i><div><small>Date et heure</small><?= e(event_when_label($ev)) ?></div></li>
                <li><i data-lucide="map-pin" aria-hidden="true"></i><div><small>Lieu</small><?= e($ev['location']) ?></div></li>
                <li><i data-lucide="user-round" aria-hidden="true"></i><div><small>Organisateur</small><?= e($ev['organizer']) ?></div></li>
                <li><i data-lucide="ticket" aria-hidden="true"></i><div><small>Tickets disponibles</small>
                    <strong><?= (int) $ev['available_seats'] ?></strong> / <?= (int) $ev['capacity'] ?>
                    <div class="seats-bar" role="img" aria-label="<?= $pct ?> % des places réservées"><span style="width:<?= $pct ?>%"></span></div>
                </div></li>
            </ul>

            <?php if ($bookable): ?>
                <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('reserve.php?event=' . $id)) ?>">Réserver ma place</a>
                <?php if ($isPaid): ?><p class="field-hint" style="margin-top:12px">Billet payant : la réservation est validée après confirmation du paiement par un administrateur.</p><?php endif; ?>
            <?php else: ?>
                <span class="btn btn-disabled btn-lg btn-block" aria-disabled="true">
                    <?= $ev['status'] === 'cancelled' ? 'Événement annulé' : ($ev['status'] === 'finished' ? 'Événement terminé' : 'Complet') ?>
                </span>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php if ($similar): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section-head"><span class="eyebrow">À découvrir aussi</span><h2>Événements similaires</h2></div>
        <div class="grid grid-3">
            <?php foreach ($similar as $i => $s): ?><?= render_event_card($s, $i) ?><?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
