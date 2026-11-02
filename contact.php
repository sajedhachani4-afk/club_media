<?php
/** Contact : le message est enregistré dans MySQL et consultable en administration */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$errors = [];
if (is_post()) {
    csrf_require();

    if (post('website') !== '') {                       // piège anti-robots (champ caché)
        flash('success', 'Merci ! Votre message a bien été envoyé.');
        redirect('contact.php');
    }

    $name    = mb_substr(post('full_name'), 0, 120);
    $email   = post('email');
    $subject = mb_substr(post('subject'), 0, 180);
    $message = post('message');

    if (mb_strlen($name) < 2)                          { $errors['full_name'] = 'Veuillez indiquer votre nom complet.'; }
    if (!valid_email($email))                          { $errors['email']     = 'Adresse e-mail invalide.'; }
    if (mb_strlen($subject) < 3)                       { $errors['subject']   = 'Veuillez indiquer un sujet (3 caractères minimum).'; }
    if (mb_strlen($message) < 10)                      { $errors['message']   = 'Votre message est trop court (10 caractères minimum).'; }
    elseif (mb_strlen($message) > 3000)                { $errors['message']   = 'Votre message est trop long (3000 caractères maximum).'; }

    if (!$errors && rate_limited('contact', 5, 3600)) {
        $errors['form'] = 'Trop de messages envoyés. Réessayez dans une heure.';
    }
    if (!$errors) {
        $st = db()->prepare('INSERT INTO contact_messages (full_name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$name, $email, $subject, $message, client_ip()]);
        rate_limit_hit('contact');
        flash('success', 'Merci ! Votre message a bien été envoyé. Nous vous répondrons dès que possible.');
        redirect('contact.php');
    }
}

$email   = setting('contact_email');
$phone   = setting('contact_phone');
$address = setting('contact_address');
$socials = [
    'instagram' => ['Instagram', setting('social_instagram')],
    'facebook'  => ['Facebook', setting('social_facebook')],
    'youtube'   => ['YouTube', setting('social_youtube')],
    'linkedin'  => ['LinkedIn', setting('social_linkedin')],
    'tiktok'    => ['TikTok', setting('social_tiktok')],
];

$pageTitle = 'Contact';
$pageDesc  = 'Contactez le Club Media ISET Gafsa.';
$activeNav = 'contact';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane"><a href="<?= e(url('index.php')) ?>">Accueil</a><span aria-hidden="true">/</span><span>Contact</span></nav>
        <h1>Parlons de <span class="text-grad">votre projet</span></h1>
        <p>Une question, une collaboration, l'envie de rejoindre le club ? Écrivez-nous.</p>
    </div>
</section>

<section class="section" style="padding-top:56px">
    <div class="container contact-layout">
        <div class="card contact-card">
            <h2 style="font-size:1.6rem">Envoyer un message</h2>
            <?php if (!empty($errors['form'])): ?><div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
            <form class="form" method="post" action="<?= e(url('contact.php')) ?>" novalidate data-once>
                <?= csrf_field() ?>
                <div class="hp-field" aria-hidden="true"><label for="website">Ne pas remplir</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="form-row">
                    <div class="field">
                        <label for="full_name">Nom complet <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= val('full_name') ?>" required maxlength="120" autocomplete="name"<?= isset($errors['full_name']) ? ' aria-invalid="true" aria-describedby="err-full_name"' : '' ?>>
                        <?= field_error($errors, 'full_name') ?>
                    </div>
                    <div class="field">
                        <label for="email">E-mail <span class="req" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" value="<?= val('email') ?>" required maxlength="150" autocomplete="email"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                        <?= field_error($errors, 'email') ?>
                    </div>
                </div>
                <div class="field">
                    <label for="subject">Sujet <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="subject" name="subject" value="<?= val('subject') ?>" required maxlength="180"<?= isset($errors['subject']) ? ' aria-invalid="true" aria-describedby="err-subject"' : '' ?>>
                    <?= field_error($errors, 'subject') ?>
                </div>
                <div class="field">
                    <label for="message">Message <span class="req" aria-hidden="true">*</span></label>
                    <textarea id="message" name="message" required maxlength="3000"<?= isset($errors['message']) ? ' aria-invalid="true" aria-describedby="err-message"' : '' ?>><?= val('message') ?></textarea>
                    <?= field_error($errors, 'message') ?>
                </div>
                <button class="btn btn-primary btn-lg" type="submit"><i data-lucide="send" aria-hidden="true"></i> Envoyer le message</button>
            </form>
        </div>

        <aside class="card contact-card">
            <h2 style="font-size:1.6rem">Nos coordonnées</h2>
            <ul class="contact-info">
                <?php if ($address !== ''): ?><li><i data-lucide="map-pin" aria-hidden="true"></i><div><small>Adresse</small><?= e($address) ?></div></li><?php endif; ?>
                <?php if ($email !== ''): ?><li><i data-lucide="mail" aria-hidden="true"></i><div><small>E-mail</small><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></li><?php endif; ?>
                <?php if ($phone !== ''): ?><li><i data-lucide="phone" aria-hidden="true"></i><div><small>Téléphone</small><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>"><?= e($phone) ?></a></div></li><?php endif; ?>
                <?php if ($email === '' && $phone === ''): ?><li><i data-lucide="info" aria-hidden="true"></i><div><small>Info</small>Utilisez le formulaire : nous vous répondrons rapidement.</div></li><?php endif; ?>
            </ul>
            <div class="socials" aria-label="Réseaux sociaux">
                <?php foreach ($socials as $key => [$label, $link]): ?>
                    <?php if ($link !== '' && valid_url($link)): ?>
                        <a class="socials__link" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= social_icon($key) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
