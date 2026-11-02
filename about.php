<?php
/** À propos : contenu 100 % modifiable depuis Administration > Paramètres */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$stats = public_stats();

$lines = static function (string $key): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', setting($key)) ?: []), static fn($l) => $l !== ''));
};
$activities = $lines('about_activities');
$values     = array_map(static fn($l) => array_pad(explode('|', $l, 2), 2, ''), $lines('about_values'));
$timeline   = array_map(static fn($l) => array_pad(explode('|', $l, 3), 3, ''), $lines('about_timeline'));
$icons      = ['sparkles', 'handshake', 'flame', 'lightbulb', 'heart', 'shield-check'];

$todo = static fn(string $t): bool => stripos($t, 'À compléter') === 0;

$photos = db()->query('SELECT p.* FROM photos p ORDER BY p.created_at DESC, p.id DESC LIMIT 3')->fetchAll();

$pageTitle = 'À propos';
$pageDesc  = setting('club_description');
$activeNav = 'about';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>À propos</span></nav>
        <h1>À propos du <span class="text-grad">Club Media</span></h1>
        <p><?= e(setting('club_description')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container split">
        <div data-gsap="head">
            <span class="eyebrow">Notre histoire</span>
            <h2 style="font-size:clamp(1.9rem,4vw,3rem)">D'où <span class="text-grad">venons-nous</span> ?</h2>
            <div class="prose"><?= paragraphs(setting('about_history')) ?></div>
            <?php if ($todo(setting('about_history')) && admin_current()): ?>
                <p class="placeholder-note">Texte à compléter : Administration &gt; Paramètres &gt; Page « À propos ».</p>
            <?php endif; ?>
        </div>
        <div class="about-photos" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px" data-aos="fade-left">
            <?php if ($photos): foreach ($photos as $i => $p): ?>
                <img class="card" src="<?= e(photo_thumb_url($p)) ?>" alt="<?= e((string) ($p['caption'] ?? 'Photo du club')) ?>" loading="lazy"
                     style="width:100%;object-fit:cover;aspect-ratio:<?= $i === 0 ? '16/10' : '1/1' ?>;<?= $i === 0 ? 'grid-column:span 2;' : '' ?>">
            <?php endforeach; else: ?>
                <img class="card" src="<?= e(asset('images/event-placeholder.svg')) ?>" alt="" style="grid-column:span 2;width:100%;aspect-ratio:16/10;object-fit:cover">
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="container split">
        <div class="card about-box" data-aos="fade-up">
            <span class="eyebrow">Notre mission</span>
            <p class="muted" style="font-size:1.08rem;margin:0"><?= e(setting('about_mission')) ?></p>
        </div>
        <div class="card about-box" data-aos="fade-up" data-aos-delay="100">
            <span class="eyebrow">Notre vision</span>
            <p class="muted" style="font-size:1.08rem;margin:0"><?= e(setting('about_vision')) ?></p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head section-head--center" data-gsap="head">
            <span class="eyebrow">Nos activités</span>
            <h2>Ce que nous <span class="text-grad">faisons</span></h2>
        </div>
        <ul class="check-list" style="max-width:720px;margin-inline:auto">
            <?php foreach ($activities as $a): ?>
                <li><i data-lucide="check-circle-2" aria-hidden="true"></i><span><?= e($a) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section section--alt">
    <div class="container">
        <div class="section-head section-head--center" data-gsap="head">
            <span class="eyebrow">Nos valeurs</span>
            <h2>Ce qui nous <span class="text-grad">anime</span></h2>
        </div>
        <div class="values">
            <?php foreach ($values as $i => [$t, $d]): ?>
                <div class="card value" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
                    <div class="value__icon"><i data-lucide="<?= e($icons[$i % count($icons)]) ?>" aria-hidden="true"></i></div>
                    <h3><?= e($t) ?></h3>
                    <p><?= e($d) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head section-head--center" data-gsap="head">
            <span class="eyebrow">Parcours</span>
            <h2>Notre <span class="text-grad">chronologie</span></h2>
            <?php if (admin_current()): ?><p class="placeholder-note">Les étapes sont des repères à remplacer par des dates vérifiées (Administration &gt; Paramètres).</p><?php endif; ?>
        </div>
        <div class="timeline">
            <span class="timeline__fill" aria-hidden="true"></span>
            <?php foreach ($timeline as [$label, $title, $text]): ?>
                <div class="timeline__item">
                    <span class="timeline__label"><?= e($label) ?></span>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="container">
        <div class="section-head section-head--center" data-gsap="head">
            <span class="eyebrow">Notre communauté</span>
            <h2>Une <span class="text-grad">communauté</span> créative</h2>
            <p><?= e(setting('about_community')) ?></p>
        </div>
        <div class="stats" style="margin-top:0">
            <div class="stat"><span class="stat__num" data-count="<?= (int) $stats['events'] ?>"><?= (int) $stats['events'] ?></span><span class="stat__label">Événements</span></div>
            <div class="stat"><span class="stat__num" data-count="<?= (int) $stats['members'] ?>"><?= (int) $stats['members'] ?></span><span class="stat__label">Membres</span></div>
            <div class="stat"><span class="stat__num" data-count="<?= (int) $stats['media'] ?>"><?= (int) $stats['media'] ?></span><span class="stat__label">Photos et vidéos</span></div>
            <div class="stat"><span class="stat__num" data-count="<?= (int) $stats['participants'] ?>"><?= (int) $stats['participants'] ?></span><span class="stat__label">Participants</span></div>
        </div>
        <div class="cta-band" style="margin-top:60px">
            <h2>Envie de <span class="text-grad">nous rejoindre</span> ?</h2>
            <p>Écrivez-nous pour participer à nos projets ou proposer une collaboration.</p>
            <a class="btn btn-primary btn-lg" href="<?= e(url('contact.php')) ?>">Nous contacter</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
