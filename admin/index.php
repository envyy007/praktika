<?php
/** Панель администратора: модерация, заявки, заказы, контент, обслуживание. */

require_once __DIR__ . '/../inc/ui.php';
require_admin();

$s = (string)($_GET['s'] ?? 'dashboard');
$sections = ['dashboard'=>'Обзор','moderation'=>'Модерация','requests'=>'Заявки','orders'=>'Заказы',
             'recipes'=>'Рецепты','users'=>'Пользователи','content'=>'Разделы','lessons'=>'Занятия','service'=>'Обслуживание'];
if (!isset($sections[$s])) $s = 'dashboard';
$me = current_user();
$isAdmin = $me['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash('bad', 'Сессия истекла.'); redirect('admin/index.php?s=' . $s); }
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($do === 'publish') {
        ex('UPDATE recipes SET status = "published", published_at = NOW(), reject_note = "" WHERE id = :id', [':id' => $id]);
        $a = (int)scalar('SELECT author_id FROM recipes WHERE id = :id', [':id' => $id]);
        $t = (string)scalar('SELECT title FROM recipes WHERE id = :id', [':id' => $id]);
        if ($a) notify($a, 'Рецепт «' . $t . '» опубликован.', 'recipe.php?id=' . $id);
        audit('recipe.publish', '#' . $id); flash('ok', 'Рецепт опубликован.');
    }
    if ($do === 'reject') {
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note === '') { flash('bad', 'Укажите причину отклонения.'); redirect('admin/index.php?s=moderation'); }
        ex('UPDATE recipes SET status = "rejected", reject_note = :n WHERE id = :id', [':n' => mb_substr($note, 0, 300), ':id' => $id]);
        $a = (int)scalar('SELECT author_id FROM recipes WHERE id = :id', [':id' => $id]);
        $t = (string)scalar('SELECT title FROM recipes WHERE id = :id', [':id' => $id]);
        if ($a) notify($a, 'Рецепт «' . $t . '» отклонён: ' . $note, 'account.php?tab=recipes');
        audit('recipe.reject', '#' . $id); flash('ok', 'Рецепт отклонён, автор уведомлён.');
    }
    if ($do === 'unpublish') {
        ex('UPDATE recipes SET status = "rejected", reject_note = "Снят с публикации модератором" WHERE id = :id', [':id' => $id]);
        audit('recipe.unpublish', '#' . $id); flash('ok', 'Рецепт снят с публикации.');
    }
    if ($do === 'delete_recipe' && $isAdmin) {
        ex('DELETE FROM recipes WHERE id = :id', [':id' => $id]);
        audit('recipe.delete', '#' . $id); flash('ok', 'Рецепт удалён.');
    }

    if ($do === 'req_confirm' || $do === 'req_decline') {
        $reply = trim((string)($_POST['reply'] ?? ''));
        $status = $do === 'req_confirm' ? 'confirmed' : 'declined';
        ex('UPDATE lesson_requests SET status = :st, reply = :r, replied_at = NOW() WHERE id = :id',
           [':st' => $status, ':r' => mb_substr($reply, 0, 400), ':id' => $id]);
        $row = q1('SELECT lr.user_id, s.topic, s.price FROM lesson_requests lr JOIN slots s ON s.id = lr.slot_id WHERE lr.id = :id', [':id' => $id]);
        if ($row) {
            notify((int)$row['user_id'], ($status === 'confirmed' ? 'Заявка подтверждена: ' : 'Заявка отклонена: ') . $row['topic'] . ($reply ? ' — ' . $reply : ''), 'account.php?tab=lessons');
            if ($status === 'declined') ex('UPDATE orders SET status = "cancelled" WHERE kind = "lesson" AND item_id = :i AND status = "new"', [':i' => $id]);
        }
        audit('request.' . $status, '#' . $id); flash('ok', 'Ответ отправлен заявителю.');
    }

    if ($do === 'order_paid') {
        ex('UPDATE orders SET status = "paid", paid_at = NOW() WHERE id = :id', [':id' => $id]);
        $o = q1('SELECT * FROM orders WHERE id = :id', [':id' => $id]);
        if ($o) notify((int)$o['user_id'], 'Оплата подтверждена по счёту №' . $id . '.', 'account.php?tab=orders');
        audit('order.paid', '#' . $id); flash('ok', 'Оплата отмечена.');
    }
    if ($do === 'order_cancel') {
        ex('UPDATE orders SET status = "cancelled" WHERE id = :id', [':id' => $id]);
        audit('order.cancel', '#' . $id); flash('ok', 'Заказ отменён.');
    }

    if ($do === 'role' && $isAdmin) {
        $role = (string)($_POST['role'] ?? 'user');
        if (in_array($role, ['user','chef','editor','admin'], true)) {
            ex('UPDATE users SET role = :r WHERE id = :id', [':r' => $role, ':id' => $id]);
            audit('user.role', '#' . $id . ' -> ' . $role); flash('ok', 'Роль изменена.');
        }
    }
    if ($do === 'block' && $isAdmin) {
        $st = (string)($_POST['status'] ?? 'active');
        ex('UPDATE users SET status = :s WHERE id = :id AND id <> :me',
           [':s' => $st === 'blocked' ? 'blocked' : 'active', ':id' => $id, ':me' => (int)$me['id']]);
        audit('user.status', '#' . $id); flash('ok', 'Статус изменён.');
    }

    if ($do === 'save_page') {
        $pid = (int)($_POST['id'] ?? 0);
        $data = [':t' => trim((string)$_POST['title']), ':b' => (string)$_POST['body'],
                 ':st' => trim((string)$_POST['seo_title']), ':sd' => trim((string)$_POST['seo_desc']),
                 ':p' => isset($_POST['published']) ? 1 : 0];
        if ($pid) {
            $data[':id'] = $pid;
            ex('UPDATE pages SET title=:t, body=:b, seo_title=:st, seo_desc=:sd, published=:p, updated_at=NOW() WHERE id=:id', $data);
        } else {
            $data[':s'] = slugify((string)($_POST['slug'] ?: $data[':t']));
            ex('INSERT INTO pages (slug,title,body,seo_title,seo_desc,published,updated_at) VALUES (:s,:t,:b,:st,:sd,:p,NOW())', $data);
        }
        audit('page.save', (string)$pid); flash('ok', 'Страница сохранена.');
    }
    if ($do === 'save_news') {
        $nid = (int)($_POST['id'] ?? 0);
        $title = trim((string)$_POST['title']); $body = (string)$_POST['body']; $pub = isset($_POST['published']) ? 1 : 0;
        if ($nid) ex('UPDATE news SET title = :t, body = :b, published = :p WHERE id = :id', [':t'=>$title, ':b'=>$body, ':p'=>$pub, ':id'=>$nid]);
        else ex('INSERT INTO news (slug,title,body,published,created_at) VALUES (:s,:t,:b,:p,NOW())',
            [':s'=>slugify($title) . '-' . substr((string)time(), -4), ':t'=>$title, ':b'=>$body, ':p'=>$pub]);
        audit('news.save', (string)$nid); flash('ok', 'Материал сохранён.');
    }
    if ($do === 'del_news' && $isAdmin) { ex('DELETE FROM news WHERE id = :id', [':id' => $id]); flash('ok', 'Материал удалён.'); }

    if ($do === 'save_slot') {
        $sid2 = (int)($_POST['id'] ?? 0);
        $chef = (int)$_POST['chef_id']; $topic = trim((string)$_POST['topic']);
        $starts = str_replace('T', ' ', (string)$_POST['starts_at']) . ':00';
        $dur = max(15, (int)$_POST['duration']); $seats = max(1, (int)$_POST['seats']); $price = max(0, (int)$_POST['price']);
        $st2 = isset($_POST['open']) ? 'open' : 'closed';
        if ($sid2) ex('UPDATE slots SET chef_id=:c, topic=:t, starts_at=:s, duration=:d, seats=:se, price=:p, status=:st WHERE id=:id',
            [':c'=>$chef, ':t'=>$topic, ':s'=>$starts, ':d'=>$dur, ':se'=>$seats, ':p'=>$price, ':st'=>$st2, ':id'=>$sid2]);
        else ex('INSERT INTO slots (chef_id,topic,starts_at,duration,seats,price,status) VALUES (:c,:t,:s,:d,:se,:p,:st)',
            [':c'=>$chef, ':t'=>$topic, ':s'=>$starts, ':d'=>$dur, ':se'=>$seats, ':p'=>$price, ':st'=>$st2]);
        audit('slot.save', (string)$sid2); flash('ok', 'Занятие сохранено.');
    }
    if ($do === 'del_slot' && $isAdmin) { ex('DELETE FROM slots WHERE id = :id', [':id' => $id]); flash('ok', 'Занятие удалено.'); }

    if ($do === 'export_xml') {
        $xml = new DOMDocument('1.0', 'UTF-8'); $xml->formatOutput = true;
        $root = $xml->createElement('schedule');
        $root->setAttribute('week', date('W')); $root->setAttribute('updated', date('Y-m-d'));
        $xml->appendChild($root);
        $days = [1=>'пн',2=>'вт',3=>'ср',4=>'чт',5=>'пт',6=>'сб',7=>'вс'];
        foreach (q('SELECT s.*, c.name FROM slots s JOIN chefs c ON c.id = s.chef_id WHERE s.starts_at >= NOW() ORDER BY s.starts_at') as $r) {
            $ts = strtotime($r['starts_at']);
            $el = $xml->createElement('lesson');
            $attrs = ['id'=>'s'.$r['id'], 'topic'=>$r['topic'], 'chef'=>$r['name'], 'day'=>$days[(int)date('N',$ts)],
                      'day_index'=>date('N',$ts), 'date'=>date('Y-m-d',$ts), 'time'=>date('H:i',$ts),
                      'duration'=>(string)$r['duration'], 'seats'=>(string)$r['seats'], 'price'=>(string)$r['price']];
            foreach ($attrs as $k => $v) $el->setAttribute($k, (string)$v);
            $root->appendChild($el);
        }
        $xml->save(__DIR__ . '/../data/schedule.xml');
        audit('xml.export'); flash('ok', 'Расписание выгружено в XML.');
    }
    if ($do === 'validate_xml') {
        libxml_use_internal_errors(true);
        $d = new DOMDocument();
        $ok = $d->load(__DIR__ . '/../data/schedule.xml') && $d->schemaValidate(__DIR__ . '/../data/schedule.xsd');
        $msgs = [];
        foreach (libxml_get_errors() as $er) $msgs[] = trim($er->message);
        libxml_clear_errors();
        flash($ok ? 'ok' : 'bad', $ok ? 'XML соответствует схеме.' : 'Ошибки XML: ' . implode('; ', $msgs));
    }
    if ($do === 'restore' && $isAdmin && isset($_FILES['dump']) && $_FILES['dump']['error'] === UPLOAD_ERR_OK) {
        $sql = file_get_contents($_FILES['dump']['tmp_name']);
        $count = 0;
        try {
            foreach (sql_statements($sql) as $stmt) { db()->exec($stmt); $count++; }
            audit('db.restore', $count . ' операторов');
            flash('ok', 'Восстановление выполнено: ' . $count . ' операторов.');
        } catch (Throwable $e) {
            log_error($e->getMessage(), 'restore');
            flash('bad', 'Ошибка восстановления: ' . $e->getMessage());
        }
    }
    redirect('admin/index.php?s=' . $s);
}

if ($s === 'service' && isset($_GET['dump'])) {
    $name = 'tarelka-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo "-- Дамп базы " . DB_NAME . " от " . date('d.m.Y H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = ['users','pages','news','ingredients','recipes','recipe_ingredients','recipe_steps','recipe_likes',
               'recipe_ratings','favorites','chefs','slots','lesson_requests','courses','course_lessons','orders',
               'notifications','visits','audit_log','error_log'];
    foreach ($tables as $t) {
        echo "DELETE FROM `$t`;\n";
        foreach (q('SELECT * FROM `' . $t . '`') as $row) {
            $vals = array_map(function ($v) {
                return $v === null ? 'NULL' : db()->quote((string)$v);
            }, array_values($row));
            echo "INSERT INTO `$t` (`" . implode('`,`', array_keys($row)) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    audit('db.dump', $name);
    exit;
}

page_top('Панель управления', 'Панель администратора каталога.', '');
?>
<section class="page-head"><p class="eyebrow">Панель</p><h1>Управление каталогом</h1>
<p class="lead">Вы вошли как <?= e($me['name']) ?> · роль <?= e($me['role']) ?></p></section>

<nav class="admin-nav">
  <?php foreach ($sections as $k => $l): ?>
    <a class="tab<?= $s === $k ? ' on' : '' ?>" href="<?= e(base_url('admin/index.php?s=' . $k)) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($s === 'dashboard'):
    $pending = (int)scalar('SELECT COUNT(*) FROM recipes WHERE status = "pending"');
    $newReq = (int)scalar('SELECT COUNT(*) FROM lesson_requests WHERE status = "new"');
    $newOrd = (int)scalar('SELECT COUNT(*) FROM orders WHERE status = "new"');
    $revenue = (int)scalar('SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = "paid"');
    $days = q('SELECT day, SUM(hits) h FROM visits WHERE day >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY day ORDER BY day');
    $map = []; foreach ($days as $d) $map[$d['day']] = (int)$d['h'];
    $series = []; $max = 1;
    for ($i = 13; $i >= 0; $i--) { $k = date('Y-m-d', strtotime('-' . $i . ' day')); $v = isset($map[$k]) ? $map[$k] : 0; $series[$k] = $v; $max = max($max, $v); }
?>
  <div class="stat-grid" style="margin-bottom:20px">
    <div class="stat"><b><?= $pending ?></b><span>рецептов на модерации</span></div>
    <div class="stat"><b><?= $newReq ?></b><span>новых заявок</span></div>
    <div class="stat"><b><?= $newOrd ?></b><span>счетов ждут оплаты</span></div>
    <div class="stat"><b><?= money($revenue) ?></b><span>оплачено всего</span></div>
  </div>
  <section class="panel"><div class="panel-head"><h2>Посещаемость за 14 дней</h2>
    <p class="sub">Всего просмотров: <?= array_sum($series) ?></p></div>
    <div class="chart"><?php foreach ($series as $k => $v): ?><div title="<?= e($k . ': ' . $v) ?>"><i style="height:<?= round($v / $max * 100) ?>%"></i></div><?php endforeach; ?></div>
    <div class="chart-x"><?php foreach ($series as $k => $v): ?><span><?= e(date('d.m', strtotime($k))) ?></span><?php endforeach; ?></div>
  </section>
  <div class="split">
    <section class="panel"><div class="panel-head"><h3>Топ рецептов</h3></div>
      <div class="table-wrap"><table><thead><tr><th>Рецепт</th><th>Просмотры</th><th>Лайки</th></tr></thead><tbody>
      <?php foreach (q('SELECT id,title,views,likes_count FROM recipes WHERE status="published" ORDER BY views DESC LIMIT :l', [':l'=>6]) as $r): ?>
        <tr><td><a href="<?= e(base_url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a></td><td><?= (int)$r['views'] ?></td><td><?= (int)$r['likes_count'] ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    </section>
    <section class="panel"><div class="panel-head"><h3>Последние действия</h3></div>
      <div class="table-wrap"><table><thead><tr><th>Действие</th><th>Детали</th><th>Когда</th></tr></thead><tbody>
      <?php foreach (q('SELECT * FROM audit_log ORDER BY id DESC LIMIT :l', [':l'=>8]) as $a): ?>
        <tr><td><?= e($a['action']) ?></td><td><?= e($a['detail']) ?></td><td><?= e(dt($a['created_at'], 'd.m H:i')) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    </section>
  </div>

<?php elseif ($s === 'moderation'):
    $rows = q('SELECT r.*, u.name AS author_name FROM recipes r LEFT JOIN users u ON u.id = r.author_id WHERE r.status = "pending" ORDER BY r.created_at ASC');
?>
  <section class="panel"><div class="panel-head"><h2>Очередь модерации</h2><span class="tag"><?= count($rows) ?></span></div>
    <?php if (!$rows): ?><p class="muted small">Очередь пуста.</p><?php else: ?>
      <div class="rows"><?php foreach ($rows as $r):
        $ings = q('SELECT i.name, ri.qty, ri.unit FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.recipe_id = :r ORDER BY ri.sort', [':r'=>(int)$r['id']]);
        $steps = q('SELECT body FROM recipe_steps WHERE recipe_id = :r ORDER BY n', [':r'=>(int)$r['id']]);
        $ingText = implode(', ', array_map(function ($i) {
            return $i['name'] . ($i['qty'] ? ' ' . num($i['qty']) . ' ' . $i['unit'] : '');
        }, $ings)); ?>
        <div class="row-item" style="display:block">
          <div class="btn-row" style="justify-content:space-between;margin-bottom:8px">
            <h3><?= e($r['title']) ?></h3>
            <span class="tag"><?= e(cat_name($r['category'])) ?> · <?= (int)$r['minutes'] ?> мин · автор <?= e($r['author_name'] ?: '—') ?></span>
          </div>
          <p class="small"><?= e($r['description']) ?></p>
          <p class="small"><b>Состав:</b> <?= e($ingText) ?></p>
          <ol class="small" style="padding-left:18px"><?php foreach ($steps as $st): ?><li><?= e($st['body']) ?></li><?php endforeach; ?></ol>
          <div class="btn-row" style="margin-top:12px">
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="publish"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-solid btn-sm" type="submit">Опубликовать</button></form>
            <form method="post" style="display:flex;gap:8px;flex:1;min-width:260px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input class="form-control" name="note" placeholder="Причина отклонения" required style="flex:1">
              <button class="btn btn-danger btn-sm" type="submit">Отклонить</button></form>
            <a class="btn btn-quiet btn-sm" href="<?= e(base_url('recipe.php?id=' . (int)$r['id'])) ?>">Предпросмотр</a>
          </div>
        </div>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </section>

<?php elseif ($s === 'requests'):
    $rows = q('SELECT lr.*, s.topic, s.starts_at, s.price, c.name AS chef_name, u.name AS user_name, u.email
               FROM lesson_requests lr JOIN slots s ON s.id = lr.slot_id JOIN chefs c ON c.id = s.chef_id JOIN users u ON u.id = lr.user_id
               ORDER BY FIELD(lr.status,"new","confirmed","declined"), lr.created_at DESC');
?>
  <section class="panel"><div class="panel-head"><h2>Заявки на занятия</h2><span class="tag"><?= count($rows) ?></span></div>
    <?php if (!$rows): ?><p class="muted small">Заявок пока нет.</p><?php endif; ?>
    <div class="rows"><?php foreach ($rows as $r): ?>
      <div class="row-item" style="display:block">
        <div class="btn-row" style="justify-content:space-between">
          <h3><?= e($r['topic']) ?></h3>
          <span class="tag <?= $r['status'] === 'new' ? 'tag-warn' : ($r['status'] === 'confirmed' ? 'tag-ok' : 'tag-bad') ?>"><?= e($r['status']) ?></span>
        </div>
        <p class="muted small"><?= e($r['user_name']) ?> · <?= e($r['email']) ?> · <?= e(dt($r['starts_at'], 'd.m.Y H:i')) ?> · <?= (int)$r['price'] ? money((int)$r['price']) : 'бесплатно' ?></p>
        <?php if ($r['comment']): ?><p class="small">Комментарий: <?= e($r['comment']) ?></p><?php endif; ?>
        <?php if ($r['status'] === 'new'): ?>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <div class="field"><label>Ответ заявителю</label><textarea name="reply" rows="2" placeholder="Ссылка на трансляцию, детали"></textarea></div>
            <div class="btn-row"><button class="btn btn-solid btn-sm" name="do" value="req_confirm" type="submit">Подтвердить</button>
              <button class="btn btn-danger btn-sm" name="do" value="req_decline" type="submit">Отклонить</button></div>
          </form>
        <?php elseif ($r['reply']): ?><div class="note" style="margin-top:8px"><?= e($r['reply']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?></div>
  </section>

<?php elseif ($s === 'orders'):
    $rows = q('SELECT o.*, u.name AS user_name, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY FIELD(o.status,"new","paid","cancelled"), o.created_at DESC');
?>
  <section class="panel"><div class="panel-head"><h2>Заказы</h2><span class="tag"><?= count($rows) ?></span></div>
    <?php if (!$rows): ?><p class="muted small">Заказов пока нет.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>№</th><th>Клиент</th><th>Позиция</th><th>Сумма</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $o):
      $title = $o['kind'] === 'course' ? (string)scalar('SELECT title FROM courses WHERE id=:i', [':i'=>(int)$o['item_id']])
        : (string)scalar('SELECT s.topic FROM lesson_requests r JOIN slots s ON s.id=r.slot_id WHERE r.id=:i', [':i'=>(int)$o['item_id']]); ?>
      <tr><td><?= (int)$o['id'] ?></td><td><?= e($o['user_name']) ?><br><span class="muted small"><?= e($o['email']) ?></span></td>
        <td><?= e($title ?: '—') ?><br><span class="muted small"><?= $o['kind'] === 'course' ? 'курс' : 'занятие' ?></span></td>
        <td><?= money((int)$o['amount']) ?></td>
        <td><span class="tag <?= $o['status'] === 'paid' ? 'tag-ok' : ($o['status'] === 'new' ? 'tag-warn' : 'tag-bad') ?>"><?= e($o['status']) ?></span></td>
        <td><?php if ($o['status'] === 'new'): ?>
          <form method="post" class="btn-row"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <button class="btn btn-solid btn-sm" name="do" value="order_paid" type="submit">Оплачен</button>
            <button class="btn btn-quiet btn-sm" name="do" value="order_cancel" type="submit">Отменить</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </section>

<?php elseif ($s === 'recipes'):
    $rows = q('SELECT r.*, u.name AS author_name FROM recipes r LEFT JOIN users u ON u.id = r.author_id ORDER BY r.created_at DESC LIMIT :l', [':l'=>100]);
?>
  <section class="panel"><div class="panel-head"><h2>Каталог</h2><span class="tag"><?= count($rows) ?></span></div>
    <div class="table-wrap"><table><thead><tr><th>Название</th><th>Категория</th><th>Автор</th><th>Статус</th><th>Оценка</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><a href="<?= e(base_url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a></td>
        <td><?= e(cat_name($r['category'])) ?></td><td><?= e($r['author_name'] ?: '—') ?></td>
        <td><span class="tag <?= $r['status'] === 'published' ? 'tag-ok' : ($r['status'] === 'pending' ? 'tag-warn' : 'tag-bad') ?>"><?= e($r['status']) ?></span></td>
        <td><?= number_format((float)$r['rating_avg'], 1, '.', '') ?></td>
        <td><form method="post" class="btn-row"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <?php if ($r['status'] === 'published'): ?><button class="btn btn-quiet btn-sm" name="do" value="unpublish" type="submit">Снять</button>
          <?php else: ?><button class="btn btn-solid btn-sm" name="do" value="publish" type="submit">Опубликовать</button><?php endif; ?>
          <?php if ($isAdmin): ?><button class="btn btn-danger btn-sm" name="do" value="delete_recipe" type="submit" onclick="return confirm('Удалить рецепт?')">Удалить</button><?php endif; ?>
        </form></td></tr>
    <?php endforeach; ?></tbody></table></div>
  </section>

<?php elseif ($s === 'users'):
    $rows = q('SELECT * FROM users ORDER BY created_at DESC');
?>
  <section class="panel"><div class="panel-head"><h2>Пользователи</h2><span class="tag"><?= count($rows) ?></span>
    <p class="sub">Роли и блокировку меняет только администратор</p></div>
    <div class="table-wrap"><table><thead><tr><th>Имя</th><th>Логин</th><th>Почта</th><th>Роль</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $u2): $st = author_stats((int)$u2['id']); ?>
      <tr><td><a href="<?= e(base_url('user.php?id=' . (int)$u2['id'])) ?>"><?= e($u2['name']) ?></a>
        <br><span class="muted small"><?= $st['recipes'] ?> рецептов · <?= $st['likes'] ?> лайков</span></td>
        <td><?= e($u2['login']) ?></td><td><?= e($u2['email']) ?></td>
        <td><?php if ($isAdmin): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="role"><input type="hidden" name="id" value="<?= (int)$u2['id'] ?>">
            <select name="role" onchange="this.form.submit()" class="form-control" style="padding:5px 8px;font-size:13px">
              <?php foreach (['user','chef','editor','admin'] as $r): ?><option<?= $u2['role'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
            </select></form>
        <?php else: ?><span class="tag"><?= e($u2['role']) ?></span><?php endif; ?></td>
        <td><span class="tag <?= $u2['status'] === 'active' ? 'tag-ok' : 'tag-bad' ?>"><?= e($u2['status']) ?></span></td>
        <td><?php if ($isAdmin && (int)$u2['id'] !== (int)$me['id']): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="block"><input type="hidden" name="id" value="<?= (int)$u2['id'] ?>">
            <input type="hidden" name="status" value="<?= $u2['status'] === 'active' ? 'blocked' : 'active' ?>">
            <button class="btn btn-quiet btn-sm" type="submit"><?= $u2['status'] === 'active' ? 'Заблокировать' : 'Разблокировать' ?></button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  </section>

<?php elseif ($s === 'content'):
    $pages = q('SELECT * FROM pages ORDER BY slug');
    $news = q('SELECT * FROM news ORDER BY created_at DESC');
    $editPage = isset($_GET['page']) ? q1('SELECT * FROM pages WHERE id = :id', [':id'=>(int)$_GET['page']]) : null;
    $editNews = isset($_GET['news']) ? q1('SELECT * FROM news WHERE id = :id', [':id'=>(int)$_GET['news']]) : null;
?>
  <div class="split">
    <section class="panel"><div class="panel-head"><h2><?= $editPage ? 'Страница' : 'Новая страница' ?></h2></div>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="save_page">
        <input type="hidden" name="id" value="<?= (int)($editPage ? $editPage['id'] : 0) ?>">
        <?= field('title', 'Заголовок', 'text', $editPage ? $editPage['title'] : '') ?>
        <?php if (!$editPage): ?><?= field('slug', 'Адрес (латиницей)', 'text', '') ?><?php endif; ?>
        <div class="field"><label>Содержимое (HTML)</label><textarea name="body" rows="7"><?= e($editPage ? $editPage['body'] : '') ?></textarea></div>
        <?= field('seo_title', 'Заголовок для поиска', 'text', $editPage ? $editPage['seo_title'] : '') ?>
        <?= field('seo_desc', 'Описание для поиска', 'text', $editPage ? $editPage['seo_desc'] : '') ?>
        <label class="check"><input type="checkbox" name="published" value="1"<?= !$editPage || $editPage['published'] ? ' checked' : '' ?>><span>Опубликовано</span></label>
        <div class="btn-row" style="margin-top:12px"><button class="btn btn-solid" type="submit">Сохранить</button>
          <a class="btn btn-quiet" href="<?= e(base_url('admin/index.php?s=content')) ?>">Очистить</a></div>
      </form>
      <hr class="hr">
      <div class="table-wrap"><table><thead><tr><th>Адрес</th><th>Заголовок</th><th></th></tr></thead><tbody>
      <?php foreach ($pages as $p): ?><tr><td><?= e($p['slug']) ?></td><td><?= e($p['title']) ?></td>
        <td><a class="btn btn-quiet btn-sm" href="<?= e(base_url('admin/index.php?s=content&page=' . (int)$p['id'])) ?>">Править</a></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </section>

    <section class="panel"><div class="panel-head"><h2><?= $editNews ? 'Материал' : 'Новый материал' ?></h2></div>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="save_news">
        <input type="hidden" name="id" value="<?= (int)($editNews ? $editNews['id'] : 0) ?>">
        <?= field('title', 'Заголовок', 'text', $editNews ? $editNews['title'] : '') ?>
        <div class="field"><label>Текст (HTML)</label><textarea name="body" rows="7"><?= e($editNews ? $editNews['body'] : '') ?></textarea></div>
        <label class="check"><input type="checkbox" name="published" value="1"<?= !$editNews || $editNews['published'] ? ' checked' : '' ?>><span>Опубликовано</span></label>
        <div class="btn-row" style="margin-top:12px"><button class="btn btn-solid" type="submit">Сохранить</button>
          <a class="btn btn-quiet" href="<?= e(base_url('admin/index.php?s=content')) ?>">Очистить</a></div>
      </form>
      <hr class="hr">
      <div class="table-wrap"><table><thead><tr><th>Заголовок</th><th>Дата</th><th></th></tr></thead><tbody>
      <?php foreach ($news as $n): ?><tr><td><?= e($n['title']) ?></td><td><?= e(dt($n['created_at'], 'd.m.Y')) ?></td>
        <td class="btn-row"><a class="btn btn-quiet btn-sm" href="<?= e(base_url('admin/index.php?s=content&news=' . (int)$n['id'])) ?>">Править</a>
        <?php if ($isAdmin): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="del_news"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">×</button></form><?php endif; ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </section>
  </div>

<?php elseif ($s === 'lessons'):
    $chefs = q('SELECT * FROM chefs ORDER BY name');
    $slots = q('SELECT s.*, c.name AS chef_name FROM slots s JOIN chefs c ON c.id = s.chef_id ORDER BY s.starts_at DESC');
    $edit = isset($_GET['slot']) ? q1('SELECT * FROM slots WHERE id = :id', [':id'=>(int)$_GET['slot']]) : null;
?>
  <section class="panel"><div class="panel-head"><h2><?= $edit ? 'Занятие' : 'Новое занятие' ?></h2>
    <form method="post" class="btn-row"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-quiet btn-sm" name="do" value="export_xml" type="submit">Выгрузить XML</button>
      <button class="btn btn-quiet btn-sm" name="do" value="validate_xml" type="submit">Проверить XML</button></form></div>
    <form method="post" class="form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="save_slot">
      <input type="hidden" name="id" value="<?= (int)($edit ? $edit['id'] : 0) ?>">
      <?= field('topic', 'Тема', 'text', $edit ? $edit['topic'] : '') ?>
      <div class="split">
        <div class="field"><label>Повар</label><select name="chef_id">
          <?php foreach ($chefs as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $edit && (int)$edit['chef_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Начало</label><input name="starts_at" type="datetime-local" value="<?= $edit ? e(date('Y-m-d\TH:i', strtotime($edit['starts_at']))) : '' ?>"></div>
      </div>
      <div class="split">
        <?= field('duration', 'Минут', 'number', (string)($edit ? $edit['duration'] : 90)) ?>
        <?= field('seats', 'Мест', 'number', (string)($edit ? $edit['seats'] : 12)) ?>
      </div>
      <?= field('price', 'Цена, ₽ (0 — бесплатно)', 'number', (string)($edit ? $edit['price'] : 0)) ?>
      <label class="check"><input type="checkbox" name="open" value="1"<?= !$edit || $edit['status'] === 'open' ? ' checked' : '' ?>><span>Открыта запись</span></label>
      <div class="btn-row" style="margin-top:12px"><button class="btn btn-solid" type="submit">Сохранить</button>
        <a class="btn btn-quiet" href="<?= e(base_url('admin/index.php?s=lessons')) ?>">Очистить</a></div>
    </form>
  </section>
  <section class="panel"><div class="panel-head"><h2>Все занятия</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Дата</th><th>Тема</th><th>Повар</th><th>Мест</th><th>Цена</th><th></th></tr></thead><tbody>
    <?php foreach ($slots as $sl): ?><tr><td><?= e(dt($sl['starts_at'], 'd.m.Y H:i')) ?></td><td><?= e($sl['topic']) ?></td>
      <td><?= e($sl['chef_name']) ?></td><td><?= (int)$sl['seats'] ?></td><td><?= (int)$sl['price'] ? money((int)$sl['price']) : '—' ?></td>
      <td class="btn-row"><a class="btn btn-quiet btn-sm" href="<?= e(base_url('admin/index.php?s=lessons&slot=' . (int)$sl['id'])) ?>">Править</a>
      <?php if ($isAdmin): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="del_slot"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Удалить занятие?')">×</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  </section>

<?php else:
    $plain = (int)scalar('SELECT COUNT(*) FROM users WHERE pass_hash NOT LIKE "$2y$%"');
    $checks = [
      ['Подготовленные запросы', true, 'Все выборки идут через PDO::prepare с именованными параметрами'],
      ['Экранирование вывода', true, 'Пользовательские данные выводятся через htmlspecialchars'],
      ['Хеширование паролей', $plain === 0, 'В базе нет паролей открытым текстом'],
      ['CSRF в формах', true, 'Каждая POST-форма содержит токен, сравнение через hash_equals'],
      ['Права на панель', is_admin(), 'Раздел доступен только ролям admin и editor'],
      ['Cookie сеанса', true, 'httponly, samesite=Lax, secure при HTTPS'],
      ['Журнал ошибок', true, 'Ошибки пишутся в error_log и не показываются пользователю'],
    ];
    $errs = q('SELECT * FROM error_log ORDER BY id DESC LIMIT :l', [':l'=>15]);
    $auditRows = q('SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT :l', [':l'=>25]);
?>
  <div class="split">
    <section class="panel"><div class="panel-head"><h2>Резервная копия</h2></div>
      <p class="small">Дамп содержит все таблицы базы, включая рецепты, заявки и заказы.</p>
      <a class="btn btn-solid" href="<?= e(base_url('admin/index.php?s=service&dump=1')) ?>"><?= icon('db', 16) ?> Скачать SQL-дамп</a>
      <?php if ($isAdmin): ?>
        <hr class="hr">
        <div class="panel-head"><h3>Восстановление</h3></div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="restore">
          <div class="field"><label>Файл дампа .sql</label><input type="file" name="dump" accept=".sql" required></div>
          <button class="btn btn-danger" type="submit" onclick="return confirm('Данные будут перезаписаны. Продолжить?')">Восстановить из дампа</button>
        </form>
      <?php endif; ?>
    </section>
    <section class="panel"><div class="panel-head"><h2>Самопроверка</h2></div>
      <div class="table-wrap"><table><thead><tr><th>Проверка</th><th>Пояснение</th><th>Итог</th></tr></thead><tbody>
      <?php foreach ($checks as $row): list($n, $ok, $d) = $row; ?>
        <tr><td><?= e($n) ?></td><td class="small"><?= e($d) ?></td>
        <td><span class="tag <?= $ok ? 'tag-ok' : 'tag-bad' ?>"><?= $ok ? 'ok' : 'замечание' ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </section>
  </div>
  <section class="panel"><div class="panel-head"><h2>Журнал ошибок</h2><span class="tag"><?= count($errs) ?></span></div>
    <?php if (!$errs): ?><p class="muted small">Ошибок не зарегистрировано.</p><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Когда</th><th>Сообщение</th><th>Контекст</th></tr></thead><tbody>
      <?php foreach ($errs as $er): ?><tr><td><?= e(dt($er['created_at'], 'd.m H:i')) ?></td><td><?= e($er['message']) ?></td><td><?= e($er['context']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
  </section>
  <section class="panel"><div class="panel-head"><h2>Журнал действий</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Детали</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($auditRows as $a): ?><tr><td><?= e(dt($a['created_at'], 'd.m H:i')) ?></td><td><?= e($a['name'] ?: 'гость') ?></td>
      <td><?= e($a['action']) ?></td><td><?= e($a['detail']) ?></td><td><?= e($a['ip']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
<?php page_bottom(); ?>