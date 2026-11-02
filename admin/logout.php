<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';

if (!is_post()) {
    redirect('admin.php');
}
csrf_require();
if (admin_current() !== null) {
    log_activity('Déconnexion', '');
}
admin_logout();
flash('info', 'Vous avez été déconnecté.');
redirect('admin.php');
