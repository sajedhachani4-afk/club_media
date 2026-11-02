<?php
/** Notre équipe */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$members = db()->query(
    'SELECT * FROM team_members WHERE show_on_team_page = 1 AND is_active = 1 ORDER BY display_order ASC, id ASC'
)->fetchAll();

$pageTitle = 'Notre équipe';
$pageDesc  = 'Les membres du Club Media ISET Gafsa.';
$activeNav = 'team';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Notre équipe</span></nav>
        <h1>Notre <span class="text-grad">équipe</span></h1>
        <p>Les étudiants qui font vivre le Club Media.</p>
    </div>
</section>

<section class="section" style="padding-top:56px">
    <div class="container">
        <?php if ($members): ?>
            <div class="team-grid">
                <?php foreach ($members as $i => $m):
                    $photo = !empty($m['photo']) ? upload_url('team', $m['photo']) : asset('images/avatar-placeholder.svg');
                    $link  = (string) ($m['social_url'] ?? '');
                    $host  = strtolower((string) parse_url($link, PHP_URL_HOST));
                    $label = str_contains($host, 'linkedin') ? 'LinkedIn' : (str_contains($host, 'instagram') ? 'Instagram' : 'Profil');
                    $icon  = str_contains($host, 'linkedin') ? 'linkedin' : (str_contains($host, 'instagram') ? 'instagram' : null);
                ?>
                    <article class="team-card card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
                        <div class="team-card__photo"><img src="<?= e($photo) ?>" alt="Photo de <?= e($m['full_name']) ?>" loading="lazy" width="400" height="400"></div>
                        <div class="team-card__body">
                            <span class="team-card__role"><?= e($m['role']) ?></span>
                            <h3><?= e($m['full_name']) ?></h3>
                            <?php if (!empty($m['description'])): ?><p><?= e(truncate((string) $m['description'], 140)) ?></p><?php endif; ?>
                            <?php if ($link !== '' && valid_url($link)): ?>
                                <a class="icon-link" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label . ' de ' . $m['full_name']) ?>">
                                    <?= $icon ? social_icon($icon) : '<i data-lucide="external-link" aria-hidden="true"></i>' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty"><i data-lucide="users" aria-hidden="true"></i><p>La présentation de l'équipe arrive bientôt.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
