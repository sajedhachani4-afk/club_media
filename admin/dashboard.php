<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
sync_event_statuses();
$pdo = db();

$one = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$totals = [
    'events'    => $one('SELECT COUNT(*) FROM events'),
    'upcoming'  => $one("SELECT COUNT(*) FROM events WHERE status IN ('upcoming','ongoing') AND is_published = 1"),
    'reserv'    => $one('SELECT COUNT(*) FROM reservations'),
    'pending'   => $one("SELECT COUNT(*) FROM reservations WHERE status = 'pending_payment'"),
    'confirmed' => $one("SELECT COUNT(*) FROM tickets WHERE status IN ('valid','used')"),
    'videos'    => $one('SELECT COUNT(*) FROM videos'),
    'photos'    => $one('SELECT COUNT(*) FROM photos'),
    'messages'  => $one('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
];

// Réservations des 14 derniers jours
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = 0;
}
$st = $pdo->prepare('SELECT DATE(created_at) d, COUNT(*) n FROM reservations WHERE created_at >= ? GROUP BY DATE(created_at)');
$st->execute([array_key_first($days) . ' 00:00:00']);
foreach ($st->fetchAll() as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']] = (int) $row['n'];
    }
}
$dayLabels = array_map(static fn($d) => date('d/m', strtotime($d)), array_keys($days));

// Tickets par statut
$tStatus = $pdo->query('SELECT status, COUNT(*) n FROM tickets GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$statusRows = [
    ['label' => 'Valides',          'value' => (int) ($tStatus['valid'] ?? 0),     'class' => 'is-ok'],
    ['label' => 'Utilisés',         'value' => (int) ($tStatus['used'] ?? 0),      'class' => ''],
    ['label' => 'En attente',       'value' => (int) ($tStatus['pending'] ?? 0),   'class' => 'is-warn'],
    ['label' => 'Annulés',          'value' => (int) ($tStatus['cancelled'] ?? 0), 'class' => 'is-bad'],
];

// Remplissage des prochains événements
$fill = $pdo->query(
    "SELECT title, capacity, capacity - available_seats AS taken FROM events
     WHERE is_published = 1 AND status IN ('upcoming','ongoing') ORDER BY start_date, start_time LIMIT 6"
)->fetchAll();
$fillRows = array_map(static fn($r) => ['label' => $r['title'], 'value' => (int) $r['taken']], $fill);

$logs = $pdo->query(
    'SELECT l.*, a.username FROM admin_activity_logs l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 10'
)->fetchAll();

admin_header('Dashboard', 'dashboard', $admin);
$cards = [
    ['calendar-days', 'Événements', $totals['events'], false],
    ['calendar-clock', 'Événements à venir', $totals['upcoming'], false],
    ['ticket', 'Réservations', $totals['reserv'], false],
    ['hourglass', 'Paiements en attente', $totals['pending'], $totals['pending'] > 0],
    ['badge-check', 'Tickets confirmés', $totals['confirmed'], false],
    ['film', 'Vidéos', $totals['videos'], false],
    ['images', 'Photos', $totals['photos'], false],
    ['mail', 'Messages non lus', $totals['messages'], $totals['messages'] > 0],
];
?>
<div class="stat-cards">
    <?php foreach ($cards as [$icon, $label, $num, $warn]): ?>
        <div class="stat-card<?= $warn ? ' stat-card--warn' : '' ?>">
            <span class="stat-card__icon"><i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i></span>
            <div><span class="stat-card__num"><?= (int) $num ?></span><span class="stat-card__label"><?= e($label) ?></span></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <section class="panel" aria-labelledby="c1">
        <h2 id="c1">Réservations — 14 derniers jours</h2>
        <?= svg_bar_chart($dayLabels, array_values($days), 'Réservations par jour sur les 14 derniers jours') ?>
    </section>
    <section class="panel" aria-labelledby="c2">
        <h2 id="c2">Tickets par statut</h2>
        <?= hbar_list($statusRows) ?>
        <h2 id="c3" style="margin-top:28px">Remplissage des prochains événements</h2>
        <?= $fillRows ? hbar_list($fillRows) : '<p class="muted">Aucun événement à venir.</p>' ?>
    </section>
</div>

<section class="panel" aria-labelledby="c4">
    <div class="panel__head">
        <h2 id="c4">Activité récente</h2>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/events.php?action=new')) ?>">+ Nouvel événement</a>
    </div>
    <?php if ($logs): ?>
        <ul class="activity">
            <?php foreach ($logs as $l): ?>
                <li><time datetime="<?= e((string) $l['created_at']) ?>"><?= e(fr_datetime((string) $l['created_at'])) ?></time>
                    <span><strong><?= e((string) ($l['username'] ?? 'Système')) ?></strong> — <?= e($l['action']) ?><?= $l['details'] ? ' : ' . e($l['details']) : '' ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Aucune activité enregistrée.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
