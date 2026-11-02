<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Fonctions utilitaires communes (compatibles PHP 8.0+).
 */
declare(strict_types=1);

/* ================================================================== */
/* Échappement, URLs, redirections                                      */
/* ================================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function upload_url(string $subdir, ?string $file): string
{
    return url('uploads/' . $subdir . '/' . rawurlencode((string) $file));
}

/** Redirection interne uniquement (le chemin est toujours préfixé par le chemin de base). */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

function is_localhost(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

/** Adresse IP du client. X-Forwarded-For n'est volontairement pas utilisé (falsifiable). */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: blob: https://i.ytimg.com; "
        . "media-src 'self' blob:; "
        . "frame-src https://www.youtube-nocookie.com; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'"
    );
}

/* ================================================================== */
/* Messages flash et saisie                                             */
/* ================================================================== */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $type  = in_array($f['type'], ['success', 'error', 'info', 'warning'], true) ? $f['type'] : 'info';
        $role  = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="alert alert--' . $type . '" role="' . $role . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/** Valeur POST nettoyée (chaîne). */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Valeur GET nettoyée (chaîne). */
function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Valeur échappée pour un champ de formulaire (POST prioritaire, sinon défaut). */
function val(string $key, $default = ''): string
{
    if (isset($_POST[$key]) && is_string($_POST[$key])) {
        return e($_POST[$key]);
    }
    return e((string) $default);
}

function field_error(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>';
}

/* ================================================================== */
/* Paramètres du site                                                   */
/* ================================================================== */

function settings_cache(?array $set = null): array
{
    static $cache = null;
    if ($set !== null) {
        $cache = $set;
    }
    if ($cache === null) {
        $cache = db()->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_cache();
    return isset($all[$key]) && $all[$key] !== null && $all[$key] !== '' ? (string) $all[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare(
        'INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $st->execute([$key, $value]);
    $all       = settings_cache();
    $all[$key] = $value;
    settings_cache($all);
}

/* ================================================================== */
/* Dates et formats français                                            */
/* ================================================================== */

function fr_date(string $date, bool $short = false): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $days   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $d = (int) date('j', $ts);
    $m = $months[(int) date('n', $ts) - 1];
    $y = date('Y', $ts);
    if ($short) {
        return $d . ' ' . mb_substr($m, 0, 4) . ($m === 'mai' || $m === 'juin' ? '' : '.') . ' ' . $y;
    }
    return $days[(int) date('w', $ts)] . ' ' . ($d === 1 ? '1er' : (string) $d) . ' ' . $m . ' ' . $y;
}

function fr_datetime(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts === false ? $datetime : date('d/m/Y à H\hi', $ts);
}

function fr_time(string $time): string
{
    return str_replace(':', 'h', substr($time, 0, 5));
}

function format_money($amount): string
{
    $s = number_format((float) $amount, 3, ',', ' ');
    $s = rtrim(rtrim($s, '0'), ',');
    return $s . ' TND';
}

function truncate(string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $len - 1)) . '…';
}

/** Texte brut → paragraphes HTML sûrs. */
function paragraphs(string $text): string
{
    $parts = preg_split('/\R{2,}/', trim($text)) ?: [];
    $out   = '';
    foreach ($parts as $p) {
        if (trim($p) !== '') {
            $out .= '<p>' . nl2br(e(trim($p)), false) . '</p>';
        }
    }
    return $out;
}

/* ================================================================== */
/* Événements                                                           */
/* ================================================================== */

const EVENT_STATUSES = [
    'upcoming'  => 'À venir',
    'ongoing'   => 'En cours',
    'finished'  => 'Terminé',
    'cancelled' => 'Annulé',
];

const RESERVATION_STATUSES = [
    'confirmed'       => 'Confirmée',
    'pending_payment' => 'En attente de paiement',
    'cancelled'       => 'Annulée',
];

const TICKET_STATUSES = [
    'valid'     => 'Valide',
    'used'      => 'Déjà utilisé',
    'cancelled' => 'Annulé',
    'pending'   => 'Non confirmé',
];

/** Met à jour les statuts automatiques (à venir / en cours / terminé) ; "Annulé" n'est jamais modifié. */
function sync_event_statuses(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "UPDATE events SET status = CASE
            WHEN TIMESTAMP(end_date, end_time) < NOW() THEN 'finished'
            WHEN TIMESTAMP(start_date, start_time) <= NOW() THEN 'ongoing'
            ELSE 'upcoming' END
         WHERE status <> 'cancelled'"
    );
}

function event_status_label(string $status): string
{
    return EVENT_STATUSES[$status] ?? $status;
}

function event_cover_url(array $ev): string
{
    if (!empty($ev['cover_image'])) {
        return upload_url('events', $ev['cover_image']);
    }
    return asset('images/event-placeholder.svg');
}

function event_price_label(array $ev): string
{
    if (($ev['ticket_type'] ?? 'free') === 'free' || (float) ($ev['ticket_price'] ?? 0) <= 0) {
        return 'Gratuit';
    }
    return format_money($ev['ticket_price']);
}

function event_is_bookable(array $ev): bool
{
    return !empty($ev['is_published'])
        && in_array($ev['status'], ['upcoming', 'ongoing'], true)
        && (int) $ev['available_seats'] > 0;
}

function event_when_label(array $ev): string
{
    if ($ev['start_date'] === $ev['end_date']) {
        return fr_date($ev['start_date']) . ' · ' . fr_time($ev['start_time']) . ' – ' . fr_time($ev['end_time']);
    }
    return 'Du ' . fr_date($ev['start_date']) . ' (' . fr_time($ev['start_time']) . ') au '
        . fr_date($ev['end_date']) . ' (' . fr_time($ev['end_time']) . ')';
}

function render_status_badge(string $status): string
{
    return '<span class="badge badge--' . e($status) . '">' . e(event_status_label($status)) . '</span>';
}

function render_event_card(array $ev, int $index = 0): string
{
    $id      = (int) $ev['id'];
    $detail  = url('event.php?id=' . $id);
    $bookable = event_is_bookable($ev);
    $delay   = ($index % 3) * 100;

    $btn = $bookable
        ? '<a class="btn btn-primary btn-sm" href="' . e(url('reserve.php?event=' . $id)) . '">Réserver</a>'
        : '<span class="btn btn-disabled btn-sm" aria-disabled="true">'
          . ($ev['status'] === 'cancelled' ? 'Annulé' : ($ev['status'] === 'finished' ? 'Terminé' : 'Complet')) . '</span>';

    $category = !empty($ev['category_name']) ? '<span class="badge badge--category">' . e($ev['category_name']) . '</span>' : '';
    $left     = ($ev['status'] === 'upcoming' || $ev['status'] === 'ongoing') && (int) $ev['available_seats'] > 0 && (int) $ev['available_seats'] <= 10
        ? '<span class="event-card__hint">Plus que ' . (int) $ev['available_seats'] . ' place(s)</span>' : '';

    return '<article class="event-card card" data-aos="fade-up" data-aos-delay="' . $delay . '">'
        . '<a class="event-card__media" href="' . e($detail) . '" tabindex="-1" aria-hidden="true">'
        . '<img src="' . e(event_cover_url($ev)) . '" alt="" loading="lazy" width="640" height="400">'
        . '<div class="event-card__badges">' . render_status_badge($ev['status']) . $category . '</div>'
        . '</a>'
        . '<div class="event-card__body">'
        . '<h3 class="event-card__title"><a href="' . e($detail) . '">' . e($ev['title']) . '</a></h3>'
        . '<ul class="meta-list">'
        . '<li><i data-lucide="calendar" aria-hidden="true"></i><span>' . e(fr_date($ev['start_date'])) . '</span></li>'
        . '<li><i data-lucide="clock" aria-hidden="true"></i><span>' . e(fr_time($ev['start_time'])) . ' – ' . e(fr_time($ev['end_time'])) . '</span></li>'
        . '<li><i data-lucide="map-pin" aria-hidden="true"></i><span>' . e($ev['location']) . '</span></li>'
        . '</ul>'
        . '<div class="event-card__foot">'
        . '<div><span class="price">' . e(event_price_label($ev)) . '</span>' . $left . '</div>'
        . $btn
        . '</div></div></article>';
}

function fetch_event(int $id, bool $publishedOnly = true): ?array
{
    $sql = 'SELECT e.*, c.name AS category_name FROM events e
            LEFT JOIN event_categories c ON c.id = e.category_id WHERE e.id = ?';
    if ($publishedOnly) {
        $sql .= ' AND e.is_published = 1';
    }
    $st = db()->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/* ================================================================== */
/* Pagination                                                           */
/* ================================================================== */

function paginate(int $total, int $perPage, ?int $page = null): array
{
    $page  = $page ?? max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function render_pagination(array $p, array $query = []): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    unset($query['page']);
    $query = array_filter($query, static fn($v) => $v !== '' && $v !== null);
    $link  = static function (int $n) use ($query): string {
        $q = $query;
        if ($n > 1) {
            $q['page'] = $n;
        }
        return '?' . http_build_query($q);
    };

    $html = '<nav class="pagination" aria-label="Pagination"><ul>';
    if ($p['page'] > 1) {
        $html .= '<li><a href="' . e($link($p['page'] - 1)) . '" rel="prev" aria-label="Page précédente">‹</a></li>';
    }
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    if ($start > 1) {
        $html .= '<li><a href="' . e($link(1)) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="pagination__gap" aria-hidden="true">…</li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $p['page']
            ? '<li><span class="is-current" aria-current="page">' . $i . '</span></li>'
            : '<li><a href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) {
            $html .= '<li class="pagination__gap" aria-hidden="true">…</li>';
        }
        $html .= '<li><a href="' . e($link($p['pages'])) . '">' . $p['pages'] . '</a></li>';
    }
    if ($p['page'] < $p['pages']) {
        $html .= '<li><a href="' . e($link($p['page'] + 1)) . '" rel="next" aria-label="Page suivante">›</a></li>';
    }
    return $html . '</ul></nav>';
}

/* ================================================================== */
/* Codes aléatoires, validations                                        */
/* ================================================================== */

/** Code lisible sans caractères ambigus (0/O, 1/I). */
function random_code(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max      = strlen($alphabet) - 1;
    $code     = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function valid_email(string $email): bool
{
    return mb_strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $phone): bool
{
    if (!preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
        return false;
    }
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    return strlen($digits) >= 8 && strlen($digits) <= 15;
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
}

/** Accepte uniquement des URL http(s). */
function valid_url(string $url): bool
{
    if (mb_strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

/** Extrait l'identifiant d'une vidéo YouTube (11 caractères) ou null. */
function youtube_id(string $input): ?string
{
    $input = trim($input);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
        return $input;
    }
    if (!preg_match('#^https?://#i', $input)) {
        return null;
    }
    $parts = parse_url($input);
    $host  = strtolower($parts['host'] ?? '');
    $host  = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
    $path  = $parts['path'] ?? '';
    $id    = null;
    if ($host === 'youtu.be') {
        $id = ltrim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        if (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
            $id = $m[2];
        } elseif ($path === '/watch' && !empty($parts['query'])) {
            parse_str($parts['query'], $q);
            $id = $q['v'] ?? null;
        }
    }
    $id = is_string($id) ? substr($id, 0, 11) : null;
    return ($id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) ? $id : null;
}

/* ================================================================== */
/* Limitation de débit (rate limiting)                                  */
/* ================================================================== */

function rate_ip_hash(): string
{
    return hash('sha256', client_ip() . '|' . DB_NAME);
}

function rate_limited(string $bucket, int $max, int $windowSeconds): bool
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
    );
    $st->execute([$bucket, rate_ip_hash(), $windowSeconds]);
    return (int) $st->fetchColumn() >= $max;
}

function rate_limit_hit(string $bucket): void
{
    $st = db()->prepare('INSERT INTO rate_limits (bucket, ip_hash) VALUES (?, ?)');
    $st->execute([$bucket, rate_ip_hash()]);
    if (random_int(1, 40) === 1) {
        db()->exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 2 DAY)');
    }
}

/* ================================================================== */
/* Journal d'activité                                                   */
/* ================================================================== */

function log_activity(string $action, string $details = '', ?int $adminId = null): void
{
    try {
        $adminId = $adminId ?? ($_SESSION['admin_id'] ?? null);
        $st = db()->prepare('INSERT INTO admin_activity_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $st->execute([$adminId, mb_substr($action, 0, 100), mb_substr($details, 0, 500), client_ip()]);
    } catch (Throwable $e) {
        error_log('[CLUB-MEDIA] log_activity: ' . $e->getMessage());
    }
}

/* ================================================================== */
/* Statistiques publiques                                               */
/* ================================================================== */

function public_stats(): array
{
    $pdo = db();
    return [
        'events'       => (int) $pdo->query('SELECT COUNT(*) FROM events WHERE is_published = 1')->fetchColumn(),
        'members'      => (int) $pdo->query('SELECT COUNT(*) FROM team_members WHERE is_active = 1')->fetchColumn(),
        'media'        => (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn()
                        + (int) $pdo->query('SELECT COUNT(*) FROM videos WHERE is_published = 1')->fetchColumn(),
        'participants' => (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('valid','used')")->fetchColumn(),
    ];
}

/* ================================================================== */
/* CSV                                                                  */
/* ================================================================== */

/** Neutralise les formules Excel (CSV injection). */
function csv_cell($value): string
{
    $s = (string) $value;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $s = "'" . $s;
    }
    return $s;
}

/* ================================================================== */
/* Page d'erreur / aide à l'installation                                */
/* ================================================================== */

function render_error_page(Throwable $e): void
{
    $kind = 'generic';
    $msg  = $e->getMessage();
    if ($e instanceof PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 0 && is_numeric((string) $e->getCode())) {
            $code = (int) $e->getCode();
        }
        if ($code === 1049 || stripos($msg, 'unknown database') !== false) {
            $kind = 'db_missing';
        } elseif ($code === 1045 || stripos($msg, 'access denied') !== false) {
            $kind = 'db_denied';
        } elseif (in_array($code, [2002, 2003, 2006], true) || stripos($msg, 'connection refused') !== false
            || stripos($msg, 'no such file') !== false || stripos($msg, 'getaddrinfo') !== false) {
            $kind = 'db_down';
        } elseif ($code === 1146 || stripos($msg, "doesn't exist") !== false || stripos($msg, 'base table or view not found') !== false) {
            $kind = 'tables_missing';
        } elseif ($code === 1054 || stripos($msg, 'unknown column') !== false) {
            $kind = 'schema_outdated';
        } else {
            $kind = 'db_other';
        }
    }

    $local = is_localhost();
    $titles = [
        'db_missing'      => 'La base de données est introuvable',
        'db_denied'       => 'Accès refusé à MySQL',
        'db_down'         => 'Impossible de joindre MySQL',
        'tables_missing'  => 'Les tables de la base sont absentes',
        'schema_outdated' => 'La structure de la base est incomplète',
        'db_other'        => 'Erreur de base de données',
        'generic'         => 'Une erreur est survenue',
    ];
    $steps = [
        'db_missing'      => ['Ouvrez phpMyAdmin (http://localhost/phpmyadmin).', 'Cliquez sur « Importer » puis choisissez le fichier database.sql du projet : il crée la base « club_media_db ».', 'Si vous utilisez un autre nom de base, modifiez DB_NAME dans config/database.php.'],
        'db_denied'       => ['Vérifiez DB_USER et DB_PASS dans config/database.php.', 'Sous XAMPP, l\'utilisateur est « root » avec un mot de passe vide par défaut.', 'Sur un hébergement, utilisez les identifiants fournis par votre hébergeur.'],
        'db_down'         => ['Ouvrez le panneau de contrôle XAMPP et cliquez sur « Start » en face de MySQL.', 'Vérifiez DB_HOST et DB_PORT dans config/database.php (3306 par défaut).', 'Si le port 3306 est occupé, XAMPP peut utiliser un autre port : reportez-le dans DB_PORT.'],
        'tables_missing'  => ['Ouvrez phpMyAdmin et sélectionnez la base du club.', 'Importez le fichier database.sql (onglet « Importer »).', 'Rechargez ensuite cette page.'],
        'schema_outdated' => ['La base ne correspond pas à la version du site.', 'Réimportez database.sql dans une base vide (attention : cela efface les données de test).'],
        'db_other'        => ['Consultez le fichier logs/app.log pour le détail technique.', 'Vérifiez que database.sql a bien été importé sans erreur.'],
        'generic'         => ['Réessayez dans quelques instants.', 'Si le problème persiste, contactez l\'administrateur du site.'],
    ];

    $title = $titles[$kind];
    $isInstall = $kind !== 'generic';
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' — Club Media ISET Gafsa</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#09090F;color:#F8FAFC;font:16px/1.6 system-ui,Segoe UI,Arial,sans-serif;padding:24px}'
        . '.box{max-width:680px;width:100%;background:#1B1B2B;border:1px solid #29293D;border-radius:20px;padding:32px;box-shadow:0 20px 60px rgba(139,92,246,.18)}'
        . 'h1{margin:0 0 8px;font-size:1.5rem}.tag{color:#A78BFA;font-weight:700;letter-spacing:.08em;font-size:.8rem}p{color:#A1A1AA}'
        . 'ol{color:#F8FAFC;padding-left:1.2em}li{margin:.4em 0}code{background:#12121E;border:1px solid #29293D;border-radius:6px;padding:.1em .4em;color:#A78BFA}'
        . 'a{color:#A78BFA}</style></head><body><div class="box" role="alert"><div class="tag">CLUB MEDIA — ISET GAFSA</div><h1>' . e($title) . '</h1>';

    if ($isInstall) {
        echo '<p>Le site ne peut pas utiliser la base de données. Voici comment corriger le problème :</p><ol>';
        foreach ($steps[$kind] as $s) {
            echo '<li>' . e($s) . '</li>';
        }
        echo '</ol>';
        if ($local) {
            echo '<p>Configuration lue : hôte <code>' . e(DB_HOST) . ':' . (int) DB_PORT . '</code>, base <code>' . e(DB_NAME)
                . '</code>, utilisateur <code>' . e(DB_USER) . '</code>.</p>';
            if (APP_DEBUG) {
                echo '<p>Détail technique : <code>' . e($msg) . '</code></p>';
            }
        }
    } else {
        echo '<ol>';
        foreach ($steps['generic'] as $s) {
            echo '<li>' . e($s) . '</li>';
        }
        echo '</ol>';
        if ($local && APP_DEBUG) {
            echo '<p>Détail : <code>' . e($msg) . '</code></p>';
        }
    }
    echo '</div></body></html>';
}

/* ================================================================== */
/* Icônes de réseaux sociaux (SVG intégrés, sans dépendance)            */
/* ================================================================== */

function social_icon(string $name): string
{
    $svg = [
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>',
        'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v7h4v-7h3l1-4h-4V8z"/>',
        'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor" stroke="none"/>',
        'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v6M8 7.5v.01M12 16v-6m0 3a3 3 0 0 1 6 0v3"/>',
        'tiktok'    => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.4 2.6 2 4.2 5 4.5"/>',
    ];
    $inner = $svg[$name] ?? '';
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/* ================================================================== */
/* Médias : photos & vidéos                                             */
/* ================================================================== */

function photo_url(array $p): string
{
    return upload_url('photos', $p['filename']);
}

function photo_thumb_url(array $p): string
{
    $thumb = UPLOAD_PATH . '/photos/thumbs/' . $p['filename'];
    return is_file($thumb) ? upload_url('photos/thumbs', $p['filename']) : photo_url($p);
}

function video_thumb_url(array $v): ?string
{
    if (!empty($v['thumbnail'])) {
        return upload_url('thumbs', $v['thumbnail']);
    }
    if ($v['source_type'] === 'youtube' && !empty($v['youtube_id'])) {
        return 'https://i.ytimg.com/vi/' . rawurlencode($v['youtube_id']) . '/hqdefault.jpg';
    }
    return null;
}

/** Carte vidéo : un bouton qui ouvre le lecteur modal (voir main.js). */
function render_video_card(array $v, int $index = 0, bool $animate = true): string
{
    $thumb  = video_thumb_url($v);
    $source = (string) $v['source_type'];
    $src    = $source === 'youtube' ? (string) $v['youtube_id'] : upload_url('videos', $v['video_file']);
    $aos    = $animate ? ' data-aos="fade-up" data-aos-delay="' . (($index % 3) * 100) . '"' : '';

    $img = $thumb !== null
        ? '<img src="' . e($thumb) . '" alt="" loading="lazy" width="640" height="360">'
        : '';
    return '<button type="button" class="video-card card" data-video data-source="' . e($source) . '" data-src="' . e($src) . '"'
        . ' data-title="' . e($v['title']) . '" data-desc="' . e((string) ($v['description'] ?? '')) . '"'
        . ($thumb !== null && $source === 'upload' ? ' data-poster="' . e($thumb) . '"' : '')
        . ' aria-label="Lire la vidéo : ' . e($v['title']) . '"' . $aos . '>'
        . '<span class="video-card__thumb">' . $img
        . '<span class="video-card__play"><span><i data-lucide="play" aria-hidden="true"></i></span></span></span>'
        . '<span class="video-card__body"><strong class="video-card__title">' . e($v['title']) . '</strong>'
        . '<span class="video-card__meta">' . e(fr_date(substr((string) $v['created_at'], 0, 10), true))
        . (!empty($v['event_title']) ? ' · ' . e($v['event_title']) : '') . '</span></span></button>';
}
