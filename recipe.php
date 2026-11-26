<?php
require_once __DIR__ . '/inc/ui.php';

$id = (int)($_GET['id'] ?? 0);
$r = q1('SELECT r.*, u.name AS author_name, u.id AS author_id
         FROM recipes r LEFT JOIN users u ON u.id = r.author_id WHERE r.id = :id', [':id' => $id]);

if (!$r || ($r['status'] !== 'published' && !is_admin() && (!is_logged() || (int)current_user()['id'] !== (int)$r['author_id']))) {
    http_response_code(404);
    page_top('Рецепт не найден', 'Рецепт отсутствует в каталоге.', 'recipes');
    echo '<div class="empty"><h3>Рецепт не найден</h3><p>Возможно, он снят с публикации.</p><a class="btn btn-solid" href="' . e(base_url('recipes.php')) . '">В каталог</a></div>';
    page_bottom(); exit;
}

function portion($qty, string $unit, int $serv, int $base): string
{
    if ($qty === null) return $unit !== '' ? $unit : 'по вкусу';
    $v = (float)$qty * $serv / $base;
    $v = in_array($unit, ['шт','зубчик','веточка'], true) ? max(0.5, round($v * 2) / 2) : ($v >= 10 ? round($v) : round($v, 1));
    return num($v) . ($unit !== '' ? ' ' . $unit : '');
}

$u = current_user();
$base = max(1, (int)$r['servings']);
$serv = max(1, min(24, (int)($_GET['serv'] ?? $base)));
$ings = q('SELECT ri.qty, ri.unit, i.name, i.kind FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
           WHERE ri.recipe_id = :id ORDER BY ri.sort', [':id' => $id]);
$steps = q('SELECT n, body, minutes FROM recipe_steps WHERE recipe_id = :id ORDER BY n', [':id' => $id]);
$liked = liked_ids();
$isLiked = in_array($id, $liked, true);
$myScore = $u ? (int)scalar('SELECT score FROM recipe_ratings WHERE user_id = :u AND recipe_id = :r', [':u' => (int)$u['id'], ':r' => $id]) : 0;
$isFav = $u ? (bool)q1('SELECT 1 FROM favorites WHERE user_id = :u AND recipe_id = :r', [':u' => (int)$u['id'], ':r' => $id]) : false;
$related = q('SELECT id,title,category,minutes,difficulty,rating_avg,rating_count,likes_count FROM recipes
              WHERE status = "published" AND category = :c AND id <> :id ORDER BY rating_avg DESC LIMIT :l',
             [':c' => $r['category'], ':id' => $id, ':l' => 4]);
$author = $r['author_id'] ? author_stats((int)$r['author_id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && $u) {
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'rate') {
        $score = max(1, min(5, (int)($_POST['score'] ?? 0)));
        ex('INSERT INTO recipe_ratings (user_id,recipe_id,score,created_at) VALUES (:u,:r,:s,NOW()) ON DUPLICATE KEY UPDATE score = :s2',
           [':u' => (int)$u['id'], ':r' => $id, ':s' => $score, ':s2' => $score]);
        $agg = q1('SELECT AVG(score) a, COUNT(*) c FROM recipe_ratings WHERE recipe_id = :r', [':r' => $id]);
        ex('UPDATE recipes SET rating_avg = :a, rating_count = :c WHERE id = :id',
           [':a' => round((float)$agg['a'], 2), ':c' => (int)$agg['c'], ':id' => $id]);
        flash('ok', 'Оценка сохранена.');
        redirect('recipe.php?id=' . $id);
    }
    if ($do === 'unfav') {
        ex('DELETE FROM favorites WHERE user_id = :u AND recipe_id = :r', [':u' => (int)$u['id'], ':r' => $id]);
        flash('ok', 'Убрано из избранного.');
    } else {
        ex('INSERT INTO favorites (user_id,recipe_id,created_at) VALUES (:u,:r,NOW()) ON DUPLICATE KEY UPDATE created_at = created_at',
           [':u' => (int)$u['id'], ':r' => $id]);
        flash('ok', 'Добавлено в избранное.');
    }
    redirect('recipe.php?id=' . $id . '&serv=' . $serv);
}
ex('UPDATE recipes SET views = views + 1 WHERE id = :id', [':id' => $id]);

$c = categories()[$r['category']];

$ingStrings = array_map(function ($i) use ($serv, $base) {
    return $i['name'] . ' — ' . portion($i['qty'], $i['unit'], $serv, $base);
}, $ings);
$stepStrings = array_map(function ($s) {
    return ['@type' => 'HowToStep', 'text' => $s['body']];
}, $steps);

$ld = [
    '@context' => 'https://schema.org', '@type' => 'Recipe',
    'name' => $r['title'], 'description' => $r['description'],
    'recipeCategory' => $c['n'], 'totalTime' => 'PT' . (int)$r['minutes'] . 'M',
    'recipeYield' => $serv . ' порций',
    'recipeIngredient' => $ingStrings,
    'recipeInstructions' => $stepStrings,
];
if ((float)$r['rating_count'] > 0) {
    $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float)$r['rating_avg'], 'ratingCount' => (int)$r['rating_count']];
}

page_top($r['title'], $r['description'], 'recipes',
    ['<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>']);
?>
<p class="eyebrow"><a href="<?= e(base_url('recipes.php')) ?>">Каталог</a> · <?= e($c['n']) ?></p>
<div class="recipe-head">
  <div class="recipe-glyph" style="--tint:<?= $c['tint'] ?>;--ink:<?= $c['ink'] ?>"><?= dish_icon($r['category']) ?></div>
  <div>
    <h1><?= e($r['title']) ?></h1>
    <p class="lead"><?= e($r['description']) ?></p>
    <div class="meta-row">
      <span class="tag"><?= icon('clock', 13) ?> <?= (int)$r['minutes'] ?> мин</span>
      <span class="tag"><?= e($r['difficulty']) ?></span>
      <span class="tag"><?= (int)$r['kcal'] ?> ккал / порция</span>
      <span class="tag"><?= star_bar((float)$r['rating_avg'], 13) ?> <b id="avg-value"><?= number_format((float)$r['rating_avg'], 1, '.', '') ?></b> (<?= (int)$r['rating_count'] ?>)</span>
      <span class="tag"><?= icon('heart', 13) ?> <b id="like-count" data-recipe="<?= $id ?>"><?= (int)$r['likes_count'] ?></b></span>
      <?php if ($r['status'] !== 'published'): ?><span class="tag tag-warn"><?= e($r['status'] === 'pending' ? 'на модерации' : 'отклонён') ?></span><?php endif; ?>
    </div>
    <div class="btn-row">
      <?php if ($u): ?>
        <button class="btn btn-quiet<?= $isLiked ? ' on' : '' ?>" data-like="<?= $id ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>"><?= icon('heart', 16) ?> <?= $isLiked ? 'Нравится' : 'Мне нравится' ?></button>
        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="do" value="<?= $isFav ? 'unfav' : 'fav' ?>">
          <button class="btn btn-quiet" type="submit"><?= $isFav ? 'Убрать из избранного' : 'В избранное' ?></button></form>
      <?php else: ?>
        <a class="btn btn-solid" href="<?= e(base_url('login.php?back=' . urlencode('recipe.php?id=' . $id))) ?>">Войти, чтобы оценить</a>
      <?php endif; ?>
      <a class="btn btn-quiet" href="<?= e(base_url('random.php')) ?>"><?= icon('dice', 15) ?> Другое блюдо</a>
    </div>
  </div>
</div>

<div class="split-wide">
  <section class="panel">
    <div class="panel-head"><h2>Приготовление</h2><p class="sub"><?= count($steps) ?> <?= plural(count($steps), ['шаг','шага','шагов']) ?></p></div>
    <ol class="steps">
      <?php foreach ($steps as $s): ?>
        <li><span class="step-n"><?= (int)$s['n'] ?></span><p><?= e($s['body']) ?></p></li>
      <?php endforeach; ?>
    </ol>
    <?php if ($r['tip'] !== ''): ?><div class="note" style="margin-top:16px"><?= e($r['tip']) ?></div><?php endif; ?>

    <?php if ($u): ?>
      <hr class="hr">
      <div class="panel-head"><h3>Ваша оценка</h3></div>
      <form class="rate-form" id="rate-form" data-recipe="<?= $id ?>" data-mine="<?= $myScore ?>" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="do" value="rate">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <button type="submit" name="score" value="<?= $i ?>" data-score="<?= $i ?>" class="<?= $i <= $myScore ? 'on' : '' ?>" aria-label="Оценка <?= $i ?>">
            <svg viewBox="0 0 24 24" width="24" height="24"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9Z"/></svg></button>
        <?php endfor; ?>
        <span class="muted small" id="rate-result" style="margin-left:10px"><?= $myScore ? 'Ваша оценка: ' . $myScore : 'Поставьте оценку от 1 до 5' ?></span>
      </form>
    <?php endif; ?>
  </section>

  <aside>
    <section class="panel">
      <div class="panel-head"><h3>Ингредиенты</h3>
        <div class="stepper">
          <a href="<?= e(base_url('recipe.php?id=' . $id . '&serv=' . max(1, $serv - 1))) ?>" aria-label="Меньше">−</a>
          <b><?= $serv ?></b>
          <a href="<?= e(base_url('recipe.php?id=' . $id . '&serv=' . min(24, $serv + 1))) ?>" aria-label="Больше">+</a>
        </div>
        <p class="sub">Пересчёт на <?= $serv ?> <?= plural($serv, ['порцию','порции','порций']) ?>, базовый рецепт на <?= $base ?></p></div>
      <ul class="ing-list">
        <?php foreach ($ings as $i): ?>
          <li><span><?= e($i['name']) ?></span><span class="ing-qty"><?= e(portion($i['qty'], $i['unit'], $serv, $base)) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($author && $r['author_name']): ?>
      <section class="panel">
        <div class="panel-head"><h3>Автор</h3></div>
        <div class="row-item" style="border:none;padding:0">
          <div class="avatar"><?= e(mb_substr($r['author_name'], 0, 1)) ?></div>
          <div class="grow"><a href="<?= e(base_url('user.php?id=' . (int)$r['author_id'])) ?>"><b><?= e($r['author_name']) ?></b></a>
            <p class="muted small"><?= star_bar($author['rating'], 13) ?> <?= number_format($author['rating'], 1, '.', '') ?> ·
            <?= $author['recipes'] ?> <?= plural($author['recipes'], ['рецепт','рецепта','рецептов']) ?> · <?= $author['likes'] ?> лайков</p></div>
        </div>
      </section>
    <?php endif; ?>
  </aside>
</div>

<?php if ($related): ?>
<section class="panel">
  <div class="panel-head"><h2>Похожие рецепты</h2></div>
  <div class="grid"><?php foreach ($related as $x) echo recipe_card($x, $liked); ?></div>
</section>
<?php endif; ?>
<?php page_bottom(); ?>