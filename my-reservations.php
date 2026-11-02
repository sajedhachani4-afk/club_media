<?php
/**
 * Mes réservations : retrouver un ticket avec son e-mail ET son code de réservation,
 * ou consulter les réservations faites depuis ce navigateur.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$errors = [];
if (is_post()) {
    csrf_require();
    $email = post('email');
    $code  = strtoupper(preg_replace('/\s+/', '', post('code')) ?? '');

    if (!valid_email($email))                                  { $errors['email'] = 'Adresse e-mail invalide.'; }
    if (!preg_match('/^R-[A-Z2-9]{8}$/', $code))               { $errors['code']  = 'Code invalide. Format attendu : R-XXXXXXXX.'; }

    if (!$errors && rate_limited('lookup', 10, 900)) {
        $errors['form'] = 'Trop de tentatives. Réessayez dans quelques minutes.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT access_token FROM reservations WHERE reservation_code = ? AND email = ?');
        $st->execute([$code, $email]);
        $token = $st->fetchColumn();
        if ($token) {
            $_SESSION['my_reservations'] = array_slice(array_values(array_unique(array_merge([(string) $token], $_SESSION['my_reservations'] ?? []))), 0, 10);
            redirect('ticket.php?t=' . $token);
        }
        rate_limit_hit('lookup');
        $errors['form'] = 'Aucune réservation ne correspond à cet e-mail et à ce code.';
    }
}

$mine = [];
$tokens = array_filter((array) ($_SESSION['my_reservations'] ?? []), static fn($t) => is_string($t) && ticket_token_valid($t));
if ($tokens) {
    $in = implode(',', array_fill(0, count($tokens), '?'));
    $st = db()->prepare(
        "SELECT r.*, e.title AS event_title, e.start_date, e.start_time, e.location
         FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.access_token IN ($in) ORDER BY r.created_at DESC"
    );
    $st->execute(array_values($tokens));
    $mine = $st->fetchAll();
}

$pageTitle = 'Mes réservations';
$activeNav = '';
$noindex   = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Mes réservations</span></nav>
        <h1>Mes <span class="text-grad">réservations</span></h1>
        <p>Retrouvez vos tickets avec votre e-mail et votre code de réservation.</p>
    </div>
</section>

<section class="section" style="padding-top:48px">
    <div class="container contact-layout">
        <div>
            <?php if ($mine): ?>
                <h2 style="font-size:1.4rem">Réservations de ce navigateur</h2>
                <div class="res-list">
                    <?php foreach ($mine as $r): ?>
                        <article class="card res-item">
                            <div>
                                <h3><?= e($r['event_title']) ?></h3>
                                <p class="muted" style="margin:0 0 8px"><?= e(fr_date($r['start_date'], true)) ?> · <?= e(fr_time($r['start_time'])) ?> · <?= e($r['location']) ?></p>
                                <span class="badge badge--<?= e($r['status']) ?>"><?= e(RESERVATION_STATUSES[$r['status']] ?? $r['status']) ?></span>
                                <span class="muted" style="margin-left:8px"><?= (int) $r['quantity'] ?> ticket(s) · <?= e($r['reservation_code']) ?></span>
                            </div>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('ticket.php?t=' . $r['access_token'])) ?>">Voir le ticket</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty"><i data-lucide="ticket" aria-hidden="true"></i><p>Aucune réservation enregistrée dans ce navigateur.<br>Utilisez le formulaire pour retrouver un ticket.</p></div>
            <?php endif; ?>
        </div>

        <aside class="card contact-card">
            <h2 style="font-size:1.4rem">Retrouver un ticket</h2>
            <?php if (!empty($errors['form'])): ?><div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
            <form class="form" method="post" action="<?= e(url('my-reservations.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">E-mail utilisé pour la réservation</label>
                    <input type="email" id="email" name="email" value="<?= val('email') ?>" required autocomplete="email"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="field">
                    <label for="code">Code de réservation</label>
                    <input type="text" id="code" name="code" value="<?= val('code') ?>" required placeholder="R-XXXXXXXX" maxlength="12" autocapitalize="characters" autocomplete="off"<?= isset($errors['code']) ? ' aria-invalid="true" aria-describedby="err-code"' : '' ?>>
                    <?= field_error($errors, 'code') ?>
                </div>
                <button class="btn btn-primary" type="submit">Afficher mon ticket</button>
            </form>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
