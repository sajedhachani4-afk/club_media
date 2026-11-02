<?php
/** Galerie photos : albums par événement, masonry, lightbox */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$pdo   = db();
$album = (int) get('album', '0');

$albums = $pdo->query(
    'SELECT a.id, a.title, COUNT(p.id) AS n FROM albums a JOIN photos p ON p.album_id = a.id GROUP BY a.id, a.title ORDER BY MAX(p.created_at) DESC'
)->fetchAll();

$where  = '1=1';
$params = [];
if ($album > 0) {
    $where   = 'p.album_id = ?';
    $params[] = $album;
}
$cnt = $pdo->prepare("SELECT COUNT(*) FROM photos p WHERE $where");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ITEMS_PER_PAGE_PHOTOS);

$st = $pdo->prepare(
    "SELECT p.*, a.title AS album_title FROM photos p JOIN albums a ON a.id = p.album_id
     WHERE $where ORDER BY p.created_at DESC, p.sort_order ASC, p.id DESC
     LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']
);
$st->execute($params);
$photos = $st->fetchAll();

$pageTitle = 'Galerie photos';
$pageDesc  = 'Les photos des événements du Club Media ISET Gafsa.';
$activeNav = 'gallery';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Galerie photos</span></nav>
        <h1>Galerie <span class="text-grad">photos</span></h1>
        <p>Les meilleurs moments de nos événements, album par album.</p>
    </div>
</section>

<section class="section" style="padding-top:40px">
    <div class="container">
        <?php if ($albums): ?>
            <nav aria-label="Albums" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:34px">
                <a class="btn btn-sm <?= $album === 0 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('gallery.php')) ?>"<?= $album === 0 ? ' aria-current="true"' : '' ?>>Tous</a>
                <?php foreach ($albums as $a): ?>
                    <a class="btn btn-sm <?= $album === (int) $a['id'] ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('gallery.php?album=' . (int) $a['id'])) ?>"<?= $album === (int) $a['id'] ? ' aria-current="true"' : '' ?>><?= e($a['title']) ?> (<?= (int) $a['n'] ?>)</a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($photos): ?>
            <div class="masonry">
                <?php foreach ($photos as $p): $cap = (string) ($p['caption'] ?: $p['album_title']); ?>
                    <button type="button" class="masonry__item" data-lightbox="gallery" data-src="<?= e(photo_url($p)) ?>" data-caption="<?= e($cap) ?>" aria-label="Agrandir la photo : <?= e($cap) ?>">
                        <img src="<?= e(photo_thumb_url($p)) ?>" alt="<?= e((string) ($p['caption'] ?? '')) ?>" loading="lazy" width="<?= max(1, (int) $p['width']) ?>" height="<?= max(1, (int) $p['height']) ?>">
                        <span class="masonry__cap"><?= e($cap) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <?= render_pagination($pg, ['album' => $album ?: '']) ?>
        <?php else: ?>
            <div class="empty"><i data-lucide="image-off" aria-hidden="true"></i><p>Aucune photo pour le moment.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
