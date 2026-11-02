<?php
/**
 * Tickets : scanner QR (caméra) / saisie manuelle, validation de l'entrée, liste des tickets.
 * Le même fichier répond aux requêtes AJAX du scanner (JSON).
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

/* ------------------------------------------------------------------ AJAX : contrôle / validation */
if (is_post() && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
    csrf_require();
    $action = post('action');
    $value  = mb_substr(post('value'), 0, 600);

    if (rate_limited('scan_' . $admin['id'], 400, 300)) {
        json_response(['ok' => false, 'state' => 'invalid', 'label' => 'Trop de requêtes', 'message' => 'Patientez quelques instants.'], 429);
    }
    rate_limit_hit('scan_' . $admin['id']);

    // Saisie manuelle possible avec le numéro imprimé du ticket (CM-XXXXXXXX)
    $token = ticket_token_extract($value);
    if ($token === null && preg_match('/^CM-[A-Z2-9]{8}$/i', trim($value))) {
        $f = $pdo->prepare('SELECT token FROM tickets WHERE ticket_number = ?');
        $f->execute([strtoupper(trim($value))]);
        $token = $f->fetchColumn() ?: null;
    }

    $describe = static function (?array $t): ?array {
        if ($t === null) {
            return null;
        }
        return [
            'token'   => $t['token'],
            'number'  => $t['ticket_number'],
            'holder'  => $t['holder_name'],
            'event'   => $t['event_title'],
            'when'    => fr_date($t['start_date'], true) . ' à ' . fr_time($t['start_time']),
            'location'=> $t['location'],
            'used_at' => $t['used_at'] ? fr_datetime((string) $t['used_at']) : null,
        ];
    };

    $ticket = $token !== null ? ticket_by_token($token) : null;

    if ($action === 'checkin') {
        if ($ticket === null) {
            json_response(['ok' => true, 'state' => 'invalid', 'label' => TICKET_STATE_LABELS['invalid'], 'message' => 'Aucun ticket ne correspond à ce code.']);
        }
        if (check_in_ticket($token, (int) $admin['id'])) {
            log_activity('Entrée validée', $ticket['ticket_number'] . ' — ' . $ticket['holder_name']);
            $ticket = ticket_by_token($token);
            json_response(['ok' => true, 'state' => 'used', 'label' => 'Entrée validée ✔', 'message' => 'Ticket marqué comme utilisé : il ne pourra plus servir.', 'ticket' => $describe($ticket)]);
        }
        // Refus : on renvoie l'état réel (déjà utilisé, annulé, non confirmé…)
        $ticket = ticket_by_token($token);
        $state  = ticket_state($ticket);
        json_response(['ok' => true, 'state' => $state, 'label' => TICKET_STATE_LABELS[$state], 'message' => 'Entrée refusée.', 'ticket' => $describe($ticket)]);
    }

    // action = check : lecture seule
    $state = ticket_state($ticket);
    $msg = [
        'valid'     => 'Ticket authentique et non utilisé. Cliquez sur « Autoriser l\'entrée » pour le valider.',
        'used'      => 'Ce ticket a déjà été scanné : entrée refusée.',
        'cancelled' => 'Ce ticket a été annulé : entrée refusée.',
        'pending'   => 'Le paiement n\'a pas été confirmé : entrée refusée.',
        'invalid'   => 'Aucun ticket ne correspond à ce code.',
    ][$state];
    json_response(['ok' => true, 'state' => $state, 'label' => TICKET_STATE_LABELS[$state], 'message' => $msg, 'ticket' => $describe($ticket)]);
}

/* ------------------------------------------------------------------ POST classique : validation manuelle depuis la liste */
if (is_post()) {
    csrf_require();
    if (post('action') === 'checkin') {
        $tk = ticket_by_token(post('token'));
        if ($tk !== null && check_in_ticket($tk['token'], (int) $admin['id'])) {
            log_activity('Entrée validée', $tk['ticket_number'] . ' — ' . $tk['holder_name']);
            flash('success', 'Entrée validée pour ' . $tk['holder_name'] . ' (' . $tk['ticket_number'] . ').');
        } else {
            flash('error', 'Ce ticket ne peut pas être validé (déjà utilisé, annulé ou non confirmé).');
        }
    }
    admin_back('tickets.php', [
        'q' => mb_substr(post('q'), 0, 100), 'event' => (int) post('event', '0'),
        'status' => isset(TICKET_STATUSES[post('status')]) ? post('status') : '',
    ]);
}

/* ------------------------------------------------------------------ Liste des tickets */
$q       = mb_substr(get('q'), 0, 100);
$eventId = (int) get('event', '0');
$status  = get('status');
if (!isset(TICKET_STATUSES[$status])) {
    $status = '';
}
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(t.ticket_number LIKE ? OR t.holder_name LIKE ? OR r.reservation_code LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($eventId > 0) { $where[] = 't.event_id = ?'; $params[] = $eventId; }
if ($status !== '') { $where[] = 't.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);
$base = "FROM tickets t JOIN reservations r ON r.id = t.reservation_id JOIN events e ON e.id = t.event_id WHERE $whereSql";

$cnt = $pdo->prepare("SELECT COUNT(*) $base");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), 25);
$st = $pdo->prepare(
    "SELECT t.*, r.reservation_code, r.status AS reservation_status, e.title AS event_title, e.status AS event_status $base
     ORDER BY t.id DESC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']
);
$st->execute($params);
$rows = $st->fetchAll();
$eventsList = $pdo->query('SELECT id, title FROM events ORDER BY start_date DESC')->fetchAll();

admin_header('Tickets & vérification', 'tickets', $admin);
?>
<div class="scan-layout">
    <section class="panel" aria-labelledby="scan-h" style="margin:0">
        <h2 id="scan-h">Scanner un ticket</h2>
        <div class="scanner" id="scanner" data-endpoint="<?= e(url('admin/tickets.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
            <video muted playsinline aria-label="Aperçu de la caméra"></video>
            <div class="scanner__frame" aria-hidden="true"></div>
            <p class="scanner__msg">Cliquez sur « Activer la caméra » pour scanner un QR code.</p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="scan-start"><i data-lucide="camera" aria-hidden="true"></i> Activer la caméra</button>
            <button type="button" class="btn btn-ghost" id="scan-stop" hidden>Arrêter la caméra</button>
        </div>
        <form id="manual-form" class="toolbar" style="margin:18px 0 0" autocomplete="off">
            <div class="field field--grow"><label for="manual-input">Saisie manuelle (numéro CM-XXXXXXXX ou code du QR)</label>
                <input type="text" id="manual-input" maxlength="600" placeholder="CM-ABCD2345"></div>
            <button class="btn btn-ghost" type="submit">Vérifier</button>
        </form>
        <p class="field-hint">La caméra fonctionne sur <code>localhost</code> ou en HTTPS. Sur téléphone, ouvrez l'administration en HTTPS.</p>
    </section>

    <section class="panel" aria-labelledby="res-h" style="margin:0" aria-live="polite">
        <h2 id="res-h">Résultat</h2>
        <div class="scan-result" id="scan-result" tabindex="-1" data-state="idle">
            <p class="muted" style="margin:0">Scannez ou saisissez un ticket : le résultat s'affichera ici (valide, déjà utilisé, annulé, non confirmé ou invalide).</p>
        </div>
    </section>
</div>

<h2 style="font-size:1.3rem">Tous les tickets</h2>
<form class="toolbar" method="get" action="<?= e(url('admin/tickets.php')) ?>" role="search">
    <div class="field field--grow"><label for="q">Rechercher</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="N° de ticket, nom, code de réservation"></div>
    <div class="field"><label for="event">Événement</label>
        <select id="event" name="event"><option value="0">Tous</option>
            <?php foreach ($eventsList as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= $eventId === (int) $ev['id'] ? ' selected' : '' ?>><?= e(truncate($ev['title'], 40)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="field"><label for="status">Statut</label>
        <select id="status" name="status"><option value="">Tous</option>
            <?php foreach (TICKET_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn btn-ghost" type="submit">Filtrer</button>
</form>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Ticket</th><th>Participant</th><th>Événement</th><th>Statut</th><th>Utilisé le</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t):
        $state = ticket_state($t + ['reservation_status' => $t['reservation_status'], 'event_status' => $t['event_status']]); ?>
        <tr>
            <td data-label="Ticket"><strong><?= e($t['ticket_number']) ?></strong><small><?= e($t['reservation_code']) ?></small></td>
            <td data-label="Participant"><?= e($t['holder_name']) ?></td>
            <td data-label="Événement"><?= e(truncate($t['event_title'], 40)) ?></td>
            <td data-label="Statut"><span class="badge badge--<?= e($state) ?>"><?= e($state === 'valid' ? 'Valide' : (TICKET_STATUSES[$state] ?? $state)) ?></span></td>
            <td data-label="Utilisé le"><?= $t['used_at'] ? e(fr_datetime((string) $t['used_at'])) : '—' ?></td>
            <td class="cell-actions"><div class="actions">
                <?php if ($state === 'valid'): ?>
                    <form method="post" action="<?= e(url('admin/tickets.php')) ?>" data-confirm="Valider l'entrée de <?= e($t['holder_name']) ?> ?"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="checkin"><input type="hidden" name="token" value="<?= e($t['token']) ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="event" value="<?= $eventId ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
                        <button class="btn btn-primary btn-xs" type="submit">Valider l'entrée</button></form>
                <?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:40px">Aucun ticket.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= render_pagination($pg, ['q' => $q, 'event' => $eventId ?: '', 'status' => $status]) ?>
<?php admin_footer(['https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js']); ?>
