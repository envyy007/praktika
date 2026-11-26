<?php
require_once __DIR__ . '/inc/ui.php';

$slug = (string)($_GET['slug'] ?? '');
$c = q1('SELECT c.*, ch.name AS chef_name, ch.title AS chef_title FROM courses c LEFT JOIN chefs ch ON ch.id = c.chef_id WHERE c.slug = :s AND c.published = 1', [':s' => $slug]);
if (!$c) {
    http_response_code(404);
    page_top('Курс не найден', 'Курс отсутствует.', 'courses');
    echo '<div class="empty"><h3>Курс не найден</h3><a class="btn btn-solid" href="' . e(base_url('courses.php')) . '">Все курсы</a></div>';
    page_bottom(); exit;
}
$u = current_user();
$lessons = q('SELECT n,title,body FROM course_lessons WHERE course_id = :c ORDER BY n', [':c' => (int)$c['id']]);
$order = $u ? q1('SELECT * FROM orders WHERE user_id = :u AND kind = "course" AND item_id = :i ORDER BY id DESC LIMIT 1', [':u' => (int)$u['id'], ':i' => (int)$c['id']]) : null;
$hasAccess = $order && $order['status'] === 'paid';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $u = current_user();
    if (!csrf_ok()) { flash('bad', 'Сессия истекла.'); redirect('course.php?slug=' . $slug); }
    if ($order && $order['status'] !== 'cancelled') { flash('warn', 'Заказ на этот курс уже создан.'); redirect('account.php?tab=orders'); }
    ex('INSERT INTO orders (user_id,kind,item_id,amount,status,created_at) VALUES (:u,"course",:i,:a,"new",NOW())',
       [':u' => (int)$u['id'], ':i' => (int)$c['id'], ':a' => (int)$c['price']]);
    foreach (q('SELECT id FROM users WHERE role IN ("admin","editor")') as $a) {
        notify((int)$a['id'], 'Новый заказ: курс «' . $c['title'] . '»', 'admin/index.php?s=orders');
    }
    audit('order.create', 'course #' . (int)$c['id']);
    flash('ok', 'Счёт создан. После подтверждения оплаты откроются материалы курса.');
    redirect('account.php?tab=orders');
}

page_top($c['title'], $c['summary'], 'courses');
?>
<p class="eyebrow"><a href="<?= e(base_url('courses.php')) ?>">Курсы</a> · <?= e($c['level']) ?></p>
<section class="page-head"><h1><?= e($c['title']) ?></h1><p class="lead"><?= e($c['summary']) ?></p>
<div class="meta-row">
  <span class="tag"><?= (int)$c['lessons_count'] ?> уроков</span>
  <span class="tag"><?= (int)$c['hours'] ?> часов</span>
  <?php if ($c['chef_name']): ?><span class="tag"><?= icon('chef', 13) ?> <?= e($c['chef_name']) ?></span><?php endif; ?>
</div></section>

<div class="split-wide">
  <section class="panel">
    <div class="panel-head"><h2>Программа</h2><p class="sub"><?= $hasAccess ? 'Материалы доступны' : 'Содержание уроков открывается после оплаты' ?></p></div>
    <div class="rows">
      <?php foreach ($lessons as $l): ?>
        <div class="row-item">
          <span class="step-n"><?= (int)$l['n'] ?></span>
          <div class="grow"><h3><?= e($l['title']) ?></h3>
            <?php if ($hasAccess): ?><p class="small" style="margin:6px 0 0"><?= e($l['body']) ?></p>
            <?php else: ?><p class="muted small" style="margin:6px 0 0"><?= e(mb_substr($l['body'], 0, 70)) ?>…</p><?php endif; ?>
          </div>
          <?php if (!$hasAccess): ?><span class="tag">закрыто</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="panel">
    <div class="panel-head"><h3>Покупка</h3></div>
    <p class="price" style="margin-bottom:12px"><?= money((int)$c['price']) ?></p>
    <?php if (!$u): ?>
      <a class="btn btn-solid" style="width:100%" href="<?= e(base_url('login.php?back=' . urlencode('course.php?slug=' . $slug))) ?>">Войти и купить</a>
    <?php elseif ($hasAccess): ?>
      <div class="flash flash-ok" style="margin:0 0 12px">Курс оплачен, материалы открыты.</div>
      <a class="btn btn-quiet" style="width:100%" href="<?= e(base_url('account.php?tab=orders')) ?>">Мои заказы</a>
    <?php elseif ($order && $order['status'] === 'new'): ?>
      <div class="flash flash-warn" style="margin:0 0 12px">Счёт №<?= (int)$order['id'] ?> ожидает подтверждения оплаты.</div>
      <a class="btn btn-quiet" style="width:100%" href="<?= e(base_url('account.php?tab=orders')) ?>">Открыть счёт</a>
    <?php else: ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-solid" style="width:100%" type="submit"><?= icon('cart', 16) ?> Купить курс</button></form>
      <p class="hint" style="margin-top:10px">Счёт появится в аккаунте. Оплата подтверждается администратором, после этого открывается доступ ко всем урокам.</p>
    <?php endif; ?>
    <hr class="hr">
    <p class="small muted">Возврат в течение 14 дней, если пройдено меньше двух уроков.</p>
  </aside>
</div>
<?php page_bottom(); ?>