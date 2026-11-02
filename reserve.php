<?php
/** Réservation de tickets (gratuits ou payants) */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

sync_event_statuses();
$eventId = (int) (is_post() ? post('event_id', '0') : get('event', '0'));
$ev      = $eventId > 0 ? fetch_event($eventId) : null;

if ($ev === null) {
    http_response_code(404);
    $pageTitle = 'Réservation';
    $activeNav = 'events';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="container"><div class="empty"><i data-lucide="ticket-x" aria-hidden="true"></i><h1 style="font-size:1.6rem">Événement introuvable</h1>'
        . '<p>Choisissez un événement pour réserver.</p><a class="btn btn-primary" href="' . e(url('events.php')) . '">Voir les événements</a></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isPaid   = $ev['ticket_type'] === 'paid' && (float) $ev['ticket_price'] > 0;
$bookable = event_is_bookable($ev);
$maxQty   = max(1, min(MAX_TICKETS_PER_RESERVATION, (int) $ev['available_seats']));
$errors   = [];

if (is_post()) {
    csrf_require();

    if (post('website') !== '') {            // piège anti-robots
        redirect('events.php');
    }
    $name  = mb_substr(post('full_name'), 0, 120);
    $email = post('email');
    $phone = post('phone');
    $qty   = (int) post('quantity', '1');

    if (mb_strlen($name) < 2)           { $errors['full_name'] = 'Veuillez indiquer votre nom complet.'; }
    if (!valid_email($email))           { $errors['email']     = 'Adresse e-mail invalide.'; }
    if (!valid_phone($phone))           { $errors['phone']     = 'Numéro de téléphone invalide (8 chiffres minimum).'; }
    if ($qty < 1 || $qty > MAX_TICKETS_PER_RESERVATION) {
        $errors['quantity'] = 'Quantité invalide (1 à ' . MAX_TICKETS_PER_RESERVATION . ' tickets).';
    }
    if (!$errors && !$bookable) {
        $errors['form'] = 'Les réservations ne sont plus possibles pour cet événement.';
    }
    if (!$errors && rate_limited('reserve', 10, 3600)) {
        $errors['form'] = 'Trop de réservations en peu de temps. Réessayez plus tard.';
    }
    if (!$errors) {
        $result = create_reservation($eventId, $name, $email, $phone, $qty);
        if ($result['ok']) {
            rate_limit_hit('reserve');
            $_SESSION['my_reservations'] = array_slice(array_merge([$result['token']], $_SESSION['my_reservations'] ?? []), 0, 10);
            flash('success', $isPaid
                ? 'Réservation enregistrée. Elle sera validée après confirmation de votre paiement.'
                : 'Réservation confirmée ! Voici votre ticket.');
            redirect('ticket.php?t=' . $result['token']);
        }
        $errors['form'] = (string) $result['error'];
        // les places ont pu changer : on relit l'événement
        $ev       = fetch_event($eventId) ?? $ev;
        $bookable = event_is_bookable($ev);
        $maxQty   = max(1, min(MAX_TICKETS_PER_RESERVATION, (int) $ev['available_seats']));
    }
}

$pageTitle   = 'Réserver : ' . $ev['title'];
$activeNav   = 'events';
$noindex     = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><a href="<?= e(url('events.php')) ?>">Événements</a><span aria-hidden="true">/</span><span>Réservation</span></nav>
        <h1>Réserver ma <span class="text-grad">place</span></h1>
        <p><?= e($ev['title']) ?></p>
    </div>
</section>

<div class="container event-layout">
    <div class="card contact-card">
        <?php if (!$bookable): ?>
            <div class="alert alert--warning" role="alert">
                <?= $ev['status'] === 'cancelled' ? 'Cet événement est annulé.' : ($ev['status'] === 'finished' ? 'Cet événement est terminé.' : 'Cet événement est complet.') ?>
            </div>
            <a class="btn btn-ghost" href="<?= e(url('events.php')) ?>">Voir d'autres événements</a>
        <?php else: ?>
            <h2 style="font-size:1.5rem">Vos informations</h2>
            <?php if (!empty($errors['form'])): ?><div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
            <?php if ($isPaid): ?>
                <div class="alert alert--info">Billet payant : votre réservation sera <strong>en attente de paiement</strong> jusqu'à confirmation par un administrateur. Aucun paiement en ligne n'est effectué sur ce site.</div>
            <?php endif; ?>
            <form class="form" method="post" action="<?= e(url('reserve.php')) ?>" novalidate data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
                <div class="hp-field" aria-hidden="true"><label for="website">Ne pas remplir</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <div class="field">
                    <label for="full_name">Nom complet <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="<?= val('full_name') ?>" required maxlength="120" autocomplete="name"<?= isset($errors['full_name']) ? ' aria-invalid="true" aria-describedby="err-full_name"' : '' ?>>
                    <?= field_error($errors, 'full_name') ?>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="email">E-mail <span class="req" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" value="<?= val('email') ?>" required maxlength="150" autocomplete="email"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                        <?= field_error($errors, 'email') ?>
                    </div>
                    <div class="field">
                        <label for="phone">Téléphone <span class="req" aria-hidden="true">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?= val('phone') ?>" required maxlength="25" autocomplete="tel" inputmode="tel" placeholder="Ex. : 22 123 456"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="err-phone"' : '' ?>>
                        <?= field_error($errors, 'phone') ?>
                    </div>
                </div>
                <div class="field">
                    <label for="quantity">Nombre de tickets <span class="req" aria-hidden="true">*</span></label>
                    <div class="stepper">
                        <button type="button" data-step="-1" aria-label="Un ticket de moins">−</button>
                        <input type="number" id="quantity" name="quantity" min="1" max="<?= $maxQty ?>" value="<?= max(1, min($maxQty, (int) (post('quantity', '1') ?: 1))) ?>" inputmode="numeric"<?= isset($errors['quantity']) ? ' aria-invalid="true" aria-describedby="err-quantity"' : '' ?>>
                        <button type="button" data-step="1" aria-label="Un ticket de plus">+</button>
                    </div>
                    <p class="field-hint">Maximum <?= $maxQty ?> ticket(s) par réservation.</p>
                    <?= field_error($errors, 'quantity') ?>
                </div>
                <button class="btn btn-primary btn-lg" type="submit"><i data-lucide="ticket-check" aria-hidden="true"></i> <?= $isPaid ? 'Envoyer ma demande de réservation' : 'Confirmer ma réservation' ?></button>
            </form>
        <?php endif; ?>
    </div>

    <aside>
        <div class="card side-card summary-box">
            <h2 style="font-size:1.25rem">Récapitulatif</h2>
            <dl>
                <dt>Événement</dt><dd><?= e($ev['title']) ?></dd>
                <dt>Date</dt><dd><?= e(fr_date($ev['start_date'], true)) ?></dd>
                <dt>Heure</dt><dd><?= e(fr_time($ev['start_time'])) ?></dd>
                <dt>Lieu</dt><dd><?= e($ev['location']) ?></dd>
                <dt>Prix unitaire</dt><dd><?= e(event_price_label($ev)) ?></dd>
                <dt>Places restantes</dt><dd><?= (int) $ev['available_seats'] ?></dd>
                <dt>Total</dt><dd class="price" data-total data-unit-price="<?= $isPaid ? e((string) (float) $ev['ticket_price']) : '0' ?>"><?= $isPaid ? e(format_money($ev['ticket_price'])) : 'Gratuit' ?></dd>
            </dl>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
