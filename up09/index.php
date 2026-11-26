<?php
require_once __DIR__ . '/inc/ui.php';

$top = q('SELECT id,title,category,minutes,difficulty,rating_avg,rating_count,likes_count
          FROM recipes WHERE status = "published" ORDER BY rating_avg DESC, rating_count DESC LIMIT :l', [':l' => 8]);
$fresh = q('SELECT id,title,category,minutes,difficulty,rating_avg,rating_count,likes_count
            FROM recipes WHERE status = "published" ORDER BY published_at DESC LIMIT :l', [':l' => 4]);
$slots = q('SELECT s.*, c.name AS chef_name FROM slots s JOIN chefs c ON c.id = s.chef_id
            WHERE s.status = "open" AND s.starts_at >= NOW() ORDER BY s.starts_at LIMIT :l', [':l' => 3]);
$courses = q('SELECT * FROM courses WHERE published = 1 ORDER BY price ASC LIMIT :l', [':l' => 3]);
$liked = liked_ids();
$total = (int)scalar('SELECT COUNT(*) FROM recipes WHERE status = "published"');

page_top('Кулинарный каталог', 'Сорок рецептов с подбором по продуктам, онлайн-занятия с поварами и курсы по готовке.', 'home');
?>
<section class="page-head">
  <p class="eyebrow">Кулинарный каталог</p>
  <h1>Что приготовить<br>из того, что есть дома</h1>
  <p class="lead">Отметьте продукты в холодильнике — каталог подберёт один рецепт, которому не хватает
  не более двух ингредиентов. Или выберите блюдо вручную: <?= $total ?> рецептов в восьми категориях.</p>
  <div class="btn-row" style="margin-top:18px">
    <a class="btn btn-solid" href="<?= e(base_url('random.php')) ?>"><?= icon('dice', 16) ?> Подобрать блюдо</a>
    <a class="btn btn-quiet" href="<?= e(base_url('recipes.php')) ?>">Открыть каталог</a>
  </div>
</section>

<section class="panel reveal">
  <div class="panel-head"><h2>Высокие оценки</h2><a class="btn btn-quiet btn-sm" href="<?= e(base_url('recipes.php?sort=rating')) ?>">Все рецепты</a>
  <p class="sub">Порядок определяется оценками читателей</p></div>
  <div class="grid"><?php foreach ($top as $r) echo recipe_card($r, $liked); ?></div>
</section>

<div class="split">
  <section class="panel reveal">
    <div class="panel-head"><h2>Ближайшие занятия</h2><a class="btn btn-quiet btn-sm" href="<?= e(base_url('lessons.php')) ?>">Расписание</a></div>
    <div class="rows">
      <?php foreach ($slots as $s): ?>
        <div class="row-item">
          <div class="thumb"><?= icon('chef', 22) ?></div>
          <div class="grow"><h3><?= e($s['topic']) ?></h3>
            <p class="muted small"><?= e($s['chef_name']) ?> · <?= e(dt($s['starts_at'], 'd.m.Y H:i')) ?> · <?= (int)$s['duration'] ?> мин</p></div>
          <span class="tag <?= (int)$s['price'] ? 'tag-accent' : 'tag-ok' ?>"><?= (int)$s['price'] ? money((int)$s['price']) : 'бесплатно' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel reveal">
    <div class="panel-head"><h2>Курсы</h2><a class="btn btn-quiet btn-sm" href="<?= e(base_url('courses.php')) ?>">Все курсы</a></div>
    <div class="rows">
      <?php foreach ($courses as $c): ?>
        <div class="row-item">
          <div class="grow"><h3><a href="<?= e(base_url('course.php?slug=' . $c['slug'])) ?>"><?= e($c['title']) ?></a></h3>
            <p class="muted small"><?= (int)$c['lessons_count'] ?> уроков · <?= e($c['level']) ?></p></div>
          <span class="price"><?= money((int)$c['price']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="panel reveal">
  <div class="panel-head"><h2>Недавно опубликовано</h2></div>
  <div class="grid"><?php foreach ($fresh as $r) echo recipe_card($r, $liked); ?></div>
</section>

<section class="panel reveal">
  <div class="panel-head"><h2>Свой рецепт в каталоге</h2></div>
  <p class="lead" style="margin:0 0 16px">Опишите блюдо, ингредиенты и шаги — рецепт попадёт на модерацию.
  После публикации читатели смогут ставить оценки и лайки, они формируют ваш рейтинг автора.</p>
  <a class="btn btn-soft" href="<?= e(base_url('submit.php')) ?>"><?= icon('plus', 16) ?> Добавить рецепт</a>
</section>
<?php page_bottom(); ?>