<?php
/**
 * Administration des membres et de l'équipe.
 *  ?view=members : tous les membres du club (compte dans « Nombre de membres »)
 *  ?view=team    : membres affichés sur la page publique « Notre équipe »
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

const TEAM_ROLES = ['Président', 'Vice-président', 'Responsable Media', 'Photographe', 'Vidéaste', 'Designer', 'Responsable Événements', 'Membre'];

$view = get('view') === 'team' ? 'team' : 'members';

if (post_too_large()) {
    flash('error', 'Photo trop volumineuse pour ce serveur (limite ' . ini_get('post_max_size') . ').');
    admin_back('team.php', ['view' => $view]);
}

if (is_post()) {
    csrf_require();
    $action = post('action');
    $id     = (int) post('id', '0');
    $view   = post('view') === 'team' ? 'team' : 'members';

    if ($action === 'delete' && $id > 0) {
        $s = $pdo->prepare('SELECT full_name, photo FROM team_members WHERE id = ?');
        $s->execute([$id]);
        if ($row = $s->fetch()) {
            $pdo->prepare('DELETE FROM team_members WHERE id = ?')->execute([$id]);
            delete_upload('team', $row['photo']);
            log_activity('Membre supprimé', $row['full_name']);
            flash('success', 'Membre supprimé.');
        }
        admin_back('team.php', ['view' => $view]);
    }

    if ($action === 'toggle_team' && $id > 0) {
        $pdo->prepare('UPDATE team_members SET show_on_team_page = 1 - show_on_team_page WHERE id = ?')->execute([$id]);
        flash('success', 'Affichage sur la page « Notre équipe » mis à jour.');
        admin_back('team.php', ['view' => $view]);
    }

    if ($action === 'save') {
        $old = null;
        if ($id > 0) {
            $o = $pdo->prepare('SELECT * FROM team_members WHERE id = ?');
            $o->execute([$id]);
            $old = $o->fetch();
            if (!$old) {
                flash('error', 'Membre introuvable.');
                admin_back('team.php', ['view' => $view]);
            }
        }
        $name   = mb_substr(post('full_name'), 0, 120);
        $role   = mb_substr(post('role'), 0, 80);
        $desc   = mb_substr(post('description'), 0, 1000);
        $social = mb_substr(post('social_url'), 0, 255);
        $order  = max(-9999, min(9999, (int) post('display_order', '0')));
        $active = isset($_POST['is_active']) ? 1 : 0;
        $show   = isset($_POST['show_on_team_page']) ? 1 : 0;
        $errors = [];

        if (mb_strlen($name) < 2) { $errors['full_name'] = 'Nom obligatoire.'; }
        if ($role === '') { $role = 'Membre'; }
        if ($social !== '' && !valid_url($social)) {
            $errors['social_url'] = 'Lien invalide (doit commencer par https://).';
        }
        $photo = $old['photo'] ?? null;
        $newPhoto = null;
        if (has_upload($_FILES['photo'] ?? null)) {
            try {
                $newPhoto = save_image($_FILES['photo'], 'team', 800)['filename'];
            } catch (RuntimeException $ex) {
                $errors['photo'] = $ex->getMessage();
            }
        }
        if ($errors) {
            delete_upload('team', $newPhoto);
            $_SESSION['team_form_errors'] = $errors;
            $_SESSION['team_form_old']    = $_POST;
            redirect('admin/team.php?' . http_build_query(['view' => $view] + ($id > 0 ? ['edit' => $id] : ['action' => 'new'])));
        }
        if ($newPhoto !== null) {
            delete_upload('team', $photo);
            $photo = $newPhoto;
        } elseif ($old && isset($_POST['remove_photo'])) {
            delete_upload('team', $photo);
            $photo = null;
        }
        if ($old) {
            $pdo->prepare('UPDATE team_members SET full_name=?, photo=?, role=?, description=?, social_url=?, display_order=?, is_active=?, show_on_team_page=? WHERE id=?')
                ->execute([$name, $photo, $role, $desc, $social !== '' ? $social : null, $order, $active, $show, $id]);
            log_activity('Membre modifié', $name);
            flash('success', 'Membre mis à jour.');
        } else {
            $pdo->prepare('INSERT INTO team_members (full_name, photo, role, description, social_url, display_order, is_active, show_on_team_page) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$name, $photo, $role, $desc, $social !== '' ? $social : null, $order, $active, $show]);
            log_activity('Membre ajouté', $name);
            flash('success', 'Membre ajouté.');
        }
        admin_back('team.php', ['view' => $view]);
    }
    admin_back('team.php', ['view' => $view]);
}

$active = $view === 'team' ? 'team' : 'members';
$formErrors = $_SESSION['team_form_errors'] ?? [];
$formOld    = $_SESSION['team_form_old'] ?? null;
unset($_SESSION['team_form_errors'], $_SESSION['team_form_old']);

/* ---------------------------------------------------------------- Formulaire */
$editId = (int) get('edit', '0');
if ($editId > 0 || get('action') === 'new') {
    $m = null;
    if ($editId > 0) {
        $s = $pdo->prepare('SELECT * FROM team_members WHERE id = ?');
        $s->execute([$editId]);
        $m = $s->fetch() ?: null;
        if ($m === null) {
            flash('error', 'Membre introuvable.');
            admin_back('team.php', ['view' => $view]);
        }
    }
    $v = static function (string $k, $default = '') use ($formOld, $m) {
        if (is_array($formOld) && isset($formOld[$k]) && is_string($formOld[$k])) {
            return $formOld[$k];
        }
        return $m[$k] ?? $default;
    };
    $isActive = is_array($formOld) ? isset($formOld['is_active']) : (!$m || (int) $m['is_active'] === 1);
    $isShown  = is_array($formOld) ? isset($formOld['show_on_team_page']) : (!$m || (int) $m['show_on_team_page'] === 1);

    admin_header($m ? 'Modifier le membre' : 'Ajouter un membre', $active, $admin);
    ?>
    <form class="panel form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/team.php')) ?>" novalidate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $m ? (int) $m['id'] : 0 ?>">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <?php if ($formErrors): ?><div class="alert alert--error" role="alert">Le formulaire contient des erreurs.</div><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label for="full_name">Nom complet <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?= e((string) $v('full_name')) ?>" required maxlength="120"<?= isset($formErrors['full_name']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'full_name') ?>
            </div>
            <div class="field">
                <label for="role">Rôle</label>
                <input type="text" id="role" name="role" list="roles" value="<?= e((string) $v('role', 'Membre')) ?>" maxlength="80">
                <datalist id="roles"><?php foreach (TEAM_ROLES as $r): ?><option value="<?= e($r) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="field span-2">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000"><?= e((string) $v('description')) ?></textarea>
            </div>
            <div class="field">
                <label for="social_url">Lien Instagram ou LinkedIn</label>
                <input type="url" id="social_url" name="social_url" value="<?= e((string) $v('social_url')) ?>" placeholder="https://www.instagram.com/..." maxlength="255"<?= isset($formErrors['social_url']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'social_url') ?>
            </div>
            <div class="field">
                <label for="display_order">Ordre d'affichage (petit nombre = en premier)</label>
                <input type="number" id="display_order" name="display_order" value="<?= (int) $v('display_order', '0') ?>" min="-9999" max="9999">
            </div>
            <div class="field span-2">
                <label for="photo">Photo (JPG, PNG ou WebP)</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photo-preview"<?= isset($formErrors['photo']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'photo') ?>
                <div class="preview" id="photo-preview"></div>
                <?php if ($m && !empty($m['photo'])): ?>
                    <img class="current-img" src="<?= e(upload_url('team', $m['photo'])) ?>" alt="Photo actuelle" style="max-width:140px">
                    <label class="check"><input type="checkbox" name="remove_photo" value="1"> Supprimer la photo</label>
                <?php endif; ?>
            </div>
            <div class="field span-2">
                <label class="check"><input type="checkbox" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>> Membre actif (compté dans les statistiques)</label>
                <label class="check"><input type="checkbox" name="show_on_team_page" value="1"<?= $isShown ? ' checked' : '' ?>> Afficher sur la page publique « Notre équipe »</label>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/team.php?view=' . $view)) ?>">Annuler</a>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

/* -------------------------------------------------------------------- Liste */
$where = $view === 'team' ? 'WHERE show_on_team_page = 1' : '';
$rows  = $pdo->query("SELECT * FROM team_members $where ORDER BY display_order ASC, id ASC")->fetchAll();
$title = $view === 'team' ? 'Équipe (page publique)' : 'Membres du club';

admin_header($title, $active, $admin);
?>
<div class="toolbar">
    <p class="muted" style="margin:0"><?= $view === 'team'
        ? 'Membres affichés sur la page « Notre équipe ». Utilisez « Ordre » pour les classer.'
        : 'Tous les membres du club. Seuls les membres actifs sont comptés sur l\'accueil.' ?></p>
    <span class="toolbar__spacer"></span>
    <a class="btn btn-primary" href="<?= e(url('admin/team.php?action=new&view=' . $view)) ?>">+ Ajouter un membre</a>
</div>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Photo</th><th>Nom</th><th>Rôle</th><th>Ordre</th><th>Actif</th><th>Page équipe</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
        <tr>
            <td data-label="Photo"><img class="thumb" src="<?= e(!empty($m['photo']) ? upload_url('team', $m['photo']) : asset('images/avatar-placeholder.svg')) ?>" alt="" loading="lazy" width="48" height="48"></td>
            <td data-label="Nom"><strong><?= e($m['full_name']) ?></strong></td>
            <td data-label="Rôle"><?= e($m['role']) ?></td>
            <td data-label="Ordre"><?= (int) $m['display_order'] ?></td>
            <td data-label="Actif"><span class="badge badge--<?= (int) $m['is_active'] ? 'valid' : 'pending' ?>"><?= (int) $m['is_active'] ? 'Oui' : 'Non' ?></span></td>
            <td data-label="Page équipe"><span class="badge badge--<?= (int) $m['show_on_team_page'] ? 'valid' : 'pending' ?>"><?= (int) $m['show_on_team_page'] ? 'Affiché' : 'Masqué' ?></span></td>
            <td class="cell-actions"><div class="actions">
                <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/team.php?edit=' . (int) $m['id'] . '&view=' . $view)) ?>">Modifier</a>
                <form method="post" action="<?= e(url('admin/team.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_team"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>">
                    <button class="btn btn-ghost btn-xs" type="submit"><?= (int) $m['show_on_team_page'] ? 'Masquer' : 'Afficher' ?></button></form>
                <form method="post" action="<?= e(url('admin/team.php')) ?>" data-confirm="Supprimer ce membre ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="view" value="<?= e($view) ?>">
                    <button class="btn btn-danger btn-xs" type="submit">Supprimer</button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:40px">Aucun membre pour le moment.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php admin_footer(); ?>
