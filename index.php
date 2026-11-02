<?php
/** Accueil */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

sync_event_statuses();
$pdo   = db();
$stats = public_stats();

$st = $pdo->query(
    "SELECT e.*, c.name AS category_name FROM events e
     LEFT JOIN event_categories c ON c.id = e.category_id
     WHERE e.is_published = 1 AND e.status IN ('upcoming','ongoing')
     ORDER BY e.start_date ASC, e.start_time ASC LIMIT 3"
);
$upcoming = $st->fetchAll();

// Derniers médias : 4 photos + 2 vidéos mélangées par date
$photos = $pdo->query(
    'SELECT p.*, a.title AS album_title FROM photos p JOIN albums a ON a.id = p.album_id ORDER BY p.created_at DESC, p.id DESC LIMIT 6'
)->fetchAll();
$videos = $pdo->query(
    'SELECT v.*, ev.title AS event_title FROM videos v LEFT JOIN events ev ON ev.id = v.event_id
     WHERE v.is_published = 1 ORDER BY v.created_at DESC, v.id DESC LIMIT 6'
)->fetchAll();

$tiles = [];
foreach ($photos as $p) {
    $tiles[] = ['type' => 'photo', 'ts' => strtotime((string) $p['created_at']), 'row' => $p];
}
foreach ($videos as $v) {
    $tiles[] = ['type' => 'video', 'ts' => strtotime((string) $v['created_at']), 'row' => $v];
}
usort($tiles, static fn($a, $b) => $b['ts'] <=> $a['ts']);
$tiles = array_slice($tiles, 0, 6);

// Vidéo d'arrière-plan : une vidéo envoyée (source « upload ») choisie dans les paramètres
$heroVideo = null;
$heroVideoId = (int) setting('hero_video_id', '0');
if ($heroVideoId > 0) {
    $hv = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND source_type = 'upload' AND is_published = 1 AND video_file IS NOT NULL");
    $hv->execute([$heroVideoId]);
    $heroVideo = $hv->fetch() ?: null;
}
$heroImage = setting('hero_image') !== '' ? upload_url('site', setting('hero_image')) : asset('images/hero-fallback.svg');

$pageTitle = '';
$activeNav = 'home';
$bodyClass = 'home';
$pageScripts = ['events.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="hero" aria-labelledby="hero-title">
    <div class="hero__media" aria-hidden="true">
        <?php if ($heroVideo): ?>
            <video autoplay muted loop playsinline preload="metadata" poster="<?= e($heroImage) ?>">
                <source src="<?= e(upload_url('videos', $heroVideo['video_file'])) ?>" type="video/<?= str_ends_with((string) $heroVideo['video_file'], '.webm') ? 'webm' : 'mp4' ?>">
            </video>
        <?php else: ?>
            <img src="<?= e($heroImage) ?>" alt="" width="1600" height="900">
        <?php endif; ?>
    </div>
    <div class="hero__overlay" aria-hidden="true"></div>
    <span class="hero__glow hero__glow--1" aria-hidden="true"></span>
    <span class="hero__glow hero__glow--2" aria-hidden="true"></span>
    <span class="hero__glow hero__glow--3" aria-hidden="true"></span>

    <div class="container hero__content">
        <span class="hero__tag"><?= e(setting('tagline', 'Create. Capture. Inspire.')) ?></span>
        <h1 class="hero__title" id="hero-title">
            <span class="line"><span class="line__inner">We create moments.</span></span>
            <span class="line line--accent"><span class="line__inner">We capture memories.</span></span>
        </h1>
        <p class="hero__lead">Bienvenue au Club Media ISET Gafsa. Découvrez nos événements, nos créations et les moments qui font vivre notre communauté.</p>
        <div class="hero__actions">
            <a class="btn btn-primary btn-lg" href="<?= e(url('events.php')) ?>"><i data-lucide="calendar-days" aria-hidden="true"></i> Explorer les événements</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('gallery.php')) ?>"><i data-lucide="images" aria-hidden="true"></i> Découvrir notre galerie</a>
        </div>
    </div>
    <span class="scroll-cue" aria-hidden="true"></span>
</section>

<div class="container">
    <div class="stats" role="list" aria-label="Le club en chiffres">
        <div class="stat" role="listitem"><span class="stat__num" data-count="<?= (int) $stats['events'] ?>"><?= (int) $stats['events'] ?></span><span class="stat__label">Événements</span></div>
        <div class="stat" role="listitem"><span class="stat__num" data-count="<?= (int) $stats['members'] ?>"><?= (int) $stats['members'] ?></span><span class="stat__label">Membres</span></div>
        <div class="stat" role="listitem"><span class="stat__num" data-count="<?= (int) $stats['media'] ?>"><?= (int) $stats['media'] ?></span><span class="stat__label">Photos et vidéos</span></div>
        <div class="stat" role="listitem"><span class="stat__num" data-count="<?= (int) $stats['participants'] ?>"><?= (int) $stats['participants'] ?></span><span class="stat__label">Participants</span></div>
    </div>
</div>

<section class="section" aria-labelledby="upcoming-title">
    <div class="container">
        <div class="section-head" data-gsap="head">
            <span class="eyebrow">Agenda</span>
            <h2 id="upcoming-title">Prochains <span class="text-grad">événements</span></h2>
            <p>Réservez votre place pour les prochaines rencontres du club.</p>
        </div>
        <?php if ($upcoming): ?>
            <div class="grid grid-3">
                <?php foreach ($upcoming as $i => $ev): ?><?= render_event_card($ev, $i) ?><?php endforeach; ?>
            </div>
            <p style="text-align:center;margin-top:40px"><a class="btn btn-ghost" href="<?= e(url('events.php')) ?>">Voir tous les événements</a></p>
        <?php else: ?>
            <div class="empty"><i data-lucide="calendar-x" aria-hidden="true"></i><p>Aucun événement à venir pour le moment. Revenez bientôt !</p></div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--alt" aria-labelledby="media-title">
    <div class="container">
        <div class="section-head" data-gsap="head">
            <span class="eyebrow">Galerie</span>
            <h2 id="media-title">Derniers <span class="text-grad">médias</span></h2>
            <p>Photos et vidéos de nos derniers événements.</p>
        </div>
        <?php if ($tiles): ?>
            <div class="media-grid">
                <?php foreach ($tiles as $t): $r = $t['row']; ?>
                    <?php if ($t['type'] === 'photo'): ?>
                        <button type="button" class="media-tile" data-lightbox="home" data-src="<?= e(photo_url($r)) ?>" data-caption="<?= e((string) ($r['caption'] ?: $r['album_title'])) ?>" aria-label="Agrandir la photo : <?= e((string) ($r['caption'] ?: $r['album_title'])) ?>">
                            <img src="<?= e(photo_thumb_url($r)) ?>" alt="<?= e((string) ($r['caption'] ?? '')) ?>" loading="lazy">
                            <span class="media-tile__label"><?= e((string) ($r['caption'] ?: $r['album_title'])) ?></span>
                        </button>
                    <?php else:
                        $thumb = video_thumb_url($r);
                        $src   = $r['source_type'] === 'youtube' ? (string) $r['youtube_id'] : upload_url('videos', $r['video_file']);
                    ?>
                        <button type="button" class="media-tile" data-video data-source="<?= e((string) $r['source_type']) ?>" data-src="<?= e($src) ?>" data-title="<?= e($r['title']) ?>" data-desc="<?= e((string) ($r['description'] ?? '')) ?>"<?= $thumb !== null && $r['source_type'] === 'upload' ? ' data-poster="' . e($thumb) . '"' : '' ?> aria-label="Lire la vidéo : <?= e($r['title']) ?>">
                            <?php if ($thumb !== null): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy"><?php else: ?><img src="<?= e(asset('images/event-placeholder.svg')) ?>" alt="" loading="lazy"><?php endif; ?>
                            <span class="play-chip"><i data-lucide="play" aria-hidden="true"></i></span>
                            <span class="media-tile__label"><?= e($r['title']) ?></span>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <p style="text-align:center;margin-top:36px">
                <a class="btn btn-ghost" href="<?= e(url('gallery.php')) ?>">Toutes les photos</a>
                <a class="btn btn-ghost" href="<?= e(url('videos.php')) ?>">Toutes les vidéos</a>
            </p>
        <?php else: ?>
            <div class="empty"><i data-lucide="image-off" aria-hidden="true"></i><p>Les premiers médias arrivent bientôt.</p></div>
        <?php endif; ?>
    </div>
</section>

<section class="section" aria-labelledby="about-title">
    <div class="container split">
        <div data-gsap="head">
            <span class="eyebrow">À propos</span>
            <h2 id="about-title" style="font-size:clamp(1.9rem,4vw,3rem)">Le club qui <span class="text-grad">raconte</span> l'institut</h2>
            <p class="muted" style="font-size:1.08rem"><?= e(setting('club_description')) ?></p>
            <ul class="check-list">
                <?php foreach (array_filter(array_map('trim', explode("\n", setting('about_activities')))) as $act): ?>
                    <li><i data-lucide="check-circle-2" aria-hidden="true"></i><span><?= e($act) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:26px"><a class="btn btn-primary" href="<?= e(url('about.php')) ?>">En savoir plus</a></p>
        </div>
        <div class="card about-box" data-aos="fade-left">
            <h3>Notre mission</h3>
            <p class="muted"><?= e(setting('about_mission')) ?></p>
            <h3>Notre vision</h3>
            <p class="muted" style="margin:0"><?= e(setting('about_vision')) ?></p>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <div class="cta-band">
            <h2>Rejoignez l'expérience <span class="text-grad">Club Media</span></h2>
            <p>Une question, une idée de projet, l'envie de rejoindre l'équipe ? Écrivez-nous.</p>
            <a class="btn btn-primary btn-lg" href="<?= e(url('contact.php')) ?>">Nous contacter</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
