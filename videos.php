<?php
/** Galerie vidéos : recherche, filtre par événement, vidéos récentes, lecteur modal */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$pdo     = db();
$q       = mb_substr(get('q'), 0, 100);
$eventId = (int) get('event', '0');

$where  = ['v.is_published = 1'];
$params = [];
if ($q !== '') {
    $where[]  = 'v.title LIKE ?';
    $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}
if ($eventId > 0) {
    $where[]  = 'v.event_id = ?';
    $params[] = $eventId;
}
$whereSql = implode(' AND ', $where);
$filtered = ($q !== '' || $eventId > 0);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM videos v WHERE $whereSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ITEMS_PER_PAGE_VIDEOS);

$st = $pdo->prepare(
    "SELECT v.*, ev.title AS event_title FROM videos v LEFT JOIN events ev ON ev.id = v.event_id
     WHERE $whereSql ORDER BY v.created_at DESC, v.id DESC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']
);
$st->execute($params);
$videos = $st->fetchAll();

$recent = [];
if (!$filtered && $pg['page'] === 1) {
    $recent = $pdo->query(
        'SELECT v.*, ev.title AS event_title FROM videos v LEFT JOIN events ev ON ev.id = v.event_id
         WHERE v.is_published = 1 ORDER BY v.created_at DESC, v.id DESC LIMIT 6'
    )->fetchAll();
}

$events = $pdo->query(
    'SELECT DISTINCT ev.id, ev.title FROM events ev JOIN videos v ON v.event_id = ev.id WHERE v.is_published = 1 ORDER BY ev.title'
)->fetchAll();

$pageTitle   = 'Galerie vidéos';
$pageDesc    = 'Les vidéos et aftermovies du Club Media ISET Gafsa.';
$activeNav   = 'gallery';
$pageScripts = ['events.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Galerie vidéos</span></nav>
        <h1>Galerie <span class="text-grad">vidéos</span></h1>
        <p>Aftermovies, reportages et créations du club.</p>
    </div>
</section>

<?php if ($recent): ?>
<section class="section" style="padding-bottom:0">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Nouveautés</span><h2>Vidéos récentes</h2></div>
        <div class="swiper" data-swiper aria-label="Vidéos récentes">
            <div class="swiper-wrapper">
                <?php foreach ($recent as $i => $v): ?>
                    <div class="swiper-slide"><?= render_video_card($v, $i, false) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-prev" aria-label="Précédent"></div>
            <div class="swiper-button-next" aria-label="Suivant"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:48px">
    <div class="container">
        <form class="filters card" id="videos-filters" method="get" action="<?= e(url('videos.php')) ?>" data-autofilter role="search" aria-label="Filtrer les vidéos" style="grid-template-columns:2fr 1.4fr auto">
            <div class="field field--search">
                <label for="v-q">Rechercher un titre</label>
                <input type="search" id="v-q" name="q" value="<?= e($q) ?>" placeholder="Ex. : aftermovie" maxlength="100">
            </div>
            <div class="field">
                <label for="v-event">Événement</label>
                <select id="v-event" name="event">
                    <option value="0">Tous les événements</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>"<?= $eventId === (int) $ev['id'] ? ' selected' : '' ?>><?= e($ev['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><button class="btn btn-primary" type="submit">Filtrer</button></div>
        </form>

        <h2 style="font-size:1.5rem;margin-bottom:24px"><?= $filtered ? 'Résultats (' . (int) $pg['total'] . ')' : 'Toutes les vidéos' ?></h2>
        <?php if ($videos): ?>
            <div class="grid grid-3">
                <?php foreach ($videos as $i => $v): ?><?= render_video_card($v, $i) ?><?php endforeach; ?>
            </div>
            <?= render_pagination($pg, ['q' => $q, 'event' => $eventId ?: '']) ?>
        <?php else: ?>
            <div class="empty"><i data-lucide="film" aria-hidden="true"></i><p><?= $filtered ? 'Aucune vidéo ne correspond à votre recherche.' : 'Aucune vidéo pour le moment.' ?></p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
