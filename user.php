<?php
require_once __DIR__ . '/inc/ui.php';
$id = (int)($_GET['id'] ?? 0);
$u = q1('SELECT id,name,login,bio,created_at,role FROM users WHERE id = :id AND status = "active"', [':id' => $id]);
if (!$u) {
    http_response_code(404);
    page_top('Профиль не найден', 'Автор не найден.', 'recipes');
    echo '<div class="empty"><h3>Профиль не найден</h3><a class="btn btn-solid" href="' . e(base_url('recipes.php')) . '">В каталог</a></div>';
    page_bottom(); exit;
}
$stats = author_stats($id);
$rows = q('SELECT id,title,category,minutes,difficulty,rating_avg,rating_count,likes_count FROM recipes WHERE author_id = :a AND status = "published" ORDER BY rating_avg DESC', [':a' => $id]);
$liked = liked_ids();

page_top($u['name'], 'Профиль автора: рецепты, рейтинг и лайки.', 'recipes');
?>
<section class="page-head">
  <div style="display:flex;gap:16px;align-items:center">
    <div class="avatar"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
    <div><p class="eyebrow">Автор</p><h1 style="font-size:32px"><?= e($u['name']) ?></h1>
      <p class="muted small">@<?= e($u['login']) ?> · с <?= e(dt($u['created_at'], 'd.m.Y')) ?></p></div>
  </div>
  <?php if ($u['bio']): ?><p class="lead"><?= e($u['bio']) ?></p><?php endif; ?>
</section>

<div class="stat-grid" style="margin-bottom:20px">
  <div class="stat"><b><?= number_format($stats['rating'], 1, '.', '') ?></b><span>рейтинг автора</span></div>
  <div class="stat"><b><?= $stats['recipes'] ?></b><span><?= plural($stats['recipes'], ['рецепт','рецепта','рецептов']) ?></span></div>
  <div class="stat"><b><?= $stats['likes'] ?></b><span>лайков получено</span></div>
  <div class="stat"><b><?= $stats['votes'] ?></b><span>оценок читателей</span></div>
</div>

<section class="panel">
  <div class="panel-head"><h2>Рецепты автора</h2></div>
  <?php if (!$rows): ?><p class="muted small">Опубликованных рецептов пока нет.</p>
  <?php else: ?><div class="grid"><?php foreach ($rows as $r) echo recipe_card($r, $liked); ?></div><?php endif; ?>
</section>
<?php page_bottom(); ?>