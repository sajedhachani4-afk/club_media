<?php
/** Administration des événements : liste, création, modification, suppression, publication, catégories */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
sync_event_statuses();
$pdo = db();

function event_status_for(string $sd, string $st, string $ed, string $et, bool $cancelled): string
{
    if ($cancelled) {
        return 'cancelled';
    }
    $now = time();
    $s = strtotime("$sd $st");
    $e = strtotime("$ed $et");
    return $now > $e ? 'finished' : ($now >= $s ? 'ongoing' : 'upcoming');
}

/* ------------------------------------------------------------------ Actions POST */
if (post_too_large()) {
    flash('error', 'Envoi trop volumineux : l\'image dépasse la limite du serveur (' . ini_get('post_max_size') . ').');
    admin_back('events.php');
}
if (is_post()) {
    csrf_require();
    $action = post('action');
    $id     = (int) post('id', '0');

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE events SET published_at = CASE WHEN is_published = 0 THEN COALESCE(published_at, NOW()) ELSE published_at END, is_published = 1 - is_published WHERE id = ?')->execute([$id]);
        log_activity('Publication modifiée', 'Événement #' . $id);
        flash('success', 'Visibilité de l\'événement mise à jour.');
        admin_back('events.php', ['q' => post('q'), 'status' => post('fstatus')]);
    }

    if ($action === 'delete' && $id > 0) {
        $cv = $pdo->prepare('SELECT title, cover_image FROM events WHERE id = ?');
        $cv->execute([$id]);
        if ($row = $cv->fetch()) {
            $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            delete_upload('events', $row['cover_image']);
            log_activity('Événement supprimé', $row['title']);
            flash('success', 'Événement supprimé (ainsi que ses réservations et tickets).');
        }
        admin_back('events.php');
    }

    if ($action === 'add_category') {
        $name = mb_substr(post('cat_name'), 0, 80);
        if (mb_strlen($name) < 2) {
            flash('error', 'Nom de catégorie trop court.');
        } else {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name))), '-');
            $slug = $slug !== '' ? $slug : 'categorie-' . substr(bin2hex(random_bytes(3)), 0, 6);
            try {
                $pdo->prepare('INSERT INTO event_categories (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
                log_activity('Catégorie ajoutée', $name);
                flash('success', 'Catégorie ajoutée.');
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
                flash('error', 'Cette catégorie existe déjà.');
            }
        }
        admin_back('events.php');
    }

    if ($action === 'delete_category' && $id > 0) {
        $pdo->prepare('DELETE FROM event_categories WHERE id = ?')->execute([$id]);
        log_activity('Catégorie supprimée', '#' . $id);
        flash('success', 'Catégorie supprimée (les événements concernés n\'ont plus de catégorie).');
        admin_back('events.php');
    }

    if ($action === 'save') {
        $editing = $id > 0;
        $old = null;
        if ($editing) {
            $o = $pdo->prepare('SELECT * FROM events WHERE id = ?');
            $o->execute([$id]);
            $old = $o->fetch();
            if (!$old) {
                flash('error', 'Événement introuvable.');
                admin_back('events.php');
            }
        }

        $title = mb_substr(post('title'), 0, 180);
        $desc  = post('description');
        $cat   = int_or_null(post('category_id'));
        $sd = post('start_date'); $st = post('start_time'); $ed = post('end_date'); $et = post('end_time');
        $loc   = mb_substr(post('location'), 0, 180);
        $org   = mb_substr(post('organizer'), 0, 120) ?: 'Club Media ISET Gafsa';
        $type  = post('ticket_type') === 'paid' ? 'paid' : 'free';
        $price = (float) str_replace(',', '.', post('ticket_price', '0'));
        $cap   = (int) post('capacity', '0');
        $pub   = isset($_POST['is_published']) ? 1 : 0;
        $canc  = isset($_POST['is_cancelled']);

        $errors = [];
        if (mb_strlen($title) < 3)               { $errors['title'] = 'Titre trop court (3 caractères minimum).'; }
        if (mb_strlen($desc) < 10)               { $errors['description'] = 'Description trop courte (10 caractères minimum).'; }
        if (mb_strlen($desc) > 8000)             { $errors['description'] = 'Description trop longue (8000 caractères maximum).'; }
        if (!valid_date($sd))                    { $errors['start_date'] = 'Date de début invalide.'; }
        if (!valid_time($st))                    { $errors['start_time'] = 'Heure de début invalide.'; }
        if (!valid_date($ed))                    { $errors['end_date'] = 'Date de fin invalide.'; }
        if (!valid_time($et))                    { $errors['end_time'] = 'Heure de fin invalide.'; }
        if (!$errors && strtotime("$ed $et") <= strtotime("$sd $st")) { $errors['end_date'] = 'La fin doit être après le début.'; }
        if ($loc === '')                         { $errors['location'] = 'Le lieu est obligatoire.'; }
        if ($price < 0 || $price > 100000)       { $errors['ticket_price'] = 'Prix invalide.'; }
        if ($type === 'paid' && $price <= 0)     { $errors['ticket_price'] = 'Un billet payant doit avoir un prix supérieur à 0.'; }
        if ($type === 'free')                    { $price = 0.0; }
        if ($cap < 1 || $cap > 100000)           { $errors['capacity'] = 'Capacité invalide (1 à 100 000).'; }
        if ($cat !== null) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM event_categories WHERE id = ?');
            $chk->execute([$cat]);
            if (!(int) $chk->fetchColumn()) { $cat = null; }
        }

        $used = $editing ? seats_in_use($id) : 0;
        if (!$errors && $cap < $used) {
            $errors['capacity'] = 'La capacité ne peut pas être inférieure aux places déjà réservées (' . $used . ').';
        }

        $newCover = null;
        if (!$errors && has_upload($_FILES['cover'] ?? null)) {
            try {
                $newCover = save_image($_FILES['cover'], 'events', 1600)['filename'];
            } catch (RuntimeException $ex) {
                $errors['cover'] = $ex->getMessage();
            }
        }

        if ($errors) {
            $_SESSION['event_form_errors'] = $errors;
            $_SESSION['event_form_old']    = $_POST;
            redirect('admin/events.php?' . ($editing ? 'edit=' . $id : 'action=new'));
        }

        $status = event_status_for($sd, $st, $ed, $et, $canc);
        $cover  = $old['cover_image'] ?? null;
        if ($newCover !== null) {
            delete_upload('events', $cover);
            $cover = $newCover;
        } elseif ($editing && isset($_POST['remove_cover'])) {
            delete_upload('events', $cover);
            $cover = null;
        }

        if ($editing) {
            $pdo->prepare(
                'UPDATE events SET category_id=?, title=?, description=?, cover_image=?, start_date=?, start_time=?, end_date=?, end_time=?,
                 location=?, organizer=?, ticket_type=?, ticket_price=?, capacity=?, available_seats=?, status=?, is_published=?,
                 published_at = CASE WHEN ? = 1 THEN COALESCE(published_at, NOW()) ELSE published_at END WHERE id=?'
            )->execute([$cat, $title, $desc, $cover, $sd, $st, $ed, $et, $loc, $org, $type, $price, $cap, $cap - $used, $status, $pub, $pub, $id]);
            log_activity('Événement modifié', $title);
            flash('success', 'Événement mis à jour.');
        } else {
            $pdo->prepare(
                'INSERT INTO events (category_id, title, description, cover_image, start_date, start_time, end_date, end_time, location, organizer,
                 ticket_type, ticket_price, capacity, available_seats, status, is_published, published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, CASE WHEN ? = 1 THEN NOW() ELSE NULL END)'
            )->execute([$cat, $title, $desc, $cover, $sd, $st, $ed, $et, $loc, $org, $type, $price, $cap, $cap, $status, $pub, $pub]);
            log_activity('Événement créé', $title);
            flash('success', 'Événement créé.');
        }
        admin_back('events.php');
    }
}

/* ------------------------------------------------------------------ Affichage : formulaire */
$formErrors = $_SESSION['event_form_errors'] ?? [];
$formOld    = $_SESSION['event_form_old'] ?? null;
unset($_SESSION['event_form_errors'], $_SESSION['event_form_old']);

$editId   = (int) get('edit', '0');
$isForm   = $editId > 0 || get('action') === 'new';
$cats     = $pdo->query('SELECT id, name FROM event_categories ORDER BY name')->fetchAll();

if ($isForm) {
    $ev = null;
    if ($editId > 0) {
        $ev = fetch_event($editId, false);
        if ($ev === null) {
            flash('error', 'Événement introuvable.');
            admin_back('events.php');
        }
    }
    // Valeurs affichées : saisie précédente en cas d'erreur, sinon l'événement, sinon valeurs par défaut
    $v = static function (string $k, $default = '') use ($formOld, $ev) {
        if (is_array($formOld) && isset($formOld[$k]) && is_string($formOld[$k])) {
            return $formOld[$k];
        }
        return $ev[$k] ?? $default;
    };
    $isPub = is_array($formOld) ? isset($formOld['is_published']) : (!$ev || (int) $ev['is_published'] === 1);
    $isCan = is_array($formOld) ? isset($formOld['is_cancelled']) : ($ev && $ev['status'] === 'cancelled');
    $tomorrow = date('Y-m-d', strtotime('+7 day'));

    admin_header($ev ? 'Modifier l\'événement' : 'Nouvel événement', 'events', $admin);
    ?>
    <form class="panel form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/events.php')) ?>" novalidate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $ev ? (int) $ev['id'] : 0 ?>">
        <?php if ($formErrors): ?><div class="alert alert--error" role="alert">Le formulaire contient des erreurs. Corrigez les champs signalés.</div><?php endif; ?>

        <div class="form-grid">
            <div class="field span-2">
                <label for="title">Titre <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="title" name="title" value="<?= e((string) $v('title')) ?>" required maxlength="180"<?= isset($formErrors['title']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'title') ?>
            </div>
            <div class="field span-2">
                <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" required maxlength="8000" rows="7"<?= isset($formErrors['description']) ? ' aria-invalid="true"' : '' ?>><?= e((string) $v('description')) ?></textarea>
                <p class="field-hint">Séparez les paragraphes par une ligne vide.</p>
                <?= field_error($formErrors, 'description') ?>
            </div>
            <div class="field">
                <label for="category_id">Catégorie</label>
                <select id="category_id" name="category_id">
                    <option value="">— Aucune —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= (string) $v('category_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="location">Lieu <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="location" name="location" value="<?= e((string) $v('location')) ?>" required maxlength="180"<?= isset($formErrors['location']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'location') ?>
            </div>
            <div class="field">
                <label for="start_date">Date de début <span class="req" aria-hidden="true">*</span></label>
                <input type="date" id="start_date" name="start_date" value="<?= e((string) $v('start_date', $tomorrow)) ?>" required<?= isset($formErrors['start_date']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'start_date') ?>
            </div>
            <div class="field">
                <label for="start_time">Heure de début <span class="req" aria-hidden="true">*</span></label>
                <input type="time" id="start_time" name="start_time" value="<?= e(substr((string) $v('start_time', '15:00'), 0, 5)) ?>" required<?= isset($formErrors['start_time']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'start_time') ?>
            </div>
            <div class="field">
                <label for="end_date">Date de fin <span class="req" aria-hidden="true">*</span></label>
                <input type="date" id="end_date" name="end_date" value="<?= e((string) $v('end_date', $tomorrow)) ?>" required<?= isset($formErrors['end_date']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'end_date') ?>
            </div>
            <div class="field">
                <label for="end_time">Heure de fin <span class="req" aria-hidden="true">*</span></label>
                <input type="time" id="end_time" name="end_time" value="<?= e(substr((string) $v('end_time', '18:00'), 0, 5)) ?>" required<?= isset($formErrors['end_time']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'end_time') ?>
            </div>
            <div class="field">
                <label for="ticket_type">Type de ticket</label>
                <select id="ticket_type" name="ticket_type">
                    <option value="free"<?= $v('ticket_type', 'free') === 'free' ? ' selected' : '' ?>>Gratuit</option>
                    <option value="paid"<?= $v('ticket_type', 'free') === 'paid' ? ' selected' : '' ?>>Payant (paiement confirmé manuellement)</option>
                </select>
            </div>
            <div class="field">
                <label for="ticket_price">Prix du ticket (TND)</label>
                <input type="number" id="ticket_price" name="ticket_price" value="<?= e(rtrim(rtrim(number_format((float) $v('ticket_price', '0'), 3, '.', ''), '0'), '.')) ?>" min="0" step="0.001" inputmode="decimal"<?= isset($formErrors['ticket_price']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'ticket_price') ?>
            </div>
            <div class="field">
                <label for="capacity">Capacité maximale <span class="req" aria-hidden="true">*</span></label>
                <input type="number" id="capacity" name="capacity" value="<?= e((string) $v('capacity', '50')) ?>" min="1" required inputmode="numeric"<?= isset($formErrors['capacity']) ? ' aria-invalid="true"' : '' ?>>
                <?php if ($ev): ?><p class="field-hint"><?= (int) $ev['capacity'] - (int) $ev['available_seats'] ?> place(s) déjà réservée(s).</p><?php endif; ?>
                <?= field_error($formErrors, 'capacity') ?>
            </div>
            <div class="field">
                <label for="organizer">Organisateur</label>
                <input type="text" id="organizer" name="organizer" value="<?= e((string) $v('organizer', 'Club Media ISET Gafsa')) ?>" maxlength="120">
            </div>
            <div class="field span-2">
                <label for="cover">Image de couverture (JPG, PNG ou WebP, 8 Mo max)</label>
                <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp" data-preview="cover-preview"<?= isset($formErrors['cover']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'cover') ?>
                <div class="preview" id="cover-preview"></div>
                <?php if ($ev && !empty($ev['cover_image'])): ?>
                    <img class="current-img" src="<?= e(event_cover_url($ev)) ?>" alt="Couverture actuelle">
                    <label class="check"><input type="checkbox" name="remove_cover" value="1"> Supprimer l'image actuelle</label>
                <?php endif; ?>
            </div>
            <div class="field span-2">
                <label class="check"><input type="checkbox" name="is_published" value="1"<?= $isPub ? ' checked' : '' ?>> Publié (visible sur le site)</label>
                <label class="check"><input type="checkbox" name="is_cancelled" value="1"<?= $isCan ? ' checked' : '' ?>> Événement annulé</label>
                <p class="field-hint">Le statut « À venir / En cours / Terminé » est calculé automatiquement d'après les dates.</p>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/events.php')) ?>">Annuler</a>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

/* ------------------------------------------------------------------ Affichage : liste */
$q       = mb_substr(get('q'), 0, 100);
$fstatus = get('status');
$where   = ['1=1'];
$params  = [];
if ($q !== '') {
    $where[] = 'e.title LIKE ?';
    $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}
if (isset(EVENT_STATUSES[$fstatus])) {
    $where[] = 'e.status = ?';
    $params[] = $fstatus;
}
$whereSql = implode(' AND ', $where);
$cnt = $pdo->prepare("SELECT COUNT(*) FROM events e WHERE $whereSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), 15);
$st = $pdo->prepare(
    "SELECT e.*, c.name AS category_name,
            (SELECT COUNT(*) FROM reservations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS res_count
     FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
     WHERE $whereSql ORDER BY e.start_date DESC, e.start_time DESC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']
);
$st->execute($params);
$events = $st->fetchAll();

$catCounts = $pdo->query('SELECT c.id, c.name, COUNT(e.id) AS n FROM event_categories c LEFT JOIN events e ON e.category_id = c.id GROUP BY c.id, c.name ORDER BY c.name')->fetchAll();

admin_header('Événements', 'events', $admin);
?>
<form class="toolbar" method="get" action="<?= e(url('admin/events.php')) ?>" role="search">
    <div class="field field--grow"><label for="q">Rechercher</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titre de l'événement"></div>
    <div class="field"><label for="status">Statut</label>
        <select id="status" name="status"><option value="">Tous</option>
            <?php foreach (EVENT_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $fstatus === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn btn-ghost" type="submit">Filtrer</button>
    <span class="toolbar__spacer"></span>
    <a class="btn btn-primary" href="<?= e(url('admin/events.php?action=new')) ?>">+ Nouvel événement</a>
</form>

<div class="table-wrap">
<table class="table">
    <thead><tr><th>Image</th><th>Événement</th><th>Date</th><th>Prix</th><th>Places</th><th>Statut</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
        <tr>
            <td data-label="Image"><img class="thumb" src="<?= e(event_cover_url($ev)) ?>" alt="" loading="lazy"></td>
            <td data-label="Événement"><strong><?= e($ev['title']) ?></strong><small><?= e((string) ($ev['category_name'] ?? 'Sans catégorie')) ?> · <?= e($ev['location']) ?></small>
                <?php if (!(int) $ev['is_published']): ?><span class="tag">Non publié</span><?php endif; ?></td>
            <td data-label="Date"><?= e(fr_date($ev['start_date'], true)) ?><small><?= e(fr_time($ev['start_time'])) ?></small></td>
            <td data-label="Prix"><?= e(event_price_label($ev)) ?></td>
            <td data-label="Places"><?= (int) $ev['available_seats'] ?> / <?= (int) $ev['capacity'] ?><small><?= (int) $ev['res_count'] ?> réservation(s)</small></td>
            <td data-label="Statut"><?= render_status_badge($ev['status']) ?></td>
            <td class="cell-actions"><div class="actions">
                <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/reservations.php?event=' . (int) $ev['id'])) ?>">Réservations</a>
                <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/events.php?edit=' . (int) $ev['id'])) ?>">Modifier</a>
                <form method="post" action="<?= e(url('admin/events.php')) ?>"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="fstatus" value="<?= e($fstatus) ?>">
                    <button class="btn btn-ghost btn-xs" type="submit"><?= (int) $ev['is_published'] ? 'Dépublier' : 'Publier' ?></button></form>
                <form method="post" action="<?= e(url('admin/events.php')) ?>" data-confirm="Supprimer définitivement cet événement, ses réservations et ses tickets ?"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                    <button class="btn btn-danger btn-xs" type="submit">Supprimer</button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:40px">Aucun événement.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= render_pagination($pg, ['q' => $q, 'status' => $fstatus]) ?>

<section class="panel" style="margin-top:32px" aria-labelledby="cats">
    <h2 id="cats">Catégories d'événements</h2>
    <ul style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
        <?php foreach ($catCounts as $c): ?>
            <li class="tag" style="display:inline-flex;gap:8px;align-items:center;padding:6px 12px">
                <?= e($c['name']) ?> (<?= (int) $c['n'] ?>)
                <form method="post" action="<?= e(url('admin/events.php')) ?>" data-confirm="Supprimer la catégorie « <?= e($c['name']) ?> » ?"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="btn-xs" style="background:none;border:0;color:var(--danger);cursor:pointer" aria-label="Supprimer la catégorie <?= e($c['name']) ?>">✕</button></form>
            </li>
        <?php endforeach; ?>
    </ul>
    <form class="toolbar" method="post" action="<?= e(url('admin/events.php')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <div class="field field--grow"><label for="cat_name">Nouvelle catégorie</label><input type="text" id="cat_name" name="cat_name" maxlength="80" required></div>
        <button class="btn btn-ghost" type="submit">Ajouter</button>
    </form>
</section>
<?php admin_footer(); ?>
