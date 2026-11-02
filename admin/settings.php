<?php
/** Paramètres du site : identité, contact, réseaux sociaux, accueil, page « À propos », mot de passe admin */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

if (post_too_large()) {
    flash('error', 'Envoi trop volumineux (limite du serveur : ' . ini_get('post_max_size') . ').');
    admin_back('settings.php');
}

$TEXT_FIELDS = [
    'site_name'            => 120,
    'tagline'              => 120,
    'club_description'     => 1000,
    'contact_email'        => 150,
    'contact_phone'        => 40,
    'contact_address'      => 250,
    'payment_instructions' => 1500,
    'about_history'        => 3000,
    'about_mission'        => 1500,
    'about_vision'         => 1500,
    'about_activities'     => 1500,
    'about_values'         => 2000,
    'about_community'      => 1500,
    'about_timeline'       => 2500,
];
$SOCIAL_FIELDS = ['social_instagram', 'social_facebook', 'social_youtube', 'social_linkedin', 'social_tiktok'];

if (is_post()) {
    csrf_require();
    $action = post('action');

    /* ----- Changement de mot de passe ----- */
    if ($action === 'password') {
        $cur  = (string) ($_POST['current_password'] ?? '');
        $new  = (string) ($_POST['new_password'] ?? '');
        $conf = (string) ($_POST['confirm_password'] ?? '');
        $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([(int) $admin['id']]);
        $hash = (string) $st->fetchColumn();
        if (rate_limited('pwd_change', 5, 900)) {
            flash('error', 'Trop de tentatives. Réessayez dans 15 minutes.');
        } elseif (!password_verify($cur, $hash)) {
            rate_limit_hit('pwd_change');
            flash('error', 'Le mot de passe actuel est incorrect.');
        } elseif ($new !== $conf) {
            flash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
        } elseif (($err = admin_password_error($new)) !== null) {
            flash('error', $err);
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $admin['id']]);
            session_regenerate_id(true);
            log_activity('Mot de passe modifié', '');
            flash('success', 'Mot de passe modifié.');
        }
        admin_back('settings.php');
    }

    /* ----- Paramètres généraux ----- */
    if ($action === 'save') {
        $errors = [];
        $values = [];
        foreach ($TEXT_FIELDS as $key => $max) {
            $values[$key] = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
        }
        if ($values['site_name'] === '') {
            $errors[] = 'Le nom du club est obligatoire.';
        }
        if ($values['contact_email'] !== '' && !valid_email($values['contact_email'])) {
            $errors[] = 'Adresse e-mail de contact invalide.';
        }
        if ($values['contact_phone'] !== '' && !valid_phone($values['contact_phone'])) {
            $errors[] = 'Numéro de téléphone de contact invalide.';
        }
        foreach ($SOCIAL_FIELDS as $key) {
            $v = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 255);
            if ($v !== '' && !valid_url($v)) {
                $errors[] = 'Lien invalide pour ' . substr($key, 7) . ' (il doit commencer par https://).';
            }
            $values[$key] = $v;
        }

        // Vidéo d'accueil : uniquement une vidéo envoyée et publiée
        $heroVid = int_or_null(post('hero_video_id'));
        if ($heroVid !== null) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE id = ? AND source_type = 'upload' AND video_file IS NOT NULL");
            $c->execute([$heroVid]);
            if (!(int) $c->fetchColumn()) {
                $heroVid = null;
            }
        }
        $values['hero_video_id'] = $heroVid !== null ? (string) $heroVid : '';

        // Images (logo et image d'accueil)
        $newFiles = [];
        foreach (['logo' => 400, 'hero_image' => 2000] as $key => $width) {
            if (has_upload($_FILES[$key] ?? null)) {
                try {
                    $newFiles[$key] = save_image($_FILES[$key], 'site', $width)['filename'];
                } catch (RuntimeException $ex) {
                    $errors[] = ($key === 'logo' ? 'Logo' : 'Image d\'accueil') . ' : ' . $ex->getMessage();
                }
            }
        }

        if ($errors) {
            foreach ($newFiles as $f) {
                delete_upload('site', $f);
            }
            foreach ($errors as $m) {
                flash('error', $m);
            }
            admin_back('settings.php');
        }

        foreach ($values as $k => $v) {
            save_setting($k, $v);
        }
        foreach (['logo', 'hero_image'] as $key) {
            if (isset($newFiles[$key])) {
                delete_upload('site', setting($key));
                save_setting($key, $newFiles[$key]);
            } elseif (isset($_POST['remove_' . $key])) {
                delete_upload('site', setting($key));
                save_setting($key, '');
            }
        }
        log_activity('Paramètres modifiés', '');
        flash('success', 'Paramètres enregistrés.');
        admin_back('settings.php');
    }
    admin_back('settings.php');
}

$uploadVideos = $pdo->query("SELECT id, title FROM videos WHERE source_type = 'upload' AND video_file IS NOT NULL ORDER BY created_at DESC")->fetchAll();

$f = static fn(string $k): string => e(setting($k));

admin_header('Paramètres', 'settings', $admin);
?>
<form class="form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/settings.php')) ?>" novalidate data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="panel" aria-labelledby="s1"><h2 id="s1">Identité du club</h2>
        <div class="form-grid">
            <div class="field"><label for="site_name">Nom du club</label><input type="text" id="site_name" name="site_name" value="<?= $f('site_name') ?>" maxlength="120" required></div>
            <div class="field"><label for="tagline">Slogan</label><input type="text" id="tagline" name="tagline" value="<?= $f('tagline') ?>" maxlength="120"></div>
            <div class="field span-2"><label for="club_description">Description du club</label><textarea id="club_description" name="club_description" rows="3" maxlength="1000"><?= $f('club_description') ?></textarea></div>
            <div class="field">
                <label for="logo">Logo (JPG, PNG ou WebP)</label>
                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logo-preview">
                <div class="preview" id="logo-preview"></div>
                <?php if (setting('logo') !== ''): ?><img class="current-img" src="<?= e(upload_url('site', setting('logo'))) ?>" alt="Logo actuel" style="max-width:90px">
                    <label class="check"><input type="checkbox" name="remove_logo" value="1"> Revenir au logo par défaut</label><?php endif; ?>
            </div>
            <div class="field">
                <label for="hero_image">Image d'accueil (arrière-plan)</label>
                <input type="file" id="hero_image" name="hero_image" accept="image/jpeg,image/png,image/webp" data-preview="hero-preview">
                <div class="preview" id="hero-preview"></div>
                <?php if (setting('hero_image') !== ''): ?><img class="current-img" src="<?= e(upload_url('site', setting('hero_image'))) ?>" alt="Image d'accueil actuelle" style="max-width:200px">
                    <label class="check"><input type="checkbox" name="remove_hero_image" value="1"> Revenir à l'image par défaut</label><?php endif; ?>
            </div>
            <div class="field span-2">
                <label for="hero_video_id">Vidéo d'arrière-plan de l'accueil (facultative)</label>
                <select id="hero_video_id" name="hero_video_id"><option value="">— Aucune (image d'accueil) —</option>
                    <?php foreach ($uploadVideos as $uv): ?><option value="<?= (int) $uv['id'] ?>"<?= (string) setting('hero_video_id') === (string) $uv['id'] ? ' selected' : '' ?>><?= e($uv['title']) ?></option><?php endforeach; ?>
                </select>
                <p class="field-hint">Seules les vidéos envoyées depuis l'ordinateur (MP4/WebM) sont proposées. Choisissez une vidéo courte et légère.</p>
            </div>
        </div>
    </section>

    <section class="panel" aria-labelledby="s2"><h2 id="s2">Contact et réseaux sociaux</h2>
        <div class="form-grid">
            <div class="field"><label for="contact_email">E-mail de contact</label><input type="email" id="contact_email" name="contact_email" value="<?= $f('contact_email') ?>" maxlength="150"></div>
            <div class="field"><label for="contact_phone">Téléphone</label><input type="tel" id="contact_phone" name="contact_phone" value="<?= $f('contact_phone') ?>" maxlength="40"></div>
            <div class="field span-2"><label for="contact_address">Adresse</label><input type="text" id="contact_address" name="contact_address" value="<?= $f('contact_address') ?>" maxlength="250"></div>
            <?php foreach (['social_instagram' => 'Instagram', 'social_facebook' => 'Facebook', 'social_youtube' => 'YouTube', 'social_linkedin' => 'LinkedIn', 'social_tiktok' => 'TikTok'] as $k => $label): ?>
                <div class="field"><label for="<?= e($k) ?>"><?= e($label) ?></label><input type="url" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= $f($k) ?>" placeholder="https://" maxlength="255"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel" aria-labelledby="s3"><h2 id="s3">Billets payants</h2>
        <div class="field"><label for="payment_instructions">Instructions de paiement (affichées sur le ticket en attente)</label>
            <textarea id="payment_instructions" name="payment_instructions" rows="4" maxlength="1500"><?= $f('payment_instructions') ?></textarea>
            <p class="field-hint">Le site n'encaisse aucun paiement en ligne : un administrateur confirme le paiement manuellement.</p></div>
    </section>

    <section class="panel" aria-labelledby="s4"><h2 id="s4">Page « À propos »</h2>
        <p class="muted">Remplacez les textes « À compléter » par des informations vérifiées sur le club.</p>
        <div class="form-grid">
            <div class="field span-2"><label for="about_history">Notre histoire</label><textarea id="about_history" name="about_history" rows="4" maxlength="3000"><?= $f('about_history') ?></textarea></div>
            <div class="field"><label for="about_mission">Notre mission</label><textarea id="about_mission" name="about_mission" rows="4" maxlength="1500"><?= $f('about_mission') ?></textarea></div>
            <div class="field"><label for="about_vision">Notre vision</label><textarea id="about_vision" name="about_vision" rows="4" maxlength="1500"><?= $f('about_vision') ?></textarea></div>
            <div class="field span-2"><label for="about_activities">Nos activités (une par ligne)</label><textarea id="about_activities" name="about_activities" rows="4" maxlength="1500"><?= $f('about_activities') ?></textarea></div>
            <div class="field span-2"><label for="about_values">Nos valeurs (une par ligne, format : Titre|Description)</label><textarea id="about_values" name="about_values" rows="4" maxlength="2000"><?= $f('about_values') ?></textarea></div>
            <div class="field span-2"><label for="about_community">Notre communauté</label><textarea id="about_community" name="about_community" rows="3" maxlength="1500"><?= $f('about_community') ?></textarea></div>
            <div class="field span-2"><label for="about_timeline">Chronologie (une étape par ligne, format : Date|Titre|Description)</label><textarea id="about_timeline" name="about_timeline" rows="5" maxlength="2500"><?= $f('about_timeline') ?></textarea></div>
        </div>
    </section>

    <div class="form-actions"><button class="btn btn-primary" type="submit">Enregistrer les paramètres</button></div>
</form>

<section class="panel" aria-labelledby="s5" style="margin-top:32px"><h2 id="s5">Changer mon mot de passe</h2>
    <form class="form" method="post" action="<?= e(url('admin/settings.php')) ?>" autocomplete="off" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="form-grid">
            <div class="field span-2"><label for="current_password">Mot de passe actuel</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
            <div class="field"><label for="new_password">Nouveau mot de passe (10 caractères minimum, lettres et chiffres)</label><input type="password" id="new_password" name="new_password" required minlength="10" autocomplete="new-password"></div>
            <div class="field"><label for="confirm_password">Confirmation</label><input type="password" id="confirm_password" name="confirm_password" required minlength="10" autocomplete="new-password"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Changer le mot de passe</button></div>
    </form>
</section>
<?php admin_footer(); ?>
