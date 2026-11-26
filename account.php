<?php
require_once __DIR__ . '/inc/ui.php';
require_login();

$u = current_user();
$uid = (int)$u['id'];
$tab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview','recipes','favorites','lessons','orders','notes','profile'], true)) $tab = 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        if (mb_strlen($name) < 2) flash('bad', 'Укажите имя.');
        else {
            ex('UPDATE users SET name = :n, bio = :b WHERE id = :id', [':n' => $name, ':b' => mb_substr($bio, 0, 300), ':id' => $uid]);
            flash('ok', 'Профиль обновлён.');
        }
        redirect('account.php?tab=profile');
    }
    if ($do === 'readall') { ex('UPDATE notifications SET is_read = 1 WHERE user_id = :u', [':u' => $uid]); redirect('account.php?tab=notes'); }
    if ($do === 'cancel_request') {
        $rid = (int)($_POST['id'] ?? 0);
        ex('UPDATE lesson_requests SET status = "declined", reply = "Отменено пользователем" WHERE id = :id AND user_id = :u AND status = "new"', [':id' => $rid, ':u' => $uid]);
        flash('ok', 'Заявка отменена.');
        redirect('account.php?tab=lessons');
    }
    if ($do === 'resubmit') {
        $rid = (int)($_POST['id'] ?? 0);
        ex('UPDATE recipes SET status = "pending", reject_note = "" WHERE id = :id AND author_id = :a AND status = "rejected"', [':id' => $rid, ':a' => $uid]);
        flash('ok', 'Рецепт снова отправлен на модерацию.');
        redirect('account.php?tab=recipes');
    }
}

$myRecipes = q('SELECT id,title,category,status,reject_note,rating_avg,rating_count,likes_count,created_at FROM recipes WHERE author_id = :a ORDER BY created_at DESC', [':a' => $uid]);
$fav = q('SELECT r.id,r.title,r.category,r.minutes,r.difficulty,r.rating_avg,r.rating_count,r.likes_count FROM favorites f JOIN recipes r ON r.id = f.recipe_id WHERE f.user_id = :u ORDER BY f.created_at DESC', [':u' => $uid]);
$reqs = q('SELECT lr.*, s.topic, s.starts_at, s.price, c.name AS chef_name FROM lesson_requests lr JOIN slots s ON s.id = lr.slot_id JOIN chefs c ON c.id = s.chef_id WHERE lr.user_id = :u ORDER BY lr.created_at DESC', [':u' => $uid]);
$orders = q('SELECT * FROM orders WHERE user_id = :u ORDER BY created_at DESC', [':u' => $uid]);
$notes = q('SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC LIMIT :l', [':u' => $uid, ':l' => 60]);
$stats = author_stats($uid);
$liked = liked_ids();
$statusTag = ['pending' => 'tag-warn', 'published' => 'tag-ok', 'rejected' => 'tag-bad'];
$statusName = ['pending' => 'на модерации', 'published' => 'опубликован', 'rejected' => 'отклонён'];
$orderName = ['new' => 'ожидает оплаты', 'paid' => 'оплачен', 'cancelled' => 'отменён'];
$orderTag = ['new' => 'tag-warn', 'paid' => 'tag-ok', 'cancelled' => 'tag-bad'];
$reqName = ['new' => 'на рассмотрении', 'confirmed' => 'подтверждена', 'declined' => 'отклонена'];
$reqTag = ['new' => 'tag-warn', 'confirmed' => 'tag-ok', 'declined' => 'tag-bad'];

page_top('Личный аккаунт', 'Аккаунт: рецепты, избранное, заявки на занятия, заказы и уведомления.', 'account');
?>
<section class="page-head">
  <div style="display:flex;gap:16px;align-items:center">
    <div class="avatar"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
    <div><h1 style="font-size:30px"><?= e($u['name']) ?></h1>
      <p class="muted small">@<?= e($u['login']) ?> · с <?= e(dt($u['created_at'], 'd.m.Y')) ?></p></div>
  </div>
</section>

<div class="tabs">
  <?php foreach (['overview'=>'Обзор','recipes'=>'Мои рецепты','favorites'=>'Избранное','lessons'=>'Занятия','orders'=>'Заказы','notes'=>'Уведомления','profile'=>'Профиль'] as $k => $l): ?>
    <a class="tab<?= $tab === $k ? ' on' : '' ?>" href="<?= e(base_url('account.php?tab=' . $k)) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
  <div class="stat-grid" style="margin-bottom:20px">
    <div class="stat"><b><?= $stats['recipes'] ?></b><span>опубликовано рецептов</span></div>
    <div class="stat"><b><?= number_format($stats['rating'], 1, '.', '') ?></b><span>рейтинг автора</span></div>
    <div class="stat"><b><?= $stats['likes'] ?></b><span>лайков получено</span></div>
    <div class="stat"><b><?= count($fav) ?></b><span>в избранном</span></div>
  </div>
  <div class="split">
    <section class="panel"><div class="panel-head"><h3>Последние уведомления</h3><a class="btn btn-quiet btn-sm" href="<?= e(base_url('account.php?tab=notes')) ?>">Все</a></div>
      <?php if (!$notes): ?><p class="muted small">Пока пусто.</p><?php else: ?>
        <div class="rows"><?php foreach (array_slice($notes, 0, 4) as $n): ?>
          <div class="row-item"><div class="grow"><p style="margin:0"><?= e($n['body']) ?></p><p class="muted small" style="margin:4px 0 0"><?= e(dt($n['created_at'])) ?></p></div>
          <?php if (!$n['is_read']): ?><span class="tag tag-accent">новое</span><?php endif; ?></div>
        <?php endforeach; ?></div>
      <?php endif; ?>
    </section>
    <section class="panel"><div class="panel-head"><h3>Быстрые действия</h3></div>
      <div class="btn-row"><a class="btn btn-solid" href="<?= e(base_url('submit.php')) ?>">Добавить рецепт</a>
        <a class="btn btn-quiet" href="<?= e(base_url('random.php')) ?>">Подобрать блюдо</a>
        <a class="btn btn-quiet" href="<?= e(base_url('lessons.php')) ?>">Записаться на занятие</a>
        <a class="btn btn-quiet" href="<?= e(base_url('user.php?id=' . $uid)) ?>">Публичный профиль</a></div>
    </section>
  </div>

<?php elseif ($tab === 'recipes'): ?>
  <section class="panel"><div class="panel-head"><h2>Мои рецепты</h2><a class="btn btn-solid btn-sm" href="<?= e(base_url('submit.php')) ?>">Добавить</a></div>
    <?php if (!$myRecipes): ?><div class="empty"><h3>Рецептов пока нет</h3><p>Отправьте свой рецепт — после модерации он появится в каталоге.</p>
      <a class="btn btn-solid" href="<?= e(base_url('submit.php')) ?>">Добавить рецепт</a></div>
    <?php else: ?><div class="table-wrap"><table>
      <thead><tr><th>Название</th><th>Категория</th><th>Статус</th><th>Оценка</th><th>Лайки</th><th></th></tr></thead><tbody>
      <?php foreach ($myRecipes as $r): ?>
        <tr><td><a href="<?= e(base_url('recipe.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a>
          <?php if ($r['reject_note']): ?><p class="err" style="margin:4px 0 0"><?= e($r['reject_note']) ?></p><?php endif; ?></td>
          <td><?= e(cat_name($r['category'])) ?></td>
          <td><span class="tag <?= $statusTag[$r['status']] ?>"><?= e($statusName[$r['status']]) ?></span></td>
          <td><?= number_format((float)$r['rating_avg'], 1, '.', '') ?> (<?= (int)$r['rating_count'] ?>)</td>
          <td><?= (int)$r['likes_count'] ?></td>
          <td><?php if ($r['status'] === 'rejected'): ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="resubmit"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-quiet btn-sm" type="submit">Отправить снова</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </section>

<?php elseif ($tab === 'favorites'): ?>
  <section class="panel"><div class="panel-head"><h2>Избранное</h2></div>
    <?php if (!$fav): ?><div class="empty"><h3>Пусто</h3><p>Добавляйте рецепты кнопкой «В избранное».</p><a class="btn btn-solid" href="<?= e(base_url('recipes.php')) ?>">В каталог</a></div>
    <?php else: ?><div class="grid"><?php foreach ($fav as $r) echo recipe_card($r, $liked); ?></div><?php endif; ?>
  </section>

<?php elseif ($tab === 'lessons'): ?>
  <section class="panel"><div class="panel-head"><h2>Заявки на занятия</h2><a class="btn btn-quiet btn-sm" href="<?= e(base_url('lessons.php')) ?>">Расписание</a></div>
    <?php if (!$reqs): ?><div class="empty"><h3>Заявок нет</h3><p>Выберите занятие и отправьте заявку.</p><a class="btn btn-solid" href="<?= e(base_url('lessons.php')) ?>">К занятиям</a></div>
    <?php else: ?><div class="rows"><?php foreach ($reqs as $r): ?>
      <div class="row-item"><div class="grow">
        <h3><?= e($r['topic']) ?></h3>
        <p class="muted small"><?= e($r['chef_name']) ?> · <?= e(dt($r['starts_at'], 'd.m.Y H:i')) ?> · <?= (int)$r['price'] ? money((int)$r['price']) : 'бесплатно' ?></p>
        <?php if ($r['reply']): ?><div class="note" style="margin-top:8px"><?= e($r['reply']) ?></div><?php endif; ?>
      </div>
      <div style="text-align:right">
        <span class="tag <?= $reqTag[$r['status']] ?>"><?= e($reqName[$r['status']]) ?></span>
        <?php if ($r['status'] === 'new'): ?>
          <form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="cancel_request"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-quiet btn-sm" type="submit">Отменить</button></form>
        <?php endif; ?>
      </div></div>
    <?php endforeach; ?></div><?php endif; ?>
  </section>

<?php elseif ($tab === 'orders'): ?>
  <section class="panel"><div class="panel-head"><h2>Заказы</h2><p class="sub">Оплата подтверждается администратором, после этого открывается доступ</p></div>
    <?php if (!$orders): ?><div class="empty"><h3>Заказов нет</h3><a class="btn btn-solid" href="<?= e(base_url('courses.php')) ?>">Выбрать курс</a></div>
    <?php else: ?><div class="table-wrap"><table>
      <thead><tr><th>№</th><th>Позиция</th><th>Сумма</th><th>Статус</th><th>Создан</th><th></th></tr></thead><tbody>
      <?php foreach ($orders as $o):
        $title = $o['kind'] === 'course'
          ? (string)scalar('SELECT title FROM courses WHERE id = :i', [':i' => (int)$o['item_id']])
          : (string)scalar('SELECT s.topic FROM lesson_requests r JOIN slots s ON s.id = r.slot_id WHERE r.id = :i', [':i' => (int)$o['item_id']]);
      ?>
        <tr><td><?= (int)$o['id'] ?></td><td><?= e($title ?: '—') ?><p class="muted small" style="margin:2px 0 0"><?= $o['kind'] === 'course' ? 'курс' : 'занятие' ?></p></td>
          <td><?= money((int)$o['amount']) ?></td>
          <td><span class="tag <?= $orderTag[$o['status']] ?>"><?= e($orderName[$o['status']]) ?></span></td>
          <td><?= e(dt($o['created_at'], 'd.m.Y')) ?></td>
          <td><?php if ($o['kind'] === 'course' && $o['status'] === 'paid'): ?>
            <a class="btn btn-soft btn-sm" href="<?= e(base_url('course.php?slug=' . (string)scalar('SELECT slug FROM courses WHERE id = :i', [':i' => (int)$o['item_id']]))) ?>">Материалы</a><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </section>

<?php elseif ($tab === 'notes'): ?>
  <section class="panel"><div class="panel-head"><h2>Уведомления</h2>
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="readall">
      <button class="btn btn-quiet btn-sm" type="submit">Отметить прочитанными</button></form></div>
    <?php if (!$notes): ?><p class="muted small">Уведомлений нет.</p><?php else: ?>
      <div class="rows"><?php foreach ($notes as $n): ?>
        <div class="row-item"><div class="grow">
          <?php if ($n['url']): ?><a href="<?= e(base_url($n['url'])) ?>"><?= e($n['body']) ?></a><?php else: ?><p style="margin:0"><?= e($n['body']) ?></p><?php endif; ?>
          <p class="muted small" style="margin:4px 0 0"><?= e(dt($n['created_at'])) ?></p></div>
          <?php if (!$n['is_read']): ?><span class="tag tag-accent">новое</span><?php endif; ?></div>
      <?php endforeach; ?></div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="panel"><div class="panel-head"><h2>Профиль</h2></div>
    <form class="form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="profile">
      <?= field('name', 'Имя', 'text', $u['name']) ?>
      <div class="field"><label for="f-bio">О себе</label><textarea id="f-bio" name="bio" rows="3" maxlength="300"><?= e($u['bio']) ?></textarea></div>
      <p class="hint">Логин и почта: <?= e($u['login']) ?> · <?= e($u['email']) ?></p>
      <button class="btn btn-solid" type="submit">Сохранить</button>
      <a class="btn btn-quiet" href="<?= e(base_url('user.php?id=' . $uid)) ?>">Публичный профиль</a>
    </form>
  </section>
<?php endif; ?>
<?php page_bottom(); ?>