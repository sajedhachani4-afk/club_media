<?php
/** Réservations : recherche, filtres, confirmation de paiement, annulation, export CSV */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
sync_event_statuses();
$pdo = db();

$q       = mb_substr(get('q'), 0, 100);
$eventId = (int) get('event', '0');
$status  = get('status');
if (!isset(RESERVATION_STATUSES[$status])) {
    $status = '';
}
$filters = ['q' => $q, 'event' => $eventId ?: '', 'status' => $status];

if (is_post()) {
    csrf_require();
    $id     = (int) post('id', '0');
    $action = post('action');
    if ($action === 'confirm' && $id > 0) {
        $r = confirm_payment($id, (int) $admin['id']);
        if ($r['ok']) {
            log_activity('Paiement confirmé', 'Réservation #' . $id);
        }
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    } elseif ($action === 'cancel' && $id > 0) {
        $r = cancel_reservation($id);
        if ($r['ok']) {
            log_activity('Réservation annulée', '#' . $id);
        }
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
    admin_back('reservations.php', [
        'q'      => mb_substr(post('q'), 0, 100),
        'event'  => (int) post('event', '0'),
        'status' => isset(RESERVATION_STATUSES[post('status')]) ? post('status') : '',
        'page'   => (int) post('page', '1') > 1 ? (int) post('page') : '',
    ]);
}

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(r.full_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ? OR r.reservation_code LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($eventId > 0) {
    $where[]  = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($status !== '') {
    $where[]  = 'r.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);
$baseSql  = "FROM reservations r JOIN events e ON e.id = r.event_id WHERE $whereSql";

/* Export CSV (avec les mêmes filtres) */
if (get('export') === '1') {
    $st = $pdo->prepare("SELECT r.*, e.title AS event_title $baseSql ORDER BY r.created_at DESC");
    $st->execute($params);
    log_activity('Export CSV', 'Réservations');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservations-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                         // BOM UTF-8 pour Excel
    fputcsv($out, ['Code', 'Événement', 'Nom', 'E-mail', 'Téléphone', 'Tickets', 'Prix unitaire (TND)', 'Total (TND)', 'Statut', 'Paiement confirmé le', 'Date de réservation'], ';');
    while ($r = $st->fetch()) {
        fputcsv($out, array_map('csv_cell', [
            $r['reservation_code'], $r['event_title'], $r['full_name'], $r['email'], $r['phone'], $r['quantity'],
            $r['unit_price'], $r['total_amount'], RESERVATION_STATUSES[$r['status']] ?? $r['status'],
            $r['payment_confirmed_at'] ?? '', $r['created_at'],
        ]), ';');
    }
    fclose($out);
    exit;
}

$cnt = $pdo->prepare("SELECT COUNT(*) $baseSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), 20);
$st = $pdo->prepare("SELECT r.*, e.title AS event_title $baseSql ORDER BY r.created_at DESC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']);
$st->execute($params);
$rows = $st->fetchAll();
$eventsList = $pdo->query('SELECT id, title FROM events ORDER BY start_date DESC')->fetchAll();

admin_header('Réservations', 'reservations', $admin);
?>
<form class="toolbar" method="get" action="<?= e(url('admin/reservations.php')) ?>" role="search">
    <div class="field field--grow"><label for="q">Rechercher</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, téléphone, code"></div>
    <div class="field"><label for="event">Événement</label>
        <select id="event" name="event"><option value="0">Tous</option>
            <?php foreach ($eventsList as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= $eventId === (int) $ev['id'] ? ' selected' : '' ?>><?= e(truncate($ev['title'], 40)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="field"><label for="status">Statut</label>
        <select id="status" name="status"><option value="">Tous</option>
            <?php foreach (RESERVATION_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn btn-ghost" type="submit">Filtrer</button>
    <span class="toolbar__spacer"></span>
    <a class="btn btn-primary" href="<?= e(url('admin/reservations.php?' . http_build_query(array_filter($filters + ['export' => '1'], static fn($v) => $v !== '' && $v !== 0)))) ?>"><i data-lucide="download" aria-hidden="true"></i> Exporter en CSV</a>
</form>
<p class="muted"><?= (int) $pg['total'] ?> réservation(s).</p>

<div class="table-wrap">
<table class="table">
    <thead><tr><th>Code</th><th>Participant</th><th>Événement</th><th>Tickets</th><th>Total</th><th>Statut</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td data-label="Code"><strong><?= e($r['reservation_code']) ?></strong></td>
            <td data-label="Participant"><?= e($r['full_name']) ?><small><?= e($r['email']) ?> · <?= e($r['phone']) ?></small></td>
            <td data-label="Événement"><?= e(truncate($r['event_title'], 40)) ?></td>
            <td data-label="Tickets"><?= (int) $r['quantity'] ?></td>
            <td data-label="Total"><?= (float) $r['total_amount'] > 0 ? e(format_money($r['total_amount'])) : 'Gratuit' ?></td>
            <td data-label="Statut"><span class="badge badge--<?= e($r['status']) ?>"><?= e(RESERVATION_STATUSES[$r['status']] ?? $r['status']) ?></span></td>
            <td data-label="Date"><?= e(fr_datetime((string) $r['created_at'])) ?></td>
            <td class="cell-actions"><div class="actions">
                <a class="btn btn-ghost btn-xs" href="<?= e(url('ticket.php?t=' . $r['access_token'])) ?>" target="_blank" rel="noopener">Ticket</a>
                <?php if ($r['status'] === 'pending_payment'): ?>
                    <form method="post" action="<?= e(url('admin/reservations.php')) ?>" data-confirm="Confirmer que le paiement de <?= e(format_money($r['total_amount'])) ?> a bien été reçu ?"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="event" value="<?= $eventId ?>"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="page" value="<?= (int) $pg['page'] ?>">
                        <button class="btn btn-primary btn-xs" type="submit">Confirmer le paiement</button></form>
                <?php endif; ?>
                <?php if ($r['status'] !== 'cancelled'): ?>
                    <form method="post" action="<?= e(url('admin/reservations.php')) ?>" data-confirm="Annuler cette réservation ? Les tickets deviendront invalides et les places seront libérées."><?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="event" value="<?= $eventId ?>"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="page" value="<?= (int) $pg['page'] ?>">
                        <button class="btn btn-danger btn-xs" type="submit">Annuler</button></form>
                <?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:40px">Aucune réservation.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= render_pagination($pg, $filters) ?>
<?php admin_footer(); ?>
