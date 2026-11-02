<?php
/** Administration des photos : albums (un par événement), envoi multiple, légendes, suppression */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

const MAX_PHOTOS_PER_UPLOAD = 20;

if (post_too_large()) {
    flash('error', 'Envoi trop volumineux : la limite du serveur est de ' . ini_get('post_max_size') . ' par envoi. Envoyez moins de photos à la fois ou augmentez post_max_size dans php.ini.');
    admin_back('photos.php');
}

/** Supprime les fichiers d'une photo (image + miniature). */
function remove_photo_files(string $filename): void
{
    delete_upload('photos', $filename);
}

if (is_post()) {
    csrf_require();
    $action  = post('action');
    $id      = (int) post('id', '0');
    $albumId = (int) post('album_id', '0');

    /* ----- Albums ----- */
    if ($action === 'album_save') {
        $title = mb_substr(post('title'), 0, 160);
        $desc  = mb_substr(post('description'), 0, 1000);
        $evId  = int_or_null(post('event_id'));
        if (mb_strlen($title) < 2) {
            flash('error', 'Le titre de l\'album est obligatoire.');
            admin_back('photos.php', ['edit_album' => $id ?: '']);
        }
        if ($evId !== null) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM events WHERE id = ?');
            $c->execute([$evId]);
            if (!(int) $c->fetchColumn()) {
                $evId = null;
            }
        }
        if ($id > 0) {
            $pdo->prepare('UPDATE albums SET title = ?, description = ?, event_id = ? WHERE id = ?')->execute([$title, $desc, $evId, $id]);
            log_activity('Album modifié', $title);
            flash('success', 'Album mis à jour.');
            admin_back('photos.php', ['album' => $id]);
        }
        $pdo->prepare('INSERT INTO albums (title, description, event_id) VALUES (?, ?, ?)')->execute([$title, $desc, $evId]);
        $newId = (int) $pdo->lastInsertId();
        log_activity('Album créé', $title);
        flash('success', 'Album créé. Ajoutez maintenant vos photos.');
        admin_back('photos.php', ['album' => $newId]);
    }

    if ($action === 'album_delete' && $id > 0) {
        $s = $pdo->prepare('SELECT filename FROM photos WHERE album_id = ?');
        $s->execute([$id]);
        $files = $s->fetchAll(PDO::FETCH_COLUMN);
        $t = $pdo->prepare('SELECT title FROM albums WHERE id = ?');
        $t->execute([$id]);
        $title = (string) $t->fetchColumn();
        $pdo->prepare('DELETE FROM albums WHERE id = ?')->execute([$id]); // photos supprimées en cascade
        foreach ($files as $f) {
            remove_photo_files((string) $f);
        }
        log_activity('Album supprimé', $title . ' (' . count($files) . ' photos)');
        flash('success', 'Album et photos supprimés.');
        admin_back('photos.php');
    }

    /* ----- Photos ----- */
    if ($action === 'upload' && $albumId > 0) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM albums WHERE id = ?');
        $chk->execute([$albumId]);
        if (!(int) $chk->fetchColumn()) {
            flash('error', 'Album introuvable.');
            admin_back('photos.php');
        }
        $files   = normalize_files($_FILES['photos'] ?? null);
        $files   = array_values(array_filter($files, 'has_upload'));
        $caption = mb_substr(post('caption'), 0, 255);
        if (!$files) {
            flash('error', 'Sélectionnez au moins une photo.');
            admin_back('photos.php', ['album' => $albumId]);
        }
        if (count($files) > MAX_PHOTOS_PER_UPLOAD) {
            flash('error', 'Maximum ' . MAX_PHOTOS_PER_UPLOAD . ' photos par envoi.');
            admin_back('photos.php', ['album' => $albumId]);
        }
        $ok = 0;
        $errors = [];
        $ins = $pdo->prepare('INSERT INTO photos (album_id, filename, caption, width, height, sort_order) VALUES (?,?,?,?,?,?)');
        foreach ($files as $f) {
            try {
                $r = save_image($f, 'photos', 1920, true, 640);
                $ins->execute([$albumId, $r['filename'], $caption !== '' ? $caption : null, $r['width'], $r['height'], 0]);
                $ok++;
            } catch (RuntimeException $ex) {
                $errors[] = (string) $f['name'] . ' : ' . $ex->getMessage();
            }
        }
        if ($ok > 0) {
            log_activity('Photos ajoutées', $ok . ' photo(s) — album #' . $albumId);
            flash('success', $ok . ' photo(s) ajoutée(s).');
        }
        foreach (array_slice($errors, 0, 5) as $msg) {
            flash('error', $msg);
        }
        admin_back('photos.php', ['album' => $albumId]);
    }

    if ($action === 'photo_update' && $id > 0) {
        $cap  = mb_substr(post('caption'), 0, 255);
        $sort = max(-9999, min(9999, (int) post('sort_order', '0')));
        $pdo->prepare('UPDATE photos SET caption = ?, sort_order = ? WHERE id = ?')->execute([$cap !== '' ? $cap : null, $sort, $id]);
        flash('success', 'Photo mise à jour.');
        admin_back('photos.php', ['album' => $albumId]);
    }

    if ($action === 'photo_delete' && $id > 0) {
        $s = $pdo->prepare('SELECT filename FROM photos WHERE id = ?');
        $s->execute([$id]);
        if ($fn = $s->fetchColumn()) {
            $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
            remove_photo_files((string) $fn);
            log_activity('Photo supprimée', 'Photo #' . $id);
            flash('success', 'Photo supprimée.');
        }
        admin_back('photos.php', ['album' => $albumId]);
    }

    admin_back('photos.php');
}

$eventsList = $pdo->query('SELECT id, title FROM events ORDER BY start_date DESC')->fetchAll();
$albumId    = (int) get('album', '0');
$editAlbum  = (int) get('edit_album', '0');
$isNew      = get('action') === 'new_album';

/* ---------------------------------------------------------- Formulaire d'album */
if ($isNew || $editAlbum > 0) {
    $al = null;
    if ($editAlbum > 0) {
        $s = $pdo->prepare('SELECT * FROM albums WHERE id = ?');
        $s->execute([$editAlbum]);
        $al = $s->fetch() ?: null;
        if ($al === null) {
            flash('error', 'Album introuvable.');
            admin_back('photos.php');
        }
    }
    admin_header($al ? 'Modifier l\'album' : 'Nouvel album', 'photos', $admin);
    ?>
    <form class="panel form" method="post" action="<?= e(url('admin/photos.php')) ?>" novalidate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="album_save">
        <input type="hidden" name="id" value="<?= $al ? (int) $al['id'] : 0 ?>">
        <div class="form-grid">
            <div class="field span-2">
                <label for="title">Titre de l'album <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="title" name="title" value="<?= e((string) ($al['title'] ?? '')) ?>" required maxlength="160">
            </div>
            <div class="field span-2">
                <label for="event_id">Événement associé</label>
                <select id="event_id" name="event_id"><option value="">— Aucun —</option>
                    <?php foreach ($eventsList as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= (string) ($al['event_id'] ?? '') === (string) $ev['id'] ? ' selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field span-2">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000"><?= e((string) ($al['description'] ?? '')) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/photos.php' . ($al ? '?album=' . (int) $al['id'] : ''))) ?>">Annuler</a>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

/* ------------------------------------------------------------ Détail d'un album */
if ($albumId > 0) {
    $s = $pdo->prepare('SELECT a.*, e.title AS event_title FROM albums a LEFT JOIN events e ON e.id = a.event_id WHERE a.id = ?');
    $s->execute([$albumId]);
    $al = $s->fetch();
    if (!$al) {
        flash('error', 'Album introuvable.');
        admin_back('photos.php');
    }
    $p = $pdo->prepare('SELECT * FROM photos WHERE album_id = ? ORDER BY sort_order ASC, id DESC');
    $p->execute([$albumId]);
    $photos = $p->fetchAll();

    admin_header('Album : ' . $al['title'], 'photos', $admin);
    ?>
    <div class="toolbar">
        <a class="btn btn-ghost" href="<?= e(url('admin/photos.php')) ?>">← Tous les albums</a>
        <span class="toolbar__spacer"></span>
        <a class="btn btn-ghost" href="<?= e(url('admin/photos.php?edit_album=' . $albumId)) ?>">Modifier l'album</a>
        <form method="post" action="<?= e(url('admin/photos.php')) ?>" data-confirm="Supprimer cet album et toutes ses photos ?"><?= csrf_field() ?>
            <input type="hidden" name="action" value="album_delete"><input type="hidden" name="id" value="<?= $albumId ?>">
            <button class="btn btn-danger" type="submit">Supprimer l'album</button></form>
    </div>

    <section class="panel" aria-labelledby="up-h">
        <h2 id="up-h">Ajouter des photos</h2>
        <form class="form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/photos.php')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="album_id" value="<?= $albumId ?>">
            <div class="form-grid">
                <div class="field span-2">
                    <label for="photos">Photos (JPG, PNG ou WebP — <?= e(human_size(MAX_IMAGE_BYTES)) ?> max chacune, <?= MAX_PHOTOS_PER_UPLOAD ?> par envoi)</label>
                    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-preview="photos-preview">
                    <div class="preview" id="photos-preview"></div>
                </div>
                <div class="field span-2">
                    <label for="caption">Légende commune (facultative)</label>
                    <input type="text" id="caption" name="caption" maxlength="255">
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Envoyer les photos</button></div>
        </form>
    </section>

    <section class="panel" aria-labelledby="ph-h">
        <h2 id="ph-h"><?= count($photos) ?> photo(s)</h2>
        <?php if (!$photos): ?><p class="muted">Cet album est vide.</p><?php endif; ?>
        <div class="photo-admin">
            <?php foreach ($photos as $ph): ?>
                <article class="photo-admin__item">
                    <img src="<?= e(photo_thumb_url($ph)) ?>" alt="<?= e((string) ($ph['caption'] ?? 'Photo')) ?>" loading="lazy">
                    <form method="post" action="<?= e(url('admin/photos.php')) ?>" class="form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="photo_update">
                        <input type="hidden" name="id" value="<?= (int) $ph['id'] ?>">
                        <input type="hidden" name="album_id" value="<?= $albumId ?>">
                        <div class="field"><label for="cap<?= (int) $ph['id'] ?>">Légende</label>
                            <input type="text" id="cap<?= (int) $ph['id'] ?>" name="caption" value="<?= e((string) ($ph['caption'] ?? '')) ?>" maxlength="255"></div>
                        <div class="field"><label for="so<?= (int) $ph['id'] ?>">Ordre</label>
                            <input type="number" id="so<?= (int) $ph['id'] ?>" name="sort_order" value="<?= (int) $ph['sort_order'] ?>" min="-9999" max="9999"></div>
                        <button class="btn btn-ghost btn-xs" type="submit">Enregistrer</button>
                    </form>
                    <form method="post" action="<?= e(url('admin/photos.php')) ?>" data-confirm="Supprimer cette photo ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="photo_delete">
                        <input type="hidden" name="id" value="<?= (int) $ph['id'] ?>">
                        <input type="hidden" name="album_id" value="<?= $albumId ?>">
                        <button class="btn btn-danger btn-xs" type="submit">Supprimer</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    admin_footer();
    exit;
}

/* ----------------------------------------------------------- Liste des albums */
$albums = $pdo->query(
    'SELECT a.*, e.title AS event_title,
            (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS n,
            (SELECT p2.filename FROM photos p2 WHERE p2.album_id = a.id ORDER BY p2.sort_order ASC, p2.id DESC LIMIT 1) AS cover
     FROM albums a LEFT JOIN events e ON e.id = a.event_id ORDER BY a.created_at DESC, a.id DESC'
)->fetchAll();

admin_header('Photos', 'photos', $admin);
?>
<div class="toolbar"><span class="toolbar__spacer"></span><a class="btn btn-primary" href="<?= e(url('admin/photos.php?action=new_album')) ?>">+ Nouvel album</a></div>
<?php if (!$albums): ?>
    <div class="panel"><p class="muted" style="text-align:center;padding:30px">Aucun album. Créez le premier album (idéalement un par événement), puis ajoutez des photos.</p></div>
<?php endif; ?>
<div class="album-cards">
    <?php foreach ($albums as $a): ?>
        <a class="album-card" href="<?= e(url('admin/photos.php?album=' . (int) $a['id'])) ?>">
            <strong><?= e($a['title']) ?></strong>
            <small class="muted"><?= (int) $a['n'] ?> photo(s)<?= $a['event_title'] ? ' · ' . e($a['event_title']) : '' ?></small>
        </a>
    <?php endforeach; ?>
</div>
<?php admin_footer(); ?>
