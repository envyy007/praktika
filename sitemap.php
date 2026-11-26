<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/config.php';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$urls = ['', 'recipes.php', 'random.php', 'lessons.php', 'schedule.php', 'courses.php', 'news.php',
         'page.php?slug=about', 'page.php?slug=contacts', 'page.php?slug=moderation'];
foreach ($urls as $u) {
    echo '<url><loc>' . htmlspecialchars(SITE_URL . '/' . $u, ENT_QUOTES) . '</loc><changefreq>weekly</changefreq></url>';
}
try {
    foreach (q('SELECT id FROM recipes WHERE status = "published"') as $r) {
        echo '<url><loc>' . htmlspecialchars(SITE_URL . '/recipe.php?id=' . (int)$r['id'], ENT_QUOTES) . '</loc></url>';
    }
    foreach (q('SELECT slug FROM courses WHERE published = 1') as $c) {
        echo '<url><loc>' . htmlspecialchars(SITE_URL . '/course.php?slug=' . $c['slug'], ENT_QUOTES) . '</loc></url>';
    }
} catch (Throwable $e) { /* база недоступна */ }
echo '</urlset>';