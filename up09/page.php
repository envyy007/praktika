<?php
require_once __DIR__ . '/inc/ui.php';
$slug = (string)($_GET['slug'] ?? 'about');
$p = q1('SELECT * FROM pages WHERE slug = :s AND published = 1', [':s' => $slug]);
if (!$p) {
    http_response_code(404);
    page_top('Страница не найдена', 'Раздел отсутствует.', '');
    echo '<div class="empty"><h3>Страница не найдена</h3><a class="btn btn-solid" href="' . e(base_url('index.php')) . '">На главную</a></div>';
    page_bottom(); exit;
}
page_top($p['seo_title'] ?: $p['title'], $p['seo_desc'], '');
?>
<section class="page-head"><p class="eyebrow">Раздел</p><h1><?= e($p['title']) ?></h1></section>
<section class="panel"><div class="prose"><?= $p['body'] ?></div></section>
<?php page_bottom(); ?>