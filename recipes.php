<?php
require_once __DIR__ . '/inc/ui.php';

$q = trim((string)($_GET['q'] ?? ''));
$cat = (string)($_GET['cat'] ?? '');
$sort = (string)($_GET['sort'] ?? 'rating');
if (!isset(categories()[$cat])) $cat = '';
if (!in_array($sort, ['rating','time','title','likes'], true)) $sort = 'rating';

$sql = 'SELECT id,title,category,minutes,difficulty,rating_avg,rating_count,likes_count FROM recipes WHERE status = "published"';
$p = [];
if ($cat !== '') { $sql .= ' AND category = :cat'; $p[':cat'] = $cat; }
if ($q !== '') {
    $sql .= ' AND (title LIKE :q1 OR description LIKE :q2 OR EXISTS (SELECT 1 FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.recipe_id = recipes.id AND i.name LIKE :q3))';
    $like = '%' . $q . '%'; $p[':q1'] = $like; $p[':q2'] = $like; $p[':q3'] = $like;
}
$sql .= $sort === 'time' ? ' ORDER BY minutes ASC' : ($sort === 'title' ? ' ORDER BY title ASC'
      : ($sort === 'likes' ? ' ORDER BY likes_count DESC' : ' ORDER BY rating_avg DESC, rating_count DESC'));
$sql .= ' LIMIT :lim'; $p[':lim'] = 48;
$rows = q($sql, $p);
$liked = liked_ids();
$counts = [];
foreach (q('SELECT category, COUNT(*) n FROM recipes WHERE status="published" GROUP BY category') as $r) $counts[$r['category']] = (int)$r['n'];
$total = (int)scalar('SELECT COUNT(*) FROM recipes WHERE status = "published"');

page_top('Каталог рецептов', 'Каталог рецептов: восемь категорий, поиск по названию и ингредиентам, сортировка по оценкам и времени.', 'recipes');
?>
<section class="page-head">
  <p class="eyebrow">Каталог</p>
  <h1>Рецепты</h1>
  <p class="lead">Поиск идёт по названию, описанию и составу. Список обновляется без перезагрузки страницы.</p>
</section>

<form class="filters" id="catalog-filters" method="get" action="<?= e(base_url('recipes.php')) ?>">
  <div class="field grow"><label for="f-q">Поиск</label>
    <input id="f-q" name="q" type="text" value="<?= e($q) ?>" placeholder="название или ингредиент"></div>
  <div class="field"><label for="f-sort">Сортировка</label>
    <select id="f-sort" name="sort">
      <option value="rating"<?= $sort === 'rating' ? ' selected' : '' ?>>по оценке</option>
      <option value="likes"<?= $sort === 'likes' ? ' selected' : '' ?>>по лайкам</option>
      <option value="time"<?= $sort === 'time' ? ' selected' : '' ?>>сначала быстрые</option>
      <option value="title"<?= $sort === 'title' ? ' selected' : '' ?>>по алфавиту</option>
    </select></div>
  <button class="btn btn-solid" type="submit">Найти</button>
  <a class="btn btn-quiet" href="<?= e(base_url('recipes.php')) ?>">Сбросить</a>
</form>

<div class="chips">
  <a class="chip<?= $cat === '' ? ' on' : '' ?>" data-cat="" href="<?= e(base_url('recipes.php')) ?>">Все (<?= $total ?>)</a>
  <?php foreach (categories() as $k => $c): ?>
    <a class="chip<?= $cat === $k ? ' on' : '' ?>" data-cat="<?= e($k) ?>" href="<?= e(base_url('recipes.php?cat=' . $k)) ?>"><?= e($c['n']) ?> (<?= $counts[$k] ?? 0 ?>)</a>
  <?php endforeach; ?>
</div>

<p class="muted small">Найдено: <b id="catalog-count"><?= count($rows) ?></b> <span id="catalog-status"></span></p>
<div class="grid" id="catalog-grid">
  <?php if (!$rows): ?>
    <div class="empty"><h3>Ничего не найдено</h3><p>Измените запрос или сбросьте фильтры.</p></div>
  <?php else: foreach ($rows as $r) echo recipe_card($r, $liked); endif; ?>
</div>
<?php page_bottom(); ?>