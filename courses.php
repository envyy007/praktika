<?php
require_once __DIR__ . '/inc/ui.php';
$rows = q('SELECT c.*, ch.name AS chef_name FROM courses c LEFT JOIN chefs ch ON ch.id = c.chef_id WHERE c.published = 1 ORDER BY c.price ASC');
$u = current_user();
$paid = [];
if ($u) $paid = array_map('intval', array_column(q('SELECT item_id FROM orders WHERE user_id = :u AND kind = "course" AND status = "paid"', [':u' => (int)$u['id']]), 'item_id'));

page_top('Курсы по готовке', 'Курсы по готовке: базовая кухня, выпечка, быстрые ужины и кондитерский минимум.', 'courses');
?>
<section class="page-head"><p class="eyebrow">Обучение</p><h1>Курсы по готовке</h1>
<p class="lead">Каждый курс — последовательность уроков с разбором техники и типичных ошибок.
Материалы открываются в аккаунте после подтверждения оплаты.</p></section>

<div class="row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px">
  <?php foreach ($rows as $c): ?>
    <article class="course-card reveal">
      <span class="tag"><?= e($c['level']) ?></span>
      <h3><a href="<?= e(base_url('course.php?slug=' . $c['slug'])) ?>"><?= e($c['title']) ?></a></h3>
      <p class="muted small" style="flex:1"><?= e($c['summary']) ?></p>
      <p class="small"><?= (int)$c['lessons_count'] ?> уроков · <?= (int)$c['hours'] ?> часов<?= $c['chef_name'] ? ' · ' . e($c['chef_name']) : '' ?></p>
      <div class="btn-row" style="justify-content:space-between">
        <span class="price"><?= money((int)$c['price']) ?></span>
        <?php if (in_array((int)$c['id'], $paid, true)): ?>
          <a class="btn btn-soft btn-sm" href="<?= e(base_url('account.php?tab=orders')) ?>">Куплен</a>
        <?php else: ?>
          <a class="btn btn-solid btn-sm" href="<?= e(base_url('course.php?slug=' . $c['slug'])) ?>">Подробнее</a>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php page_bottom(); ?>