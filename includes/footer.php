<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Pied de page commun + chargement des scripts.
 * Variables attendues (facultatives) : $needQr (bool), $pageScripts (liste de fichiers dans assets/js/)
 */
declare(strict_types=1);

$siteName = setting('site_name', APP_NAME);
$email    = setting('contact_email');
$phone    = setting('contact_phone');
$address  = setting('contact_address');
$socials  = [
    'instagram' => ['Instagram', setting('social_instagram')],
    'facebook'  => ['Facebook',  setting('social_facebook')],
    'youtube'   => ['YouTube',   setting('social_youtube')],
    'linkedin'  => ['LinkedIn',  setting('social_linkedin')],
    'tiktok'    => ['TikTok',    setting('social_tiktok')],
];
?>
</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div class="site-footer__about">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <img class="brand__logo" src="<?= e(setting('logo') !== '' ? upload_url('site', setting('logo')) : asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
                <span class="brand__text"><strong>CLUB MEDIA</strong><small>ISET GAFSA</small></span>
            </a>
            <p class="site-footer__tag"><?= e(setting('tagline', 'Create. Capture. Inspire.')) ?></p>
            <p><?= e(truncate(setting('club_description'), 170)) ?></p>
        </div>

        <nav aria-label="Liens rapides">
            <h2 class="site-footer__title">Explorer</h2>
            <ul class="site-footer__links">
                <li><a href="<?= e(url('events.php')) ?>">Événements</a></li>
                <li><a href="<?= e(url('gallery.php')) ?>">Galerie photos</a></li>
                <li><a href="<?= e(url('videos.php')) ?>">Galerie vidéos</a></li>
                <li><a href="<?= e(url('team.php')) ?>">Notre équipe</a></li>
                <li><a href="<?= e(url('about.php')) ?>">À propos</a></li>
                <li><a href="<?= e(url('my-reservations.php')) ?>">Mes réservations</a></li>
            </ul>
        </nav>

        <div>
            <h2 class="site-footer__title">Contact</h2>
            <ul class="site-footer__links site-footer__contact">
                <?php if ($address !== ''): ?><li><i data-lucide="map-pin" aria-hidden="true"></i><span><?= e($address) ?></span></li><?php endif; ?>
                <?php if ($email !== ''): ?><li><i data-lucide="mail" aria-hidden="true"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                <?php if ($phone !== ''): ?><li><i data-lucide="phone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>"><?= e($phone) ?></a></li><?php endif; ?>
                <li><i data-lucide="message-square" aria-hidden="true"></i><a href="<?= e(url('contact.php')) ?>">Formulaire de contact</a></li>
            </ul>
            <div class="socials" aria-label="Réseaux sociaux">
                <?php foreach ($socials as $key => [$label, $link]): ?>
                    <?php if ($link !== '' && valid_url($link)): ?>
                        <a class="socials__link" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= social_icon($key) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <p>© <?= date('Y') ?> <?= e($siteName) ?>. Tous droits réservés.</p>
        <p><a href="<?= e(url('admin.php')) ?>">Espace administration</a></p>
    </div>
</footer>

<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<?php if (!empty($needQr)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('js/animations.js')) ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $__js): ?>
<script src="<?= e(asset('js/' . $__js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
