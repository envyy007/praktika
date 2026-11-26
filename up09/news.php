<?php
require_once __DIR__ . '/inc/ui.php';
$slug = (string)($_GET['slug'] ?? '');
if ($slug !== '') {
    $n = q1('SELECT * FROM news WHERE slug = :s AND published = 1', [':s' => $slug]);
    if (!$n) { http_response_code(404); page_top('Материал не найден', 'Материал отсутствует.', 'news');
        echo '<div class="empty"><h3>Материал не найден</h3><a class="btn btn-solid" href="' . e(base_url('news.php')) . '">В журнал</a></div>'; page_bottom(); exit; }
    page_top($n['title'], mb_substr(strip_tags($n['body']), 0, 160), 'news');
    echo '<p class="eyebrow"><a href="' . e(base_url('news.php')) . '">Журнал</a> · ' . e(dt($n['created_at'], 'd.m.Y')) . '</p>';
    echo '<section class="page-head"><h1>' . e($n['title']) . '</h1></section>';
    echo '<section class="panel"><div class="prose">' . $n['body'] . '</div></section>';
    page_bottom(); exit;
}
$rows = q('SELECT * FROM news WHERE published = 1 ORDER BY created_at DESC');
page_top('Журнал', 'Новости каталога: обновления рецептов, подбор по продуктам и расписание занятий.', 'news');
?>
<section class="page-head"><p class="eyebrow">Журнал</p><h1>Что нового</h1></section>
<div class="rows">
  <?php foreach ($rows as $n): ?>
    <article class="row-item reveal"><div class="grow">
      <h3><a href="<?= e(base_url('news.php?slug=' . $n['slug'])) ?>"><?= e($n['title']) ?></a></h3>
      <p class="muted small"><?= e(dt($n['created_at'], 'd.m.Y')) ?></p>
      <p class="small"><?= e(mb_substr(strip_tags($n['body']), 0, 150)) ?>…</p></div></article>
  <?php endforeach; ?>
</div>
<?php page_bottom(); ?>