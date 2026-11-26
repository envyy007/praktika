<?php
/** Онлайн-занятия с поварами и запись. */
require_once __DIR__ . '/inc/ui.php';

$chefs = q('SELECT * FROM chefs ORDER BY name');
$slotId = (int)($_GET['slot'] ?? 0);
$slot = $slotId ? q1('SELECT s.*, c.name AS chef_name, c.title AS chef_title FROM slots s JOIN chefs c ON c.id = s.chef_id WHERE s.id = :id', [':id' => $slotId]) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $u = current_user();
    $sid = (int)($_POST['slot_id'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    $target = q1('SELECT * FROM slots WHERE id = :id AND status = "open"', [':id' => $sid]);
    if (!csrf_ok()) $errors['csrf'] = 'Сессия истекла.';
    if (!$target) $errors['slot'] = 'Занятие недоступно для записи.';
    if (mb_strlen($comment) > 300) $errors['comment'] = 'Комментарий до 300 символов.';
    if (!$errors && q1('SELECT id FROM lesson_requests WHERE user_id = :u AND slot_id = :s AND status <> "declined"', [':u' => (int)$u['id'], ':s' => $sid])) {
        $errors['slot'] = 'Вы уже записаны на это занятие.';
    }
    if (!$errors) {
        $taken = (int)scalar('SELECT COUNT(*) FROM lesson_requests WHERE slot_id = :s AND status <> "declined"', [':s' => $sid]);
        if ($taken >= (int)$target['seats']) $errors['slot'] = 'Свободных мест не осталось.';
    }
    if (!$errors) {
        ex('INSERT INTO lesson_requests (user_id,slot_id,comment,status,created_at) VALUES (:u,:s,:c,"new",NOW())',
           [':u' => (int)$u['id'], ':s' => $sid, ':c' => $comment]);
        $reqId = last_id();
        if ((int)$target['price'] > 0) {
            ex('INSERT INTO orders (user_id,kind,item_id,amount,status,created_at) VALUES (:u,"lesson",:i,:a,"new",NOW())',
               [':u' => (int)$u['id'], ':i' => $reqId, ':a' => (int)$target['price']]);
        }
        foreach (q('SELECT id FROM users WHERE role IN ("admin","editor")') as $a) {
            notify((int)$a['id'], 'Новая заявка на занятие: ' . $target['topic'], 'admin/index.php?s=requests');
        }
        audit('lesson.request', 'slot #' . $sid);
        flash('ok', 'Заявка отправлена. Ответ администратора появится в аккаунте.');
        redirect('account.php?tab=lessons');
    }
    http_response_code(422);
}

$fChef = (int)($_GET['chef'] ?? 0);
$sql = 'SELECT s.*, c.name AS chef_name,
        (SELECT COUNT(*) FROM lesson_requests r WHERE r.slot_id = s.id AND r.status <> "declined") AS taken
        FROM slots s JOIN chefs c ON c.id = s.chef_id WHERE s.status = "open" AND s.starts_at >= NOW()';
$p = [];
if ($fChef > 0) { $sql .= ' AND s.chef_id = :c'; $p[':c'] = $fChef; }
$sql .= ' ORDER BY s.starts_at LIMIT :l'; $p[':l'] = 40;
$rows = q($sql, $p);
$u = current_user();

page_top('Занятия с поварами', 'Онлайн-занятия с поварами: расписание, свободные места и запись.', 'lessons');
?>
<section class="page-head"><p class="eyebrow">Онлайн</p><h1>Занятия с поварами</h1>
<p class="lead">Встречи проходят онлайн. Часть занятий бесплатные, остальные — по билету.
После заявки администратор подтверждает место и присылает ответ в аккаунт.</p>
<div class="btn-row" style="margin-top:14px"><a class="btn btn-quiet" href="<?= e(base_url('schedule.php')) ?>">Расписание таблицей</a></div>
</section>

<?php if ($slot): ?>
<section class="panel">
  <div class="panel-head"><h2>Запись на занятие</h2><span class="tag <?= (int)$slot['price'] ? 'tag-accent' : 'tag-ok' ?>"><?= (int)$slot['price'] ? money((int)$slot['price']) : 'бесплатно' ?></span></div>
  <div class="split">
    <div>
      <h3 style="font-size:20px;margin-bottom:6px"><?= e($slot['topic']) ?></h3>
      <p class="muted small"><?= e($slot['chef_name']) ?> · <?= e($slot['chef_title']) ?></p>
      <p><?= e(dt($slot['starts_at'], 'd.m.Y в H:i')) ?> · <?= (int)$slot['duration'] ?> минут · мест: <?= (int)$slot['seats'] ?></p>
      <?php if ((int)$slot['price'] > 0): ?><p class="hint">После подтверждения заявки в аккаунте появится счёт на оплату.</p><?php endif; ?>
    </div>
    <div>
      <?php if (!$u): ?>
        <div class="flash flash-info">Для записи нужен аккаунт.</div>
        <a class="btn btn-solid" href="<?= e(base_url('login.php?back=' . urlencode('lessons.php?slot=' . $slotId))) ?>">Войти</a>
        <a class="btn btn-quiet" href="<?= e(base_url('register.php')) ?>">Регистрация</a>
      <?php else: ?>
        <?php if (isset($errors['slot'])): ?><div class="flash flash-bad"><?= e($errors['slot']) ?></div><?php endif; ?>
        <?php if (isset($errors['csrf'])): ?><div class="flash flash-bad"><?= e($errors['csrf']) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
          <div class="field<?= isset($errors['comment']) ? ' bad' : '' ?>"><label for="f-comment">Комментарий повару</label>
            <textarea id="f-comment" name="comment" rows="3" maxlength="300" placeholder="Опыт готовки, вопросы к занятию"></textarea>
            <?php if (isset($errors['comment'])): ?><p class="err"><?= e($errors['comment']) ?></p><?php endif; ?></div>
          <button class="btn btn-solid" type="submit">Отправить заявку</button>
          <a class="btn btn-quiet" href="<?= e(base_url('lessons.php')) ?>">Отмена</a>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>Расписание</h2><p class="sub">Фильтр обновляет список без перезагрузки</p></div>
  <form class="filters" id="schedule-filters" method="get" action="<?= e(base_url('lessons.php')) ?>">
    <div class="field"><label for="f-chef">Повар</label>
      <select id="f-chef" name="chef"><option value="">Все повара</option>
        <?php foreach ($chefs as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $fChef === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="f-day">Дата</label><input id="f-day" name="day" type="date"></div>
    <button class="btn btn-quiet" type="submit">Показать</button>
    <span class="muted small" id="schedule-status"></span>
  </form>

  <div id="schedule-box">
    <?php if (!$rows): ?><div class="empty"><h3>Занятий не найдено</h3><p>Выберите другого повара или дату.</p></div>
    <?php else: ?>
      <div class="rows">
        <?php foreach ($rows as $s): $free = max(0, (int)$s['seats'] - (int)$s['taken']); ?>
          <div class="slot-row reveal">
            <div class="slot-date"><b><?= e(date('d', strtotime($s['starts_at']))) ?></b>
              <span><?= e(['','пн','вт','ср','чт','пт','сб','вс'][(int)date('N', strtotime($s['starts_at']))]) ?> · <?= e(date('H:i', strtotime($s['starts_at']))) ?></span></div>
            <div><h3><?= e($s['topic']) ?></h3>
              <p class="muted small"><?= e($s['chef_name']) ?> · <?= (int)$s['duration'] ?> мин · <?= $free > 0 ? $free . ' ' . plural($free, ['место','места','мест']) : 'мест нет' ?></p></div>
            <div class="btn-row"><span class="tag <?= (int)$s['price'] ? 'tag-accent' : 'tag-ok' ?>"><?= (int)$s['price'] ? money((int)$s['price']) : 'бесплатно' ?></span>
              <a class="btn btn-quiet btn-sm<?= $free ? '' : ' disabled' ?>" href="<?= e(base_url('lessons.php?slot=' . (int)$s['id'])) ?>">Записаться</a></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Повара</h2></div>
  <div class="rows">
    <?php foreach ($chefs as $c): ?>
      <div class="row-item"><div class="thumb"><?= icon('chef', 22) ?></div>
        <div class="grow"><h3><?= e($c['name']) ?></h3><p class="muted small"><?= e($c['title']) ?></p><p class="small"><?= e($c['bio']) ?></p></div></div>
    <?php endforeach; ?>
  </div>
</section>
<?php page_bottom(); ?>