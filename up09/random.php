<?php
/** Подбор одного случайного блюда по имеющимся ингредиентам. */
require_once __DIR__ . '/inc/ui.php';

$kinds = ['basic'=>'Базовое','fresh'=>'Овощи и грибы','meat'=>'Мясо и рыба','dairy'=>'Молочное','grain'=>'Крупы и хлеб','fruit'=>'Фрукты и ягоды','spice'=>'Специи и прочее'];
$all = q('SELECT id, name, kind FROM ingredients WHERE kind <> "pantry" ORDER BY kind, name');
$groups = [];
foreach ($all as $i) $groups[$i['kind']][] = $i;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $have = array_map('intval', (array)($_POST['have'] ?? []));
    $_SESSION['pantry'] = array_values(array_unique(array_filter($have)));
    redirect('random.php?pick=1');
}
if (isset($_GET['clear'])) { unset($_SESSION['pantry']); redirect('random.php'); }

$have = (array)($_SESSION['pantry'] ?? []);
$pick = null; $missing = []; $candidates = 0;

if (isset($_GET['pick']) && $have) {
    $haveSet = array_flip($have);
    $recipes = q('SELECT id, title, category, minutes, difficulty, rating_avg, rating_count, likes_count, description, kcal, servings
                  FROM recipes WHERE status = "published"');
    $pool = [];
    foreach ($recipes as $r) {
        $need = q('SELECT i.id, i.name FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
                   WHERE ri.recipe_id = :r AND ri.is_optional = 0 AND i.kind <> "pantry"', [':r' => (int)$r['id']]);
        $miss = [];
        foreach ($need as $n) {
            if (!isset($haveSet[(int)$n['id']])) $miss[] = $n;
        }
        if (count($miss) <= MAX_MISSING && (count($need) - count($miss)) >= 1) {
            $pool[] = ['r' => $r, 'miss' => $miss];
        }
    }
    $candidates = count($pool);
    if ($pool) {
        $counts = array_map(function ($x) { return count($x['miss']); }, $pool);
        $best = min($counts);
        $short = array_values(array_filter($pool, function ($x) use ($best) {
            return count($x['miss']) <= $best + 1;
        }));
        $chosen = $short[random_int(0, count($short) - 1)];
        $pick = $chosen['r'];
        $missing = $chosen['miss'];
    }
}

page_top('Случайное блюдо', 'Отметьте продукты, которые есть дома, и каталог подберёт один случайный рецепт с минимумом докупок.', 'random');
?>
<section class="page-head">
  <p class="eyebrow">Подбор по продуктам</p>
  <h1>Случайное блюдо</h1>
  <p class="lead">Отметьте, что есть дома. Каталог выберет один случайный рецепт, которому не хватает
  не более <?= MAX_MISSING ?> ингредиентов. Соль, перец, сахар, масло и вода считаются базовыми и в расчёт не берутся.</p>
</section>

<?php if (isset($_GET['pick'])): ?>
  <section class="panel">
    <?php if (!$have): ?>
      <div class="empty"><h3>Сначала отметьте продукты</h3><p>Без списка ингредиентов подбор невозможен.</p>
        <a class="btn btn-solid" href="<?= e(base_url('random.php')) ?>">Выбрать продукты</a></div>
    <?php elseif (!$pick): ?>
      <div class="empty"><h3>Подходящего блюда нет</h3>
        <p>Отмечено <?= count($have) ?> <?= plural(count($have), ['продукт','продукта','продуктов']) ?>. Добавьте ещё несколько позиций — например, яйцо, муку или картофель.</p>
        <a class="btn btn-solid" href="<?= e(base_url('random.php')) ?>">Дополнить список</a></div>
    <?php else: $c = categories()[$pick['category']]; ?>
      <div class="panel-head"><h2>Вам подойдёт</h2>
        <span class="tag tag-ok">подходящих вариантов: <?= $candidates ?></span></div>
      <div class="pick-result">
        <div class="recipe-glyph" style="--tint:<?= $c['tint'] ?>;--ink:<?= $c['ink'] ?>"><?= dish_icon($pick['category']) ?></div>
        <div>
          <h2 style="font-size:28px;margin-bottom:8px"><a href="<?= e(base_url('recipe.php?id=' . (int)$pick['id'])) ?>"><?= e($pick['title']) ?></a></h2>
          <p class="lead" style="margin:0 0 12px"><?= e($pick['description']) ?></p>
          <div class="meta-row">
            <span class="tag"><?= e($c['n']) ?></span>
            <span class="tag"><?= icon('clock', 13) ?> <?= (int)$pick['minutes'] ?> мин</span>
            <span class="tag"><?= e($pick['difficulty']) ?></span>
            <span class="tag"><?= star_bar((float)$pick['rating_avg'], 13) ?> <?= number_format((float)$pick['rating_avg'], 1, '.', '') ?></span>
          </div>
          <?php if ($missing): ?>
            <p class="small" style="margin:12px 0 4px"><b>Докупить:</b></p>
            <div class="tags"><?php foreach ($missing as $m): ?><span class="tag tag-accent"><?= e($m['name']) ?></span><?php endforeach; ?></div>
          <?php else: ?>
            <div class="note" style="margin-top:12px">Всё необходимое у вас уже есть.</div>
          <?php endif; ?>
          <div class="btn-row" style="margin-top:16px">
            <a class="btn btn-solid" href="<?= e(base_url('recipe.php?id=' . (int)$pick['id'])) ?>">Открыть рецепт</a>
            <a class="btn btn-quiet" href="<?= e(base_url('random.php?pick=1')) ?>"><?= icon('dice', 15) ?> Другой вариант</a>
            <a class="btn btn-quiet" href="<?= e(base_url('random.php')) ?>">Изменить продукты</a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>Что есть дома</h2>
    <span class="tag">отмечено: <b id="pantry-count">0</b></span>
    <p class="sub">Отметьте продукты — подбор сохранится на время сеанса</p></div>

  <div class="field" style="max-width:340px"><label for="pantry-search">Поиск продукта</label>
    <input id="pantry-search" type="text" placeholder="например, творог"></div>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div id="pantry">
      <?php foreach ($kinds as $kind => $label): if (empty($groups[$kind])) continue; ?>
        <div class="pantry-group" data-group="<?= e($kind) ?>">
          <h3><?= e($label) ?> <button type="button" class="btn btn-quiet btn-sm" data-group-toggle="<?= e($kind) ?>">все / снять</button></h3>
          <div class="pantry">
            <?php foreach ($groups[$kind] as $i): ?>
              <label><input type="checkbox" name="have[]" value="<?= (int)$i['id'] ?>"<?= in_array((int)$i['id'], $have, true) ? ' checked' : '' ?>><span><?= e($i['name']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="pantry-bar">
      <button class="btn btn-solid" type="submit" id="pantry-submit"><?= icon('dice', 16) ?> Подобрать блюдо</button>
      <a class="btn btn-quiet" href="<?= e(base_url('random.php?clear=1')) ?>">Очистить</a>
      <span class="muted small">Базовые продукты учитываются автоматически</span>
    </div>
  </form>
</section>
<?php page_bottom(); ?>