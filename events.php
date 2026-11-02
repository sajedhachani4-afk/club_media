<?php
/** Liste des événements : recherche, filtres, pagination */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

sync_event_statuses();
$pdo = db();

$q      = mb_substr(get('q'), 0, 100);
$cat    = (int) get('category', '0');
$date   = get('date');
$status = get('status');
if ($date !== '' && !valid_date($date)) {
    $date = '';
}
if ($status !== '' && !isset(EVENT_STATUSES[$status])) {
    $status = '';
}

$where  = ['e.is_published = 1'];
$params = [];
if ($q !== '') {
    $where[]  = 'e.title LIKE ?';
    $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}
if ($cat > 0) {
    $where[]  = 'e.category_id = ?';
    $params[] = $cat;
}
if ($date !== '') {
    $where[]  = 'e.start_date <= ? AND e.end_date >= ?';
    $params[] = $date;
    $params[] = $date;
}
if ($status !== '') {
    $where[]  = 'e.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM events e WHERE $whereSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ITEMS_PER_PAGE_EVENTS);

// À venir / en cours d'abord (du plus proche au plus lointain), puis terminés (du plus récent au plus ancien)
$sql = "SELECT e.*, c.name AS category_name FROM events e
        LEFT JOIN event_categories c ON c.id = e.category_id
        WHERE $whereSql
        ORDER BY (e.status IN ('finished','cancelled')) ASC,
                 CASE WHEN e.status IN ('finished','cancelled') THEN NULL ELSE e.start_date END ASC,
                 CASE WHEN e.status IN ('finished','cancelled') THEN e.start_date END DESC,
                 e.start_time ASC
        LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'];
$st = $pdo->prepare($sql);
$st->execute($params);
$events = $st->fetchAll();

$cats = $pdo->query('SELECT id, name FROM event_categories ORDER BY name')->fetchAll();

$pageTitle   = 'Événements';
$pageDesc    = 'Tous les événements du Club Media ISET Gafsa : ateliers, projections, formations.';
$activeNav   = 'events';
$pageScripts = ['events.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Événements</span></nav>
        <h1>Nos <span class="text-grad">événements</span></h1>
        <p>Ateliers, projections, formations et rencontres : trouvez le prochain rendez-vous du club.</p>
    </div>
</section>

<section class="section" style="padding-top:48px">
    <div class="container">
        <form class="filters card" id="events-filters" method="get" action="<?= e(url('events.php')) ?>" data-autofilter role="search" aria-label="Filtrer les événements">
            <div class="field field--search">
                <label for="f-q">Rechercher un titre</label>
                <input type="search" id="f-q" name="q" value="<?= e($q) ?>" placeholder="Ex. : atelier photo" maxlength="100">
            </div>
            <div class="field">
                <label for="f-cat">Catégorie</label>
                <select id="f-cat" name="category">
                    <option value="0">Toutes</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-date">Date</label>
                <input type="date" id="f-date" name="date" value="<?= e($date) ?>">
            </div>
            <div class="field">
                <label for="f-status">Statut</label>
                <select id="f-status" name="status">
                    <option value="">Tous</option>
                    <?php foreach (EVENT_STATUSES as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <button class="btn btn-primary" type="submit">Filtrer</button>
            </div>
        </form>
        <?php if ($q !== '' || $cat > 0 || $date !== '' || $status !== ''): ?>
            <p class="muted"><?= (int) $pg['total'] ?> résultat(s). <a href="<?= e(url('events.php')) ?>">Réinitialiser les filtres</a></p>
        <?php endif; ?>

        <?php if ($events): ?>
            <div class="grid grid-3">
                <?php foreach ($events as $i => $ev): ?><?= render_event_card($ev, $i) ?><?php endforeach; ?>
            </div>
            <?= render_pagination($pg, ['q' => $q, 'category' => $cat ?: '', 'date' => $date, 'status' => $status]) ?>
        <?php else: ?>
            <div class="empty"><i data-lucide="calendar-x" aria-hidden="true"></i><p>Aucun événement ne correspond à votre recherche.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
