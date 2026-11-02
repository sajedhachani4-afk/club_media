<?php
/**
 * CLUB MEDIA — ISET GAFSA
 * Envoi sécurisé de fichiers : images (JPG/PNG/WebP, ré-encodées avec GD)
 * et vidéos (MP4/WebM, signature vérifiée). Les noms de fichiers sont
 * générés aléatoirement ; l'extension vient du type détecté, jamais du client.
 */
declare(strict_types=1);

const IMAGE_MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Le fichier dépasse la taille maximale autorisée par le serveur (' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'Le fichier n\'a été envoyé que partiellement. Réessayez.';
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier sélectionné.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Le serveur n\'a pas pu enregistrer le fichier (dossier temporaire ou droits d\'écriture).';
        default:
            return 'Erreur inconnue pendant l\'envoi du fichier.';
    }
}

/** Taille maximale effective d'un envoi vidéo (min entre la config du site et celle de PHP). */
function effective_upload_limit(int $siteLimit): int
{
    $toBytes = static function (string $v): int {
        $v = trim($v);
        if ($v === '') {
            return PHP_INT_MAX;
        }
        $n    = (int) $v;
        $unit = strtolower(substr($v, -1));
        if ($unit === 'g') {
            return $n * 1024 * 1024 * 1024;
        }
        if ($unit === 'm') {
            return $n * 1024 * 1024;
        }
        if ($unit === 'k') {
            return $n * 1024;
        }
        return $n;
    };
    $upload = $toBytes((string) ini_get('upload_max_filesize'));
    $post   = $toBytes((string) ini_get('post_max_size'));
    return min($siteLimit, $upload, $post);
}

function human_size(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 1) . ' Go';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576) . ' Mo';
    }
    return round($bytes / 1024) . ' Ko';
}

/** Retourne true si un fichier a réellement été choisi dans ce champ. */
function has_upload(?array $file): bool
{
    return $file !== null && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/** Transforme $_FILES['x'] (multiple) en liste de fichiers individuels. */
function normalize_files(?array $files): array
{
    if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
        return $files !== null && isset($files['name']) ? [$files] : [];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[] = [
            'name'     => $name,
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }
    return $out;
}

function ensure_upload_dir(string $subdir): string
{
    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de créer le dossier d\'envoi « ' . $subdir . ' ».');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Le dossier « uploads/' . $subdir . ' » n\'est pas accessible en écriture.');
    }
    return $dir;
}

function detect_mime(string $path): string
{
    $f    = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $f ? (string) finfo_file($f, $path) : '';
    if ($f) {
        finfo_close($f);
    }
    return $mime;
}

/**
 * Valide, redimensionne et enregistre une image.
 * @return array{filename:string,width:int,height:int}
 * @throws RuntimeException avec un message prêt à afficher
 */
function save_image(array $file, string $subdir, int $maxWidth = 1920, bool $makeThumb = false, int $thumbWidth = 640): array
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('L\'extension PHP GD est requise (activez extension=gd dans php.ini).');
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp) && !(defined('CM_TESTING') && is_file($tmp))) {
        throw new RuntimeException('Fichier envoyé invalide.');
    }
    if ((int) $file['size'] > MAX_IMAGE_BYTES || filesize($tmp) > MAX_IMAGE_BYTES) {
        throw new RuntimeException('Image trop lourde (maximum ' . human_size(MAX_IMAGE_BYTES) . ').');
    }

    $mime = detect_mime($tmp);
    if (!isset(IMAGE_MIME_EXT[$mime])) {
        throw new RuntimeException('Format non accepté : utilisez JPG, PNG ou WebP.');
    }
    $info = @getimagesize($tmp);
    if ($info === false || $info[0] < 1 || $info[1] < 1) {
        throw new RuntimeException('Le fichier n\'est pas une image valide.');
    }
    [$w, $h] = $info;
    if ($w * $h > MAX_IMAGE_PIXELS) {
        throw new RuntimeException('Image trop grande en pixels (maximum ' . (int) (MAX_IMAGE_PIXELS / 1000000) . ' mégapixels).');
    }

    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($tmp);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($tmp);
            break;
        default:
            if (!function_exists('imagecreatefromwebp')) {
                throw new RuntimeException('Le format WebP n\'est pas pris en charge par ce serveur.');
            }
            $src = @imagecreatefromwebp($tmp);
    }
    if (!$src) {
        throw new RuntimeException('Impossible de lire cette image (fichier corrompu ?).');
    }

    // Orientation EXIF (photos de téléphone)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o    = (int) ($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle !== 0) {
            $rot = imagerotate($src, $angle, 0);
            if ($rot) {
                $src = $rot;
                $w   = imagesx($src);
                $h   = imagesy($src);
            }
        }
    }

    $ext  = IMAGE_MIME_EXT[$mime];
    $dir  = ensure_upload_dir($subdir);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;

    $main = image_resize_to_width($src, $w, $h, $maxWidth);
    write_image($main['img'], $dir . '/' . $name, $ext);

    if ($makeThumb) {
        $tdir  = ensure_upload_dir($subdir . '/thumbs');
        $thumb = image_resize_to_width($src, $w, $h, $thumbWidth);
        write_image($thumb['img'], $tdir . '/' . $name, $ext);
        if ($thumb['img'] !== $src) {
            imagedestroy($thumb['img']);
        }
    }
    $fw = $main['w'];
    $fh = $main['h'];
    if ($main['img'] !== $src) {
        imagedestroy($main['img']);
    }
    imagedestroy($src);

    return ['filename' => $name, 'width' => $fw, 'height' => $fh];
}

/** @return array{img:\GdImage|resource,w:int,h:int} */
function image_resize_to_width($src, int $w, int $h, int $maxWidth): array
{
    if ($w <= $maxWidth) {
        return ['img' => $src, 'w' => $w, 'h' => $h];
    }
    $nw  = $maxWidth;
    $nh  = max(1, (int) round($h * ($maxWidth / $w)));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return ['img' => $dst, 'w' => $nw, 'h' => $nh];
}

function write_image($img, string $path, string $ext): void
{
    $ok = false;
    if ($ext === 'jpg') {
        $ok = imagejpeg($img, $path, 82);
    } elseif ($ext === 'png') {
        imagesavealpha($img, true);
        $ok = imagepng($img, $path, 6);
    } else {
        $ok = imagewebp($img, $path, 80);
    }
    if (!$ok) {
        throw new RuntimeException('Impossible d\'enregistrer l\'image sur le serveur.');
    }
    @chmod($path, 0644);
}

/**
 * Valide et enregistre une vidéo MP4 ou WebM (taille, type MIME ET signature binaire).
 * @return string nom du fichier enregistré
 */
function save_video(array $file, string $subdir = 'videos'): string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp) && !(defined('CM_TESTING') && is_file($tmp))) {
        throw new RuntimeException('Fichier envoyé invalide.');
    }
    $size = (int) filesize($tmp);
    if ($size <= 0 || $size > MAX_VIDEO_BYTES) {
        throw new RuntimeException('Vidéo trop lourde (maximum ' . human_size(MAX_VIDEO_BYTES) . ').');
    }

    $fh   = fopen($tmp, 'rb');
    $head = $fh ? (string) fread($fh, 16) : '';
    if ($fh) {
        fclose($fh);
    }
    $isMp4  = strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp';
    $isWebm = strlen($head) >= 4 && substr($head, 0, 4) === "\x1A\x45\xDF\xA3";
    if (!$isMp4 && !$isWebm) {
        throw new RuntimeException('Format vidéo non accepté : utilisez un fichier MP4 ou WebM valide.');
    }
    $mime = detect_mime($tmp);
    if (!in_array($mime, ['video/mp4', 'video/webm', 'video/x-m4v', 'application/octet-stream'], true)) {
        throw new RuntimeException('Le type du fichier ne correspond pas à une vidéo MP4 ou WebM.');
    }

    $dir  = ensure_upload_dir($subdir);
    $name = bin2hex(random_bytes(12)) . ($isWebm ? '.webm' : '.mp4');
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        if (!(defined('CM_TESTING') && copy($tmp, $dir . '/' . $name))) {
            throw new RuntimeException('Impossible d\'enregistrer la vidéo sur le serveur.');
        }
    }
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

/** Supprime un fichier envoyé (et sa miniature éventuelle). Le nom est validé strictement. */
function delete_upload(string $subdir, ?string $filename): void
{
    if ($filename === null || !preg_match('/^[a-f0-9]{24}\.(jpg|png|webp|mp4|webm)$/', $filename)) {
        return;
    }
    foreach ([$subdir, $subdir . '/thumbs'] as $d) {
        $path = UPLOAD_PATH . '/' . $d . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
