<?php
/** Каркас страниц, иконки, оформление, SEO, счётчик посещений. */

require_once __DIR__ . '/auth.php';

$ready = readiness();
if (!$ready['ok']) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Установка</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#FBF9F6;color:#17150F;display:grid;'
       . 'place-items:center;min-height:100vh;margin:0;padding:24px}.b{max-width:520px;background:#fff;'
       . 'border:1px solid #E7E2D8;border-radius:14px;padding:32px}h1{font-size:22px;margin:0 0 10px}'
       . 'p{color:#4A463C;line-height:1.6;font-size:14px}a{display:inline-block;margin-top:14px;'
       . 'background:#B4552B;color:#fff;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:600}'
       . '</style></head><body><div class="b"><h1>Каталог ещё не установлен</h1><p>'
       . htmlspecialchars($ready['reason']) . '</p><p>' . htmlspecialchars($ready['hint'])
       . '</p><a href="' . htmlspecialchars(base_url('install.php')) . '">Открыть установщик</a></div></body></html>';
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function plural(int $n, array $f): string
{
    $n = abs($n) % 100; $d = $n % 10;
    if ($n > 10 && $n < 20) return $f[2];
    if ($d > 1 && $d < 5) return $f[1];
    if ($d === 1) return $f[0];
    return $f[2];
}
function money(int $rub): string { return number_format($rub, 0, ',', ' ') . ' ₽'; }
function num($v): string
{
    $v = (float)$v;
    $t = $v >= 10 ? (string)round($v) : rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    return $t === '' ? '0' : $t;
}
function slugify(string $s): string
{
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
    'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h',
    'ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower(trim($s));
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'item';
}
function dt(string $sql, string $fmt = 'd.m.Y H:i'): string { return date($fmt, strtotime($sql)); }

function categories(): array
{
    return [
        'breakfast' => ['n' => 'завтраки', 'tint' => '#F3EEE4', 'ink' => '#8A6A2E'],
        'bakery'    => ['n' => 'выпечка',  'tint' => '#F5EBE2', 'ink' => '#A65B2B'],
        'soup'      => ['n' => 'супы',     'tint' => '#F1EEE6', 'ink' => '#7A6A3A'],
        'salad'     => ['n' => 'салаты',   'tint' => '#EBF0E7', 'ink' => '#4F6B45'],
        'main'      => ['n' => 'основные', 'tint' => '#F4EAE6', 'ink' => '#9C4A2E'],
        'pasta'     => ['n' => 'паста',    'tint' => '#F3EFE3', 'ink' => '#8A7434'],
        'dessert'   => ['n' => 'десерты',  'tint' => '#F5E9EC', 'ink' => '#9A4560'],
        'drink'     => ['n' => 'напитки',  'tint' => '#E9EEF2', 'ink' => '#3F5A6B'],
    ];
}
function cat_name(string $key): string { return categories()[$key]['n'] ?? $key; }

/** Минималистичная линейная иконка блюда по категории. */
function dish_icon(string $cat): string
{
    $g = [
        'breakfast' => '<circle cx="32" cy="34" r="16"/><path d="M32 24v10l7 4"/><path d="M14 16h36"/>',
        'bakery'    => '<path d="M14 40c0-12 8-20 18-20s18 8 18 20z"/><path d="M22 40v-8M32 40v-12M42 40v-8"/>',
        'soup'      => '<path d="M12 30h40a20 20 0 0 1-40 0z"/><path d="M26 20c0-4 4-4 4-8M36 20c0-4 4-4 4-8"/><path d="M8 52h48"/>',
        'salad'     => '<path d="M12 32h40a20 20 0 0 1-40 0z"/><path d="M24 26c4-8 12-10 16-6"/><circle cx="40" cy="24" r="4"/>',
        'main'      => '<path d="M18 24c8-6 22-6 28 2s2 20-8 22-24-2-24-12z"/><path d="M24 30h16M24 38h12"/>',
        'pasta'     => '<circle cx="32" cy="34" r="18"/><path d="M20 30c6 4 18 4 24 0M20 38c6 4 18 4 24 0"/>',
        'dessert'   => '<path d="M18 44h28l-4-18H22z"/><path d="M22 26c4-6 16-6 20 0"/><circle cx="32" cy="18" r="3"/>',
        'drink'     => '<path d="M22 18h20l-3 30H25z"/><path d="M22 28h20"/><path d="M40 22h6a4 4 0 0 1 0 8h-5"/>',
    ];
    return '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($g[$cat] ?? $g['main']) . '</svg>';
}

function icon(string $name, int $size = 18): string
{
    $p = [
        'heart'  => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
        'star'   => '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9Z"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/>',
        'check'  => '<path d="m4 12.5 5.5 5.5L20 6.5"/>',
        'x'      => '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
        'plus'   => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'dice'   => '<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="8.5" cy="8.5" r="1.3" fill="currentColor"/><circle cx="15.5" cy="15.5" r="1.3" fill="currentColor"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/>',
        'cart'   => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.6 12.4A2 2 0 0 0 9.6 17h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/>',
        'cal'    => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'chef'   => '<path d="M7 13a4 4 0 0 1-1-7.9 5 5 0 0 1 9.6-1A4 4 0 0 1 17 13v5H7Z"/><path d="M7 21h10"/>',
        'arrow'  => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'filter' => '<path d="M3 5h18M6 12h12M10 19h4"/>',
        'bell'   => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'edit'   => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'shield' => '<path d="M12 2 4 6v6c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V6Z"/>',
        'db'     => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function star_bar(float $rating, int $size = 14): string
{
    $pct = max(0, min(100, $rating / 5 * 100));
    $one = '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '"><path fill="currentColor" d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9Z"/></svg>';
    $row = str_repeat($one, 5);
    return '<span class="stars"><span class="stars-bg">' . $row . '</span><span class="stars-on" style="width:' . round($pct, 1) . '%">' . $row . '</span></span>';
}

/** Рейтинг автора: средняя оценка опубликованных рецептов и сумма лайков. */
function author_stats(int $authorId): array
{
    $row = q1('SELECT COUNT(*) AS n, COALESCE(AVG(rating_avg),0) AS r, COALESCE(SUM(likes_count),0) AS l, COALESCE(SUM(rating_count),0) AS v
               FROM recipes WHERE author_id = :a AND status = "published"', [':a' => $authorId]);
    return ['recipes' => (int)$row['n'], 'rating' => round((float)$row['r'], 2),
            'likes' => (int)$row['l'], 'votes' => (int)$row['v']];
}

function recipe_card(array $r, array $liked = []): string
{
    $c = categories()[$r['category']] ?? categories()['main'];
    $isLiked = in_array((int)$r['id'], $liked, true);
    $h  = '<article class="card" data-card="' . (int)$r['id'] . '">';
    $h .= '<a class="card-media" href="' . e(base_url('recipe.php?id=' . (int)$r['id'])) . '" style="--tint:' . $c['tint'] . ';--ink:' . $c['ink'] . '">';
    $h .= '<span class="card-glyph">' . dish_icon($r['category']) . '</span>';
    $h .= '<span class="card-cat">' . e($c['n']) . '</span></a>';
    $h .= '<div class="card-body">';
    $h .= '<a class="card-title" href="' . e(base_url('recipe.php?id=' . (int)$r['id'])) . '">' . e($r['title']) . '</a>';
    $h .= '<p class="card-meta">' . (int)$r['minutes'] . ' мин · ' . e($r['difficulty']) . '</p>';
    $h .= '<div class="card-foot">' . star_bar((float)$r['rating_avg'])
        . '<span class="rate-num">' . number_format((float)$r['rating_avg'], 1, '.', '') . '</span>'
        . '<span class="rate-cnt">(' . (int)$r['rating_count'] . ')</span>'
        . '<span class="card-likes" data-likes="' . (int)$r['id'] . '">' . icon('heart', 14) . '<b>' . (int)$r['likes_count'] . '</b></span>';
    $h .= '</div></div>';
    if (is_logged()) {
        $h .= '<button class="like-btn' . ($isLiked ? ' on' : '') . '" data-like="' . (int)$r['id'] . '" aria-pressed="' . ($isLiked ? 'true' : 'false') . '" title="Нравится">' . icon('heart', 16) . '</button>';
    }
    return $h . '</article>';
}

function liked_ids(): array
{
    $u = current_user();
    if (!$u) return [];
    return array_map('intval', array_column(q('SELECT recipe_id FROM recipe_likes WHERE user_id = :u', [':u' => (int)$u['id']]), 'recipe_id'));
}

function track_visit(): void
{
    $path = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    try {
        ex('INSERT INTO visits (day, path, hits) VALUES (CURDATE(), :p, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', [':p' => $path]);
    } catch (Throwable $e) { /* счётчик не критичен */ }
}

function page_top(string $title, string $desc, string $active = '', array $extraHead = []): void
{
    track_visit();
    $u = current_user();
    $nav = [
        'recipes'  => ['recipes.php', 'Рецепты'],
        'random'   => ['random.php', 'Случайное блюдо'],
        'lessons'  => ['lessons.php', 'Занятия'],
        'courses'  => ['courses.php', 'Курсы'],
        'news'     => ['news.php', 'Журнал'],
    ];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' — ' . e(SITE_NAME) . '</title>';
    echo '<meta name="description" content="' . e($desc) . '">';
    echo '<link rel="canonical" href="' . e(SITE_URL . '/' . current_path()) . '">';
    echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . e(SITE_NAME) . '">';
    echo '<meta property="og:title" content="' . e($title) . '"><meta property="og:description" content="' . e($desc) . '">';
    echo '<meta name="theme-color" content="#FBF9F6">';
    echo '<script>document.documentElement.className+=" js";</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . e(base_url('assets/app.css')) . '">';
    foreach ($extraHead as $tag) echo $tag;
    echo '</head><body>';

    echo '<header class="top"><div class="container top-in">';
    echo '<a class="logo" href="' . e(base_url('index.php')) . '"><span class="logo-mark"></span><span>' . e(SITE_NAME) . '</span></a>';
    echo '<nav class="nav">';
    foreach ($nav as $k => [$href, $label]) {
        echo '<a class="nav-link' . ($k === $active ? ' on' : '') . '" href="' . e(base_url($href)) . '">' . e($label) . '</a>';
    }
    echo '</nav><div class="top-right">';
    if ($u) {
        $n = unread_count();
        echo '<a class="icon-link' . ($active === 'account' ? ' on' : '') . '" href="' . e(base_url('account.php')) . '" title="Аккаунт">' . icon('user') . ($n ? '<i class="dot">' . $n . '</i>' : '') . '</a>';
        if (is_admin()) echo '<a class="btn btn-quiet btn-sm" href="' . e(base_url('admin/index.php')) . '">Панель</a>';
        echo '<a class="btn btn-quiet btn-sm" href="' . e(base_url('logout.php')) . '">Выйти</a>';
    } else {
        echo '<a class="btn btn-quiet btn-sm" href="' . e(base_url('login.php')) . '">Войти</a>';
        echo '<a class="btn btn-solid btn-sm" href="' . e(base_url('register.php')) . '">Регистрация</a>';
    }
    echo '</div></div></header><main class="container main">';
    foreach (take_flashes() as $f) {
        echo '<div class="flash flash-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
}

function page_bottom(): void
{
    echo '</main><footer class="foot"><div class="container foot-in">';
    echo '<div><a class="logo" href="' . e(base_url('index.php')) . '"><span class="logo-mark"></span><span>' . e(SITE_NAME) . '</span></a>';
    echo '<p class="muted small">' . e(SITE_TAG) . '</p></div>';
    echo '<nav class="foot-nav">';
    foreach ([['recipes.php','Рецепты'],['random.php','Случайное блюдо'],['lessons.php','Занятия'],['courses.php','Курсы'],
              ['news.php','Журнал'],['page.php?slug=about','О проекте'],['page.php?slug=contacts','Контакты']] as [$h,$l]) {
        echo '<a href="' . e(base_url($h)) . '">' . e($l) . '</a>';
    }
    echo '</nav><p class="muted small">© ' . date('Y') . ' ' . e(SITE_NAME) . '</p>';
    echo '</div></footer>';
    echo '<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>';
    echo '<script>window.TARELKA={base:' . json_encode(base_url()) . ',csrf:' . json_encode(csrf_token()) . ',logged:' . (is_logged() ? 'true' : 'false') . '};</script>';
    echo '<script src="' . e(base_url('assets/app.js')) . '"></script>';
    echo '</body></html>';
}

function field(string $name, string $label, string $type = 'text', string $value = '', string $error = '', string $hint = ''): string
{
    $h = '<div class="field' . ($error ? ' bad' : '') . '"><label for="f-' . e($name) . '">' . e($label) . '</label>';
    $h .= '<input id="f-' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '">';
    if ($hint) $h .= '<p class="hint">' . e($hint) . '</p>';
    if ($error) $h .= '<p class="err">' . e($error) . '</p>';
    return $h . '</div>';
}