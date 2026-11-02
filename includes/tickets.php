<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Logique métier des réservations et des tickets.
 *
 * Anti-survente : chaque opération qui modifie les places verrouille d'abord la ligne
 * de l'événement (SELECT ... FOR UPDATE) dans une transaction. L'ordre de verrouillage
 * est toujours « événement puis réservation » pour éviter les blocages croisés.
 */
declare(strict_types=1);

function ticket_token_valid(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{48}$/', $token);
}

/** Extrait un jeton de ticket d'un texte scanné (jeton seul, ou URL le contenant). */
function ticket_token_extract(string $scanned): ?string
{
    $scanned = trim($scanned);
    if (ticket_token_valid($scanned)) {
        return $scanned;
    }
    if (preg_match('/(?<![a-f0-9])([a-f0-9]{48})(?![a-f0-9])/i', $scanned, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

/** Réservation sans doublon de code : réessaie si le code aléatoire existe déjà. */
function insert_unique(PDO $pdo, string $sql, callable $paramsFor, int $tries = 6): int
{
    for ($i = 0; $i < $tries; $i++) {
        try {
            $pdo->prepare($sql)->execute($paramsFor());
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1062 || $i === $tries - 1) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Insertion impossible.');
}

/**
 * Crée une réservation (gratuite : confirmée ; payante : en attente de paiement).
 * @return array{ok:bool,error:?string,token:?string}
 */
function create_reservation(int $eventId, string $name, string $email, string $phone, int $qty): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
        $st->execute([$eventId]);
        $ev = $st->fetch();

        if (!$ev || !(int) $ev['is_published']) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Cet événement n\'est pas disponible.', 'token' => null];
        }
        if (!in_array($ev['status'], ['upcoming', 'ongoing'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Les réservations sont fermées pour cet événement.', 'token' => null];
        }
        $left = (int) $ev['available_seats'];
        if ($left < $qty) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $left <= 0
                ? 'Désolé, cet événement est complet.'
                : 'Il ne reste que ' . $left . ' place(s) disponible(s).', 'token' => null];
        }

        $isPaid = $ev['ticket_type'] === 'paid' && (float) $ev['ticket_price'] > 0;
        $unit   = $isPaid ? (float) $ev['ticket_price'] : 0.0;
        $token  = bin2hex(random_bytes(24));

        $resId = insert_unique(
            $pdo,
            'INSERT INTO reservations (reservation_code, access_token, event_id, full_name, email, phone, quantity, unit_price, total_amount, status, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            static fn() => ['R-' . random_code(8), $token, $eventId, $name, $email, $phone, $qty, $unit, $unit * $qty,
                            $isPaid ? 'pending_payment' : 'confirmed', client_ip()]
        );

        $ticketStatus = $isPaid ? 'pending' : 'valid';
        for ($i = 0; $i < $qty; $i++) {
            insert_unique(
                $pdo,
                'INSERT INTO tickets (reservation_id, event_id, ticket_number, token, holder_name, status) VALUES (?, ?, ?, ?, ?, ?)',
                static fn() => [$resId, $eventId, 'CM-' . random_code(8), bin2hex(random_bytes(24)), $name, $ticketStatus]
            );
        }

        $pdo->prepare('UPDATE events SET available_seats = available_seats - ? WHERE id = ?')->execute([$qty, $eventId]);
        $pdo->commit();
        return ['ok' => true, 'error' => null, 'token' => $token];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * L'administrateur confirme la réception du paiement : les tickets deviennent valides.
 * @return array{ok:bool,message:string}
 */
function confirm_payment(int $reservationId, int $adminId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $pdo->prepare('SELECT r.*, e.status AS event_status FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.id = ? FOR UPDATE');
        $r->execute([$reservationId]);
        $res = $r->fetch();
        if (!$res) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Réservation introuvable.'];
        }
        if ($res['status'] !== 'pending_payment') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Cette réservation n\'est pas en attente de paiement.'];
        }
        if ($res['event_status'] === 'cancelled') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'L\'événement est annulé : annulez plutôt la réservation.'];
        }
        $pdo->prepare("UPDATE reservations SET status = 'confirmed', payment_confirmed_at = NOW(), payment_confirmed_by = ? WHERE id = ?")
            ->execute([$adminId, $reservationId]);
        $pdo->prepare("UPDATE tickets SET status = 'valid' WHERE reservation_id = ? AND status = 'pending'")->execute([$reservationId]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Paiement confirmé : les tickets sont maintenant valides.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Annule une réservation et libère les places des tickets non utilisés.
 * @return array{ok:bool,message:string}
 */
function cancel_reservation(int $reservationId): array
{
    $pdo = db();
    $pre = $pdo->prepare('SELECT event_id FROM reservations WHERE id = ?');
    $pre->execute([$reservationId]);
    $eventId = (int) $pre->fetchColumn();
    if ($eventId === 0) {
        return ['ok' => false, 'message' => 'Réservation introuvable.'];
    }

    $pdo->beginTransaction();
    try {
        $ev = $pdo->prepare('SELECT id, capacity FROM events WHERE id = ? FOR UPDATE');
        $ev->execute([$eventId]);
        $event = $ev->fetch();

        $r = $pdo->prepare('SELECT * FROM reservations WHERE id = ? FOR UPDATE');
        $r->execute([$reservationId]);
        $res = $r->fetch();
        if (!$res || !$event) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Réservation introuvable.'];
        }
        if ($res['status'] === 'cancelled') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Cette réservation est déjà annulée.'];
        }
        $n = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE reservation_id = ? AND status IN ('valid','pending')");
        $n->execute([$reservationId]);
        $release = (int) $n->fetchColumn();

        $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE reservation_id = ? AND status IN ('valid','pending')")->execute([$reservationId]);
        $pdo->prepare("UPDATE reservations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?")->execute([$reservationId]);
        $pdo->prepare('UPDATE events SET available_seats = LEAST(capacity, available_seats + ?) WHERE id = ?')->execute([$release, $eventId]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Réservation annulée (' . $release . ' place(s) libérée(s)).'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Ticket + réservation + événement à partir du jeton secret, ou null. */
function ticket_by_token(string $token): ?array
{
    if (!ticket_token_valid($token)) {
        return null;
    }
    $st = db()->prepare(
        'SELECT t.*, r.status AS reservation_status, r.reservation_code, r.full_name AS buyer_name, r.email AS buyer_email,
                e.title AS event_title, e.start_date, e.start_time, e.end_time, e.location, e.ticket_type, e.status AS event_status
         FROM tickets t
         JOIN reservations r ON r.id = t.reservation_id
         JOIN events e ON e.id = t.event_id
         WHERE t.token = ?'
    );
    $st->execute([$token]);
    $row = $st->fetch();
    return $row ?: null;
}

/** État affiché au contrôle : valid | used | cancelled | pending | invalid */
function ticket_state(?array $t): string
{
    if ($t === null) {
        return 'invalid';
    }
    if ($t['status'] === 'cancelled' || $t['reservation_status'] === 'cancelled' || $t['event_status'] === 'cancelled') {
        return 'cancelled';
    }
    if ($t['status'] === 'pending' || $t['reservation_status'] === 'pending_payment') {
        return 'pending';
    }
    return $t['status'] === 'used' ? 'used' : 'valid';
}

const TICKET_STATE_LABELS = [
    'valid'     => 'Ticket valide',
    'used'      => 'Déjà utilisé',
    'cancelled' => 'Ticket annulé',
    'pending'   => 'Non confirmé (paiement en attente)',
    'invalid'   => 'Ticket invalide',
];

/**
 * Valide l'entrée : passe le ticket à « utilisé » de façon atomique (une seule requête UPDATE),
 * ce qui empêche qu'un même ticket soit accepté deux fois, même avec deux scanners simultanés.
 */
function check_in_ticket(string $token, int $adminId): bool
{
    if (!ticket_token_valid($token)) {
        return false;
    }
    $st = db()->prepare(
        "UPDATE tickets t
         JOIN reservations r ON r.id = t.reservation_id
         JOIN events e ON e.id = t.event_id
         SET t.status = 'used', t.used_at = NOW(), t.used_by = ?
         WHERE t.token = ? AND t.status = 'valid' AND r.status = 'confirmed' AND e.status <> 'cancelled'"
    );
    $st->execute([$adminId, $token]);
    return $st->rowCount() === 1;
}

/** Recalcule les places disponibles d'un événement (après modification de la capacité). */
function seats_in_use(int $eventId): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status IN ('valid','pending','used')");
    $st->execute([$eventId]);
    return (int) $st->fetchColumn();
}
