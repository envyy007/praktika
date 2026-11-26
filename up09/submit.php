<?php
/** Отправка рецепта на модерацию. */
require_once __DIR__ . '/inc/ui.php';
require_login();

$u = current_user();
$errors = [];
$old = ['title' => '', 'category' => 'main', 'minutes' => '30', 'servings' => '2', 'difficulty' => 'легко',
        'kcal' => '0', 'description' => '', 'tip' => '', 'ingredients' => '', 'steps' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) $old[$k] = trim((string)($_POST[$k] ?? $v));
    if (!csrf_ok()) $errors['csrf'] = 'Сессия истекла, обновите страницу.';
    if (mb_strlen($old['title']) < 3) $errors['title'] = 'Название от 3 символов.';
    if (!isset(categories()[$old['category']])) $errors['category'] = 'Выберите категорию.';
    $min = (int)$old['minutes'];
    if ($min < 1 || $min > 600) $errors['minutes'] = 'Время от 1 до 600 минут.';
    $serv = (int)$old['servings'];
    if ($serv < 1 || $serv > 24) $errors['servings'] = 'Порции от 1 до 24.';
    if (mb_strlen($old['description']) < 10) $errors['description'] = 'Опишите блюдо хотя бы одним предложением.';

    $ingLines = array_values(array_filter(array_map('trim', explode("\n", $old['ingredients']))));
    $stepLines = array_values(array_filter(array_map('trim', explode("\n", $old['steps']))));
    if (count($ingLines) < 2) $errors['ingredients'] = 'Укажите минимум два ингредиента, каждый с новой строки.';
    if (count($stepLines) < 2) $errors['steps'] = 'Опишите минимум два шага, каждый с новой строки.';
    if (count($ingLines) > 30) $errors['ingredients'] = 'Не более 30 ингредиентов.';
    if (count($stepLines) > 20) $errors['steps'] = 'Не более 20 шагов.';

    if (!$errors) {
        $slug = slugify($old['title']);
        if (q1('SELECT id FROM recipes WHERE slug = :s', [':s' => $slug])) $slug .= '-' . substr((string)time(), -4);
        ex('INSERT INTO recipes (slug,title,category,minutes,servings,difficulty,kcal,description,tip,author_id,status,created_at)
            VALUES (:slug,:title,:cat,:min,:serv,:diff,:kcal,:descr,:tip,:a,"pending",NOW())', [
            ':slug' => $slug, ':title' => $old['title'], ':cat' => $old['category'], ':min' => $min, ':serv' => $serv,
            ':diff' => in_array($old['difficulty'], ['легко','средне','сложно'], true) ? $old['difficulty'] : 'легко',
            ':kcal' => max(0, min(3000, (int)$old['kcal'])), ':descr' => mb_substr($old['description'], 0, 300),
            ':tip' => mb_substr($old['tip'], 0, 300), ':a' => (int)$u['id'],
        ]);
        $rid = last_id();
        foreach ($ingLines as $i => $line) {
            $parts = preg_split('/\s*[—–-]\s*/u', $line, 2);
            $name = mb_strtolower(trim($parts[0]));
            $qty = null; $unit = '';
            if (isset($parts[1]) && preg_match('/^(\d+[.,]?\d*)\s*(.*)$/u', trim($parts[1]), $m)) {
                $qty = (float)str_replace(',', '.', $m[1]); $unit = trim($m[2]);
            } elseif (isset($parts[1])) { $unit = trim($parts[1]); }
            $ing = q1('SELECT id FROM ingredients WHERE name = :n', [':n' => $name]);
            if (!$ing) {
                ex('INSERT INTO ingredients (name,kind,unit) VALUES (:n,"basic",:u)', [':n' => mb_substr($name, 0, 80), ':u' => $unit ?: 'г']);
                $iid = last_id();
            } else { $iid = (int)$ing['id']; }
            ex('INSERT INTO recipe_ingredients (recipe_id,ingredient_id,qty,unit,is_optional,sort) VALUES (:r,:i,:q,:u,0,:s)',
               [':r' => $rid, ':i' => $iid, ':q' => $qty, ':u' => mb_substr($unit, 0, 20), ':s' => $i + 1]);
        }
        foreach ($stepLines as $i => $body) {
            ex('INSERT INTO recipe_steps (recipe_id,n,body,minutes) VALUES (:r,:n,:b,NULL)',
               [':r' => $rid, ':n' => $i + 1, ':b' => mb_substr($body, 0, 400)]);
        }
        foreach (q('SELECT id FROM users WHERE role IN ("admin","editor")') as $a) {
            notify((int)$a['id'], 'Новый рецепт на модерации: ' . $old['title'], 'admin/index.php?s=moderation');
        }
        audit('recipe.submit', 'recipe #' . $rid);
        flash('ok', 'Рецепт отправлен на модерацию. Статус появится в аккаунте.');
        redirect('account.php?tab=recipes');
    }
    http_response_code(422);
}

page_top('Добавить рецепт', 'Отправка собственного рецепта в каталог: ингредиенты, шаги и модерация.', 'recipes');
?>
<section class="page-head"><p class="eyebrow">Свой рецепт</p><h1>Добавить рецепт</h1>
<p class="lead">Рецепт появится в каталоге после проверки модератором. Обычно это занимает до суток.
Результат и комментарий придут в аккаунт.</p></section>

<?php if (isset($errors['csrf'])): ?><div class="flash flash-bad"><?= e($errors['csrf']) ?></div><?php endif; ?>

<section class="panel">
<form class="form" method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <?= field('title', 'Название блюда', 'text', $old['title'], $errors['title'] ?? '') ?>
  <div class="split">
    <div class="field<?= isset($errors['category']) ? ' bad' : '' ?>"><label for="f-category">Категория</label>
      <select id="f-category" name="category">
        <?php foreach (categories() as $k => $c): ?><option value="<?= e($k) ?>"<?= $old['category'] === $k ? ' selected' : '' ?>><?= e($c['n']) ?></option><?php endforeach; ?>
      </select><?php if (isset($errors['category'])): ?><p class="err"><?= e($errors['category']) ?></p><?php endif; ?></div>
    <div class="field"><label for="f-difficulty">Сложность</label>
      <select id="f-difficulty" name="difficulty">
        <?php foreach (['легко','средне','сложно'] as $d): ?><option<?= $old['difficulty'] === $d ? ' selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <div class="split">
    <?= field('minutes', 'Время, минут', 'number', $old['minutes'], $errors['minutes'] ?? '') ?>
    <?= field('servings', 'Порций', 'number', $old['servings'], $errors['servings'] ?? '') ?>
  </div>
  <?= field('kcal', 'Ккал на порцию', 'number', $old['kcal']) ?>
  <div class="field<?= isset($errors['description']) ? ' bad' : '' ?>"><label for="f-description">Короткое описание</label>
    <textarea id="f-description" name="description" rows="2" maxlength="300"><?= e($old['description']) ?></textarea>
    <?php if (isset($errors['description'])): ?><p class="err"><?= e($errors['description']) ?></p><?php endif; ?></div>
  <div class="field<?= isset($errors['ingredients']) ? ' bad' : '' ?>"><label for="f-ingredients">Ингредиенты</label>
    <textarea id="f-ingredients" name="ingredients" rows="7" placeholder="творог — 500 г&#10;яйцо — 1 шт&#10;сахар — 2 ст.л."><?= e($old['ingredients']) ?></textarea>
    <p class="hint">Каждый ингредиент с новой строки: название — количество единица</p>
    <?php if (isset($errors['ingredients'])): ?><p class="err"><?= e($errors['ingredients']) ?></p><?php endif; ?></div>
  <div class="field<?= isset($errors['steps']) ? ' bad' : '' ?>"><label for="f-steps">Шаги приготовления</label>
    <textarea id="f-steps" name="steps" rows="6" placeholder="Разотрите творог с яйцом и сахаром.&#10;Введите муку, сформируйте шайбы."><?= e($old['steps']) ?></textarea>
    <p class="hint">Каждый шаг с новой строки</p>
    <?php if (isset($errors['steps'])): ?><p class="err"><?= e($errors['steps']) ?></p><?php endif; ?></div>
  <?= field('tip', 'Совет (необязательно)', 'text', $old['tip']) ?>
  <div class="btn-row"><button class="btn btn-solid" type="submit">Отправить на модерацию</button>
    <a class="btn btn-quiet" href="<?= e(base_url('account.php')) ?>">Отмена</a></div>
</form>
</section>
<?php page_bottom(); ?>