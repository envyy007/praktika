<?php
/** JSON-эндпоинты: каталог, расписание, лайк, оценка, подбор блюда. */

require_once __DIR__ . '/inc/ui.php';
header('Content-Type: application/json; charset=utf-8');

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function need_csrf(): void
{
    if (!csrf_ok()) out(['ok' => false, 'error' => 'Сессия истекла'], 419);
}
function need_user(): array
{
    $u = current_user();
    if (!$u) out(['ok' => false, 'error' => 'Требуется вход'], 401);
    return $u;
}

try {
    switch ($action) {

    case 'recipes':
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
        $html = '';
        foreach ($rows as $r) $html .= recipe_card($r, $liked);
        out(['ok' => true, 'total' => count($rows), 'html' => $html]);

    case 'schedule':
        $chef = (int)($_GET['chef'] ?? 0);
        $day = (string)($_GET['day'] ?? '');
        $sql = 'SELECT s.*, c.name AS chef_name,
                (SELECT COUNT(*) FROM lesson_requests r WHERE r.slot_id = s.id AND r.status <> "declined") AS taken
                FROM slots s JOIN chefs c ON c.id = s.chef_id WHERE s.status = "open" AND s.starts_at >= NOW()';
        $p = [];
        if ($chef > 0) { $sql .= ' AND s.chef_id = :chef'; $p[':chef'] = $chef; }
        if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) { $sql .= ' AND DATE(s.starts_at) = :d'; $p[':d'] = $day; }
        $sql .= ' ORDER BY s.starts_at LIMIT :lim'; $p[':lim'] = 40;
        $rows = q($sql, $p);
        $html = '';
        if (!$rows) {
            $html = '<div class="empty"><h3>Занятий не найдено</h3><p>Выберите другого повара или другую дату.</p></div>';
        } else {
            $html = '<div class="rows">';
            foreach ($rows as $s) {
                $free = max(0, (int)$s['seats'] - (int)$s['taken']);
                $html .= '<div class="slot-row reveal"><div class="slot-date"><b>' . e(date('d', strtotime($s['starts_at']))) . '</b><span>'
                       . e(['','пн','вт','ср','чт','пт','сб','вс'][(int)date('N', strtotime($s['starts_at']))]) . ' · ' . e(date('H:i', strtotime($s['starts_at']))) . '</span></div>'
                       . '<div><h3>' . e($s['topic']) . '</h3><p class="muted small">' . e($s['chef_name']) . ' · ' . (int)$s['duration'] . ' мин · '
                       . ($free > 0 ? $free . ' ' . plural($free, ['место','места','мест']) : 'мест нет') . '</p></div>'
                       . '<div class="btn-row"><span class="tag ' . ((int)$s['price'] ? 'tag-accent' : 'tag-ok') . '">' . ((int)$s['price'] ? money((int)$s['price']) : 'бесплатно') . '</span>'
                       . '<a class="btn btn-quiet btn-sm" href="' . e(base_url('lessons.php?slot=' . (int)$s['id'])) . '">Записаться</a></div></div>';
            }
            $html .= '</div>';
        }
        out(['ok' => true, 'total' => count($rows), 'html' => $html]);

    case 'like':
        if (!$post) out(['ok' => false], 405);
        need_csrf(); $u = need_user();
        $rid = (int)($_POST['recipe_id'] ?? 0);
        if (!q1('SELECT id FROM recipes WHERE id = :id AND status = "published"', [':id' => $rid])) out(['ok' => false], 404);
        $has = q1('SELECT 1 FROM recipe_likes WHERE user_id = :u AND recipe_id = :r', [':u' => (int)$u['id'], ':r' => $rid]);
        if ($has) {
            ex('DELETE FROM recipe_likes WHERE user_id = :u AND recipe_id = :r', [':u' => (int)$u['id'], ':r' => $rid]);
            $liked = false;
        } else {
            ex('INSERT INTO recipe_likes (user_id,recipe_id,created_at) VALUES (:u,:r,NOW())', [':u' => (int)$u['id'], ':r' => $rid]);
            $liked = true;
            $author = (int)scalar('SELECT author_id FROM recipes WHERE id = :id', [':id' => $rid]);
            if ($author && $author !== (int)$u['id']) notify($author, 'Ваш рецепт «' . scalar('SELECT title FROM recipes WHERE id = :id', [':id' => $rid]) . '» понравился читателю.', 'recipe.php?id=' . $rid);
        }
        $count = (int)scalar('SELECT COUNT(*) FROM recipe_likes WHERE recipe_id = :r', [':r' => $rid]);
        ex('UPDATE recipes SET likes_count = :c WHERE id = :id', [':c' => $count, ':id' => $rid]);
        out(['ok' => true, 'liked' => $liked, 'count' => $count]);

    case 'rate':
        if (!$post) out(['ok' => false], 405);
        need_csrf(); $u = need_user();
        $rid = (int)($_POST['recipe_id'] ?? 0);
        $score = (int)($_POST['score'] ?? 0);
        if ($score < 1 || $score > 5) out(['ok' => false, 'error' => 'Оценка от 1 до 5'], 422);
        if (!q1('SELECT id FROM recipes WHERE id = :id AND status = "published"', [':id' => $rid])) out(['ok' => false], 404);
        ex('INSERT INTO recipe_ratings (user_id,recipe_id,score,created_at) VALUES (:u,:r,:s,NOW())
            ON DUPLICATE KEY UPDATE score = :s2, created_at = NOW()',
           [':u' => (int)$u['id'], ':r' => $rid, ':s' => $score, ':s2' => $score]);
        $agg = q1('SELECT AVG(score) AS a, COUNT(*) AS c FROM recipe_ratings WHERE recipe_id = :r', [':r' => $rid]);
        $avg = round((float)$agg['a'], 2);
        ex('UPDATE recipes SET rating_avg = :a, rating_count = :c WHERE id = :id', [':a' => $avg, ':c' => (int)$agg['c'], ':id' => $rid]);
        out(['ok' => true, 'avg' => number_format($avg, 1, '.', ''), 'count' => (int)$agg['c'], 'yours' => $score]);

    case 'ingredients':
        $q = trim((string)($_GET['q'] ?? ''));
        $sql = 'SELECT id,name,kind FROM ingredients';
        $p = [];
        if ($q !== '') { $sql .= ' WHERE name LIKE :q'; $p[':q'] = '%' . $q . '%'; }
        $sql .= ' ORDER BY kind,name LIMIT :lim'; $p[':lim'] = 200;
        out(['ok' => true, 'items' => q($sql, $p)]);

    default:
        out(['ok' => false, 'error' => 'Неизвестное действие'], 400);
    }
} catch (Throwable $e) {
    log_error($e->getMessage(), 'api:' . $action);
    out(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
}