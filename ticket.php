<?php
/**
 * Ticket(s) d'une réservation. L'accès se fait par un jeton secret imprévisible (48 caractères),
 * jamais par un identifiant séquentiel.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$token = get('t');
$res   = null;
if (ticket_token_valid($token)) {
    $st = db()->prepare(
        'SELECT r.*, e.title AS event_title, e.start_date, e.end_date, e.start_time, e.end_time, e.location, e.ticket_type, e.status AS event_status
         FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.access_token = ?'
    );
    $st->execute([$token]);
    $res = $st->fetch() ?: null;
}

$noindex   = true;
$activeNav = 'events';
$pageTitle = 'Mon ticket';

if ($res === null) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty"><i data-lucide="ticket-x" aria-hidden="true"></i><h1 style="font-size:1.6rem">Ticket introuvable</h1>'
        . '<p>Le lien est incorrect ou incomplet. Retrouvez votre réservation avec votre e-mail et votre code.</p>'
        . '<a class="btn btn-primary" href="' . e(url('my-reservations.php')) . '">Mes réservations</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tk = db()->prepare('SELECT * FROM tickets WHERE reservation_id = ? ORDER BY id');
$tk->execute([(int) $res['id']]);
$tickets = $tk->fetchAll();

$isPaid    = $res['ticket_type'] === 'paid' && (float) $res['unit_price'] > 0;
$pending   = $res['status'] === 'pending_payment';
$cancelled = $res['status'] === 'cancelled';
$logo      = setting('logo') !== '' ? upload_url('site', setting('logo')) : asset('images/logo-mark.svg');

$needQr = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><a href="<?= e(url('my-reservations.php')) ?>">Mes réservations</a><span aria-hidden="true">/</span><span><?= e($res['reservation_code']) ?></span></nav>
        <h1><?= count($tickets) > 1 ? 'Mes <span class="text-grad">tickets</span>' : 'Mon <span class="text-grad">ticket</span>' ?></h1>
        <p>Réservation <strong><?= e($res['reservation_code']) ?></strong> · <?= e($res['event_title']) ?></p>
    </div>
</section>

<section class="section" style="padding-top:48px">
    <div class="container ticket-wrap">
        <?php if ($pending): ?>
            <div class="alert alert--warning" role="status">
                <strong>En attente de paiement.</strong> Montant à régler : <strong><?= e(format_money($res['total_amount'])) ?></strong>.
                Vos tickets ne seront valides qu'après confirmation du paiement par un administrateur.
                <br><?= e(setting('payment_instructions')) ?>
                <br>Code à indiquer : <strong><?= e($res['reservation_code']) ?></strong>
            </div>
        <?php elseif ($cancelled): ?>
            <div class="alert alert--error" role="status"><strong>Réservation annulée.</strong> Ces tickets ne sont plus valables.</div>
        <?php elseif ($res['event_status'] === 'cancelled'): ?>
            <div class="alert alert--error" role="status"><strong>Événement annulé.</strong> Ces tickets ne sont plus valables.</div>
        <?php else: ?>
            <div class="alert alert--success" role="status">
                <?= $isPaid ? 'Paiement confirmé. ' : '' ?>Présentez le QR code à l'entrée (sur votre téléphone ou imprimé). Conservez cette page ou le lien dans votre navigateur.
            </div>
        <?php endif; ?>

        <div class="card summary-box">
            <dl>
                <dt>Événement</dt><dd><?= e($res['event_title']) ?></dd>
                <dt>Au nom de</dt><dd><?= e($res['full_name']) ?></dd>
                <dt>Quantité</dt><dd><?= (int) $res['quantity'] ?> ticket(s)</dd>
                <dt>Total</dt><dd><?= $isPaid ? e(format_money($res['total_amount'])) : 'Gratuit' ?></dd>
                <dt>Statut</dt><dd><span class="badge badge--<?= e($res['status']) ?>"><?= e(RESERVATION_STATUSES[$res['status']] ?? $res['status']) ?></span></dd>
            </dl>
        </div>

        <?php foreach ($tickets as $i => $t):
            $row = $t + [
                'reservation_status' => $res['status'], 'event_status' => $res['event_status'],
            ];
            $state  = ticket_state($row);
            $showQr = in_array($state, ['valid', 'used'], true);
            $qrId   = 'qr-' . $i;
        ?>
            <article class="ticket<?= $showQr && $state === 'valid' ? '' : ' ticket--inactive' ?>" aria-label="Ticket <?= e($t['ticket_number']) ?>">
                <div class="ticket__main">
                    <div class="ticket__brand">
                        <img src="<?= e($logo) ?>" alt="" width="38" height="38">
                        <div><strong>CLUB MEDIA</strong><small>ISET GAFSA</small></div>
                    </div>
                    <h2 class="ticket__event"><?= e($res['event_title']) ?></h2>
                    <dl class="ticket__grid">
                        <div><dt>Participant</dt><dd><?= e($t['holder_name']) ?></dd></div>
                        <div><dt>Ticket n°</dt><dd><?= e($t['ticket_number']) ?></dd></div>
                        <div><dt>Date</dt><dd><?= e(fr_date($res['start_date'])) ?></dd></div>
                        <div><dt>Heure</dt><dd><?= e(fr_time($res['start_time'])) ?> – <?= e(fr_time($res['end_time'])) ?></dd></div>
                        <div><dt>Lieu</dt><dd><?= e($res['location']) ?></dd></div>
                        <div><dt>Type</dt><dd><?= $isPaid ? 'Payant — ' . e(format_money($res['unit_price'])) : 'Gratuit' ?></dd></div>
                    </dl>
                </div>
                <div class="ticket__qr">
                    <?php if ($showQr): ?>
                        <div class="qr-box" id="<?= e($qrId) ?>" data-qr="<?= e($t['token']) ?>" data-number="<?= e($t['ticket_number']) ?>"></div>
                    <?php else: ?>
                        <div class="qr-box" style="color:#09090F;font-size:.78rem;text-align:center;padding:14px">QR code activé<br>après confirmation</div>
                    <?php endif; ?>
                    <span class="ticket__num"><?= e($t['ticket_number']) ?></span>
                    <span class="ticket__state badge badge--<?= e($state === 'invalid' ? 'cancelled' : $state) ?>"><?= e($state === 'valid' ? 'Valide' : (TICKET_STATUSES[$state] ?? $state)) ?></span>
                    <?php if ($showQr): ?>
                        <button type="button" class="btn btn-ghost btn-sm no-print" data-qr-download="<?= e($qrId) ?>" data-number="<?= e($t['ticket_number']) ?>">Télécharger le QR</button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <div class="ticket-actions no-print">
            <button type="button" class="btn btn-primary" data-print><i data-lucide="printer" aria-hidden="true"></i> Imprimer</button>
            <a class="btn btn-ghost" href="<?= e(url('events.php')) ?>">Autres événements</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
