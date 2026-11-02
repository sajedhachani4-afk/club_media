<?php
/** Administration des vidéos : envoi depuis l'ordinateur (MP4/WebM) ou lien YouTube */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

$limit = effective_upload_limit(MAX_VIDEO_BYTES);

if (post_too_large()) {
    flash('error', 'Envoi trop volumineux : la limite du serveur est de ' . human_size($limit) . '. Augmentez upload_max_filesize et post_max_size dans php.ini (voir le guide d\'installation).');
    admin_back('videos.php');
}

if (is_post()) {
    csrf_require();
    $action = post('action');
    $id     = (int) post('id', '0');

    if ($action === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE videos SET is_published = 1 - is_published WHERE id = ?')->execute([$id]);
        flash('success', 'Visibilité de la vidéo mise à jour.');
        admin_back('videos.php');
    }

    if ($action === 'delete' && $id > 0) {
        $s = $pdo->prepare('SELECT title, video_file, thumbnail FROM videos WHERE id = ?');
        $s->execute([$id]);
        if ($row = $s->fetch()) {
            $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
            delete_upload('videos', $row['video_file']);
            delete_upload('thumbs', $row['thumbnail']);
            if ((int) setting('hero_video_id', '0') === $id) {
                save_setting('hero_video_id', '');
            }
            log_activity('Vidéo supprimée', $row['title']);
            flash('success', 'Vidéo supprimée.');
        }
        admin_back('videos.php');
    }

    if ($action === 'save') {
        $editing = $id > 0;
        $old = null;
        if ($editing) {
            $o = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
            $o->execute([$id]);
            $old = $o->fetch();
            if (!$old) {
                flash('error', 'Vidéo introuvable.');
                admin_back('videos.php');
            }
        }

        $title  = mb_substr(post('title'), 0, 160);
        $desc   = mb_substr(post('description'), 0, 2000);
        $evId   = int_or_null(post('event_id'));
        $source = post('source_type') === 'youtube' ? 'youtube' : 'upload';
        $pub    = isset($_POST['is_published']) ? 1 : 0;
        $errors = [];

        if (mb_strlen($title) < 2) { $errors['title'] = 'Titre obligatoire.'; }
        if ($evId !== null) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM events WHERE id = ?');
            $c->execute([$evId]);
            if (!(int) $c->fetchColumn()) { $evId = null; }
        }

        $ytId = null;
        $ytUrl = null;
        $newFile = null;
        if ($source === 'youtube') {
            $ytId = youtube_id(post('youtube_url'));
            if ($ytId === null) {
                $errors['youtube_url'] = 'Lien YouTube invalide (exemples : https://youtu.be/XXXXXXXXXXX ou https://www.youtube.com/watch?v=XXXXXXXXXXX).';
            } else {
                $ytUrl = 'https://www.youtube.com/watch?v=' . $ytId;
            }
        } else {
            $hasFile = has_upload($_FILES['video_file'] ?? null);
            if (!$hasFile && (!$editing || $old['source_type'] !== 'upload')) {
                $errors['video_file'] = 'Choisissez un fichier vidéo (MP4 ou WebM).';
            } elseif ($hasFile) {
                try {
                    $newFile = save_video($_FILES['video_file']);
                } catch (RuntimeException $ex) {
                    $errors['video_file'] = $ex->getMessage();
                }
            }
        }

        $newThumb = null;
        if (has_upload($_FILES['thumbnail'] ?? null)) {
            try {
                $newThumb = save_image($_FILES['thumbnail'], 'thumbs', 960)['filename'];
            } catch (RuntimeException $ex) {
                $errors['thumbnail'] = $ex->getMessage();
            }
        }

        if ($errors) {
            // On ne garde pas les fichiers déjà enregistrés si le formulaire échoue
            delete_upload('videos', $newFile);
            delete_upload('thumbs', $newThumb);
            $_SESSION['video_form_errors'] = $errors;
            $_SESSION['video_form_old']    = $_POST;
            redirect('admin/videos.php?' . ($editing ? 'edit=' . $id : 'action=new'));
        }

        $videoFile = $old['video_file'] ?? null;
        $thumb     = $old['thumbnail'] ?? null;
        if ($source === 'youtube') {
            if ($editing && $videoFile) { delete_upload('videos', $videoFile); }
            $videoFile = null;
        } elseif ($newFile !== null) {
            if ($videoFile) { delete_upload('videos', $videoFile); }
            $videoFile = $newFile;
            $ytId = $ytUrl = null;
        } else {
            $ytId = $ytUrl = null;
        }
        if ($newThumb !== null) {
            delete_upload('thumbs', $thumb);
            $thumb = $newThumb;
        } elseif ($editing && isset($_POST['remove_thumbnail'])) {
            delete_upload('thumbs', $thumb);
            $thumb = null;
        }

        if ($editing) {
            $pdo->prepare('UPDATE videos SET event_id=?, title=?, description=?, thumbnail=?, source_type=?, video_file=?, video_url=?, youtube_id=?, is_published=? WHERE id=?')
                ->execute([$evId, $title, $desc, $thumb, $source, $videoFile, $ytUrl, $ytId, $pub, $id]);
            log_activity('Vidéo modifiée', $title);
            flash('success', 'Vidéo mise à jour.');
        } else {
            $pdo->prepare('INSERT INTO videos (event_id, title, description, thumbnail, source_type, video_file, video_url, youtube_id, is_published) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$evId, $title, $desc, $thumb, $source, $videoFile, $ytUrl, $ytId, $pub]);
            log_activity('Vidéo ajoutée', $title);
            flash('success', 'Vidéo ajoutée.');
        }
        admin_back('videos.php');
    }
}

$formErrors = $_SESSION['video_form_errors'] ?? [];
$formOld    = $_SESSION['video_form_old'] ?? null;
unset($_SESSION['video_form_errors'], $_SESSION['video_form_old']);
$eventsList = $pdo->query('SELECT id, title FROM events ORDER BY start_date DESC')->fetchAll();

$editId = (int) get('edit', '0');
if ($editId > 0 || get('action') === 'new') {
    $vd = null;
    if ($editId > 0) {
        $s = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
        $s->execute([$editId]);
        $vd = $s->fetch() ?: null;
        if ($vd === null) {
            flash('error', 'Vidéo introuvable.');
            admin_back('videos.php');
        }
    }
    $v = static function (string $k, $default = '') use ($formOld, $vd) {
        if (is_array($formOld) && isset($formOld[$k]) && is_string($formOld[$k])) {
            return $formOld[$k];
        }
        return $vd[$k] ?? $default;
    };
    $src   = (string) ($formOld['source_type'] ?? ($vd['source_type'] ?? 'upload'));
    $isPub = is_array($formOld) ? isset($formOld['is_published']) : (!$vd || (int) $vd['is_published'] === 1);

    admin_header($vd ? 'Modifier la vidéo' : 'Ajouter une vidéo', 'videos', $admin);
    ?>
    <form class="panel form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/videos.php')) ?>" novalidate data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $vd ? (int) $vd['id'] : 0 ?>">
        <?php if ($formErrors): ?><div class="alert alert--error" role="alert">Le formulaire contient des erreurs.</div><?php endif; ?>
        <div class="form-grid">
            <div class="field span-2">
                <label for="title">Titre <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="title" name="title" value="<?= e((string) $v('title')) ?>" required maxlength="160"<?= isset($formErrors['title']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'title') ?>
            </div>
            <div class="field span-2">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="2000" rows="4"><?= e((string) $v('description')) ?></textarea>
            </div>
            <div class="field">
                <label for="event_id">Événement associé</label>
                <select id="event_id" name="event_id"><option value="">— Aucun —</option>
                    <?php foreach ($eventsList as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= (string) $v('event_id') === (string) $ev['id'] ? ' selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="source_type">Source</label>
                <select id="source_type" name="source_type" data-source-toggle>
                    <option value="upload"<?= $src === 'upload' ? ' selected' : '' ?>>Fichier depuis mon ordinateur</option>
                    <option value="youtube"<?= $src === 'youtube' ? ' selected' : '' ?>>Lien YouTube</option>
                </select>
            </div>
            <div class="field span-2" data-source="upload">
                <label for="video_file">Fichier vidéo (MP4 ou WebM, <?= e(human_size($limit)) ?> max)</label>
                <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm"<?= isset($formErrors['video_file']) ? ' aria-invalid="true"' : '' ?>>
                <?php if ($vd && $vd['source_type'] === 'upload'): ?><p class="field-hint">Un fichier est déjà enregistré : laissez vide pour le conserver.</p><?php endif; ?>
                <p class="field-hint">Conseil : privilégiez des vidéos compressées (H.264). Pour de longues vidéos, YouTube est plus adapté.</p>
                <?= field_error($formErrors, 'video_file') ?>
            </div>
            <div class="field span-2" data-source="youtube" hidden>
                <label for="youtube_url">Lien YouTube</label>
                <input type="url" id="youtube_url" name="youtube_url" value="<?= e((string) ($formOld['youtube_url'] ?? ($vd['video_url'] ?? ''))) ?>" placeholder="https://www.youtube.com/watch?v=..." maxlength="255"<?= isset($formErrors['youtube_url']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'youtube_url') ?>
            </div>
            <div class="field span-2">
                <label for="thumbnail">Miniature (facultative pour YouTube, recommandée pour un fichier)</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp" data-preview="thumb-preview"<?= isset($formErrors['thumbnail']) ? ' aria-invalid="true"' : '' ?>>
                <?= field_error($formErrors, 'thumbnail') ?>
                <div class="preview" id="thumb-preview"></div>
                <?php if ($vd && !empty($vd['thumbnail'])): ?>
                    <img class="current-img" src="<?= e(upload_url('thumbs', $vd['thumbnail'])) ?>" alt="Miniature actuelle">
                    <label class="check"><input type="checkbox" name="remove_thumbnail" value="1"> Supprimer la miniature</label>
                <?php endif; ?>
            </div>
            <div class="field span-2">
                <label class="check"><input type="checkbox" name="is_published" value="1"<?= $isPub ? ' checked' : '' ?>> Publiée (visible sur le site)</label>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= e(url('admin/videos.php')) ?>">Annuler</a>
        </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$rows = $pdo->query(
    'SELECT v.*, e.title AS event_title FROM videos v LEFT JOIN events e ON e.id = v.event_id ORDER BY v.created_at DESC, v.id DESC'
)->fetchAll();

admin_header('Vidéos', 'videos', $admin);
?>
<div class="toolbar"><span class="toolbar__spacer"></span><a class="btn btn-primary" href="<?= e(url('admin/videos.php?action=new')) ?>">+ Ajouter une vidéo</a></div>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Miniature</th><th>Titre</th><th>Source</th><th>Événement</th><th>Statut</th><th>Ajoutée</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $v): $th = video_thumb_url($v); ?>
        <tr>
            <td data-label="Miniature"><?php if ($th): ?><img class="thumb" src="<?= e($th) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb" style="display:grid;place-items:center"><i data-lucide="film" aria-hidden="true"></i></span><?php endif; ?></td>
            <td data-label="Titre"><strong><?= e($v['title']) ?></strong></td>
            <td data-label="Source"><span class="tag"><?= $v['source_type'] === 'youtube' ? 'YouTube' : 'Fichier' ?></span></td>
            <td data-label="Événement"><?= e((string) ($v['event_title'] ?? '—')) ?></td>
            <td data-label="Statut"><span class="badge badge--<?= (int) $v['is_published'] ? 'valid' : 'pending' ?>"><?= (int) $v['is_published'] ? 'Publiée' : 'Masquée' ?></span></td>
            <td data-label="Ajoutée"><?= e(fr_date(substr((string) $v['created_at'], 0, 10), true)) ?></td>
            <td class="cell-actions"><div class="actions">
                <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/videos.php?edit=' . (int) $v['id'])) ?>">Modifier</a>
                <form method="post" action="<?= e(url('admin/videos.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                    <button class="btn btn-ghost btn-xs" type="submit"><?= (int) $v['is_published'] ? 'Masquer' : 'Publier' ?></button></form>
                <form method="post" action="<?= e(url('admin/videos.php')) ?>" data-confirm="Supprimer cette vidéo ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                    <button class="btn btn-danger btn-xs" type="submit">Supprimer</button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:40px">Aucune vidéo. Ajoutez la première !</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php admin_footer(); ?>
