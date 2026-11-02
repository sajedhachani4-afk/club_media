<?php
/** Messages du formulaire de contact : lecture, marquage lu/non lu, suppression */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$admin = require_admin();
$pdo   = db();

$filter = get('filter') === 'unread' ? 'unread' : 'all';

if (is_post()) {
    csrf_require();
    $action = post('action');
    $id     = (int) post('id', '0');

    if ($action === 'mark_all_read') {
        $pdo->exec('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        flash('success', 'Tous les messages sont marqués comme lus.');
    } elseif ($id > 0 && $action === 'toggle_read') {
        $pdo->prepare('UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    } elseif ($id > 0 && $action === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        log_activity('Message supprimé', '#' . $id);
        flash('success', 'Message supprimé.');
        admin_back('messages.php', ['filter' => $filter === 'unread' ? 'unread' : '']);
    }
    admin_back('messages.php', ['filter' => $filter === 'unread' ? 'unread' : '']);
}

/* ------------------------------------------------------------ Lecture d'un message */
$viewId = (int) get('view', '0');
if ($viewId > 0) {
    $s = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $s->execute([$viewId]);
    $msg = $s->fetch();
    if (!$msg) {
        flash('error', 'Message introuvable.');
        admin_back('messages.php');
    }
    // L'ouverture d'un message le marque comme lu.
    if (!(int) $msg['is_read']) {
        $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$viewId]);
        $msg['is_read'] = 1;
    }
    admin_header('Message', 'messages', $admin);
    $mailSubject = 'Re: ' . $msg['subject'];
    ?>
    <article class="panel">
        <div class="panel__head">
            <h2><?= e($msg['subject']) ?></h2>
            <span class="muted"><?= e(fr_datetime((string) $msg['created_at'])) ?></span>
        </div>
        <p><strong><?= e($msg['full_name']) ?></strong> — <a href="mailto:<?= e($msg['email']) ?>?subject=<?= e(rawurlencode($mailSubject)) ?>"><?= e($msg['email']) ?></a></p>
        <div style="white-space:pre-wrap;line-height:1.7;margin:18px 0"><?= e($msg['message']) ?></div>
        <div class="form-actions">
            <a class="btn btn-primary" href="mailto:<?= e($msg['email']) ?>?subject=<?= e(rawurlencode($mailSubject)) ?>">Répondre par e-mail</a>
            <form method="post" action="<?= e(url('admin/messages.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_read"><input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                <button class="btn btn-ghost" type="submit">Marquer comme non lu</button></form>
            <form method="post" action="<?= e(url('admin/messages.php')) ?>" data-confirm="Supprimer ce message ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                <button class="btn btn-danger" type="submit">Supprimer</button></form>
            <a class="btn btn-ghost" href="<?= e(url('admin/messages.php')) ?>">← Retour</a>
        </div>
    </article>
    <?php
    admin_footer();
    exit;
}

/* ------------------------------------------------------------------------ Liste */
$where = $filter === 'unread' ? 'WHERE is_read = 0' : '';
$cnt   = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages $where")->fetchColumn();
$pg    = paginate($cnt, 20);
$rows  = $pdo->query(
    "SELECT * FROM contact_messages $where ORDER BY created_at DESC, id DESC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']
)->fetchAll();

admin_header('Messages', 'messages', $admin);
?>
<div class="toolbar">
    <a class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/messages.php')) ?>">Tous</a>
    <a class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('admin/messages.php?filter=unread')) ?>">Non lus</a>
    <span class="toolbar__spacer"></span>
    <form method="post" action="<?= e(url('admin/messages.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read">
        <button class="btn btn-ghost btn-sm" type="submit">Tout marquer comme lu</button></form>
</div>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Statut</th><th>De</th><th>Sujet</th><th>Reçu le</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): $unread = !(int) $m['is_read']; ?>
        <tr>
            <td data-label="Statut"><span class="badge badge--<?= $unread ? 'pending' : 'finished' ?>"><?= $unread ? 'Non lu' : 'Lu' ?></span></td>
            <td data-label="De"><strong><?= e($m['full_name']) ?></strong><br><small class="muted"><?= e($m['email']) ?></small></td>
            <td data-label="Sujet"><?= $unread ? '<strong>' . e($m['subject']) . '</strong>' : e($m['subject']) ?></td>
            <td data-label="Reçu le"><?= e(fr_datetime((string) $m['created_at'])) ?></td>
            <td class="cell-actions"><div class="actions">
                <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/messages.php?view=' . (int) $m['id'])) ?>">Lire</a>
                <form method="post" action="<?= e(url('admin/messages.php')) ?>" data-confirm="Supprimer ce message ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                    <button class="btn btn-danger btn-xs" type="submit">Supprimer</button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:40px">Aucun message.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?= render_pagination($pg, ['filter' => $filter === 'unread' ? 'unread' : '']) ?>
<?php admin_footer(); ?>
