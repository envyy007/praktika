<?php
/** Установка базы и наполнение каталога. */

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/seed.php';

$log = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db_server();
        foreach (sql_statements(file_get_contents(__DIR__ . '/sql/schema.sql')) as $s) { $pdo->exec($s); }
        $log[] = ['ok', 'Схема создана: 20 таблиц'];
        $pdo->exec('USE ' . DB_NAME);

        $accounts = [
            ['admin',  'admin12345',  'admin@tarelka.ru',  'Администратор каталога', 'admin'],
            ['chef',   'chef12345',   'chef@tarelka.ru',   'Ирина Ковалёва',         'chef'],
            ['reader', 'reader12345', 'reader@tarelka.ru', 'Елисей Пономарёв',       'user'],
        ];
        $st = $pdo->prepare('INSERT INTO users (login,email,pass_hash,name,role,bio,created_at) VALUES (:l,:e,:p,:n,:r,:b,NOW())');
        foreach ($accounts as [$l, $p, $e, $n, $r]) {
            $st->execute([':l'=>$l, ':e'=>$e, ':p'=>password_hash($p, PASSWORD_DEFAULT), ':n'=>$n, ':r'=>$r,
                          ':b'=>$r === 'user' ? 'Готовлю по выходным, люблю выпечку.' : '']);
        }
        $adminId = (int)$pdo->query('SELECT id FROM users WHERE login = "admin"')->fetchColumn();
        $log[] = ['ok', 'Созданы учётные записи: admin, chef, reader'];

        $ingIds = [];
        $st = $pdo->prepare('INSERT INTO ingredients (name,kind,unit) VALUES (:n,:k,:u)');
        foreach (seed_ingredients() as $kind => $names) {
            foreach ($names as $name) {
                $unit = in_array($name, ['яйцо','лук репчатый','морковь','картофель','огурцы','перец болгарский','авокадо','банан','лимон','лайм','яблоки','чеснок'], true) ? 'шт' : 'г';
                $st->execute([':n'=>$name, ':k'=>$kind, ':u'=>$unit]);
                $ingIds[$name] = (int)$pdo->lastInsertId();
            }
        }
        $log[] = ['ok', 'Справочник ингредиентов: ' . count($ingIds) . ' позиций'];

        $rSt = $pdo->prepare('INSERT INTO recipes (slug,title,category,minutes,servings,difficulty,kcal,description,tip,author_id,status,rating_avg,rating_count,likes_count,created_at,published_at)
                              VALUES (:slug,:title,:cat,:min,:serv,:diff,:kcal,:descr,:tip,:author,"published",:ra,:rc,:lc,NOW(),NOW())');
        $iSt = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id,ingredient_id,qty,unit,is_optional,sort) VALUES (:r,:i,:q,:u,:o,:s)');
        $sSt = $pdo->prepare('INSERT INTO recipe_steps (recipe_id,n,body,minutes) VALUES (:r,:n,:b,:m)');
        $lSt = $pdo->prepare('INSERT INTO recipe_likes (user_id,recipe_id,created_at) VALUES (:u,:r,NOW())');
        $vSt = $pdo->prepare('INSERT INTO recipe_ratings (user_id,recipe_id,score,created_at) VALUES (:u,:r,:s,NOW())');

        $seed = seed_recipes();
        $n = 0;
        foreach ($seed as $k => $row) {
            [$slug,$title,$cat,$min,$serv,$diff,$kcal,$descr,$tip,$ings,$steps] = $row;
            $rating = 4.2 + (($k * 7) % 8) / 10;
            $votes  = 12 + (($k * 13) % 90);
            $likes  = 4 + (($k * 11) % 60);
            $rSt->execute([':slug'=>$slug, ':title'=>$title, ':cat'=>$cat, ':min'=>$min, ':serv'=>$serv, ':diff'=>$diff,
                           ':kcal'=>$kcal, ':descr'=>$descr, ':tip'=>$tip, ':author'=>$adminId,
                           ':ra'=>round($rating, 2), ':rc'=>$votes, ':lc'=>$likes]);
            $rid = (int)$pdo->lastInsertId();
            foreach ($ings as $i => $ing) {
                [$name, $qty, $unit] = $ing;
                if (!isset($ingIds[$name])) { throw new RuntimeException('Неизвестный ингредиент: ' . $name); }
                $iSt->execute([':r'=>$rid, ':i'=>$ingIds[$name], ':q'=>$qty, ':u'=>$unit, ':o'=>0, ':s'=>$i + 1]);
            }
            foreach ($steps as $i => $body) {
                $sSt->execute([':r'=>$rid, ':n'=>$i + 1, ':b'=>$body, ':m'=>null]);
            }
            if ($k % 3 === 0) { $lSt->execute([':u'=>3, ':r'=>$rid]); }
            if ($k % 4 === 0) { $vSt->execute([':u'=>3, ':r'=>$rid, ':s'=>5]); }
            $n++;
        }
        $log[] = ['ok', 'Рецепты: ' . $n . ' шт. с ингредиентами и шагами'];

        $chefIds = [];
        $cSt = $pdo->prepare('INSERT INTO chefs (name,title,bio) VALUES (:n,:t,:b)');
        foreach (seed_chefs() as [$name, $title, $bio]) {
            $cSt->execute([':n'=>$name, ':t'=>$title, ':b'=>$bio]);
            $chefIds[] = (int)$pdo->lastInsertId();
        }
        $slSt = $pdo->prepare('INSERT INTO slots (chef_id,topic,starts_at,duration,seats,price,status) VALUES (:c,:t,:s,:d,:se,:p,"open")');
        foreach (seed_slots() as [$ci, $topic, $days, $time, $dur, $seats, $price]) {
            $slSt->execute([':c'=>$chefIds[$ci], ':t'=>$topic, ':s'=>date('Y-m-d', strtotime('+' . $days . ' day')) . ' ' . $time . ':00',
                            ':d'=>$dur, ':se'=>$seats, ':p'=>$price]);
        }
        $log[] = ['ok', 'Повара: ' . count($chefIds) . ', занятия: ' . count(seed_slots())];

        $coSt = $pdo->prepare('INSERT INTO courses (slug,title,level,lessons_count,hours,price,summary,chef_id,published) VALUES (:s,:t,:l,:n,:h,:p,:sum,:c,1)');
        $clSt = $pdo->prepare('INSERT INTO course_lessons (course_id,n,title,body) VALUES (:c,:n,:t,:b)');
        foreach (seed_courses() as [$slug,$title,$level,$cnt,$hours,$price,$summary,$ci,$lessons]) {
            $coSt->execute([':s'=>$slug, ':t'=>$title, ':l'=>$level, ':n'=>$cnt, ':h'=>$hours, ':p'=>$price,
                            ':sum'=>$summary, ':c'=>$chefIds[$ci]]);
            $cid = (int)$pdo->lastInsertId();
            foreach ($lessons as $i => [$lt, $lb]) { $clSt->execute([':c'=>$cid, ':n'=>$i + 1, ':t'=>$lt, ':b'=>$lb]); }
        }
        $log[] = ['ok', 'Курсы: ' . count(seed_courses()) . ' с материалами уроков'];

        $pSt = $pdo->prepare('INSERT INTO pages (slug,title,body,seo_title,seo_desc,published,updated_at) VALUES (:s,:t,:b,:st,:sd,1,NOW())');
        foreach (seed_pages() as [$slug,$title,$body,$stitle,$sdesc]) {
            $pSt->execute([':s'=>$slug, ':t'=>$title, ':b'=>$body, ':st'=>$stitle, ':sd'=>$sdesc]);
        }
        $nSt = $pdo->prepare('INSERT INTO news (slug,title,body,published,created_at) VALUES (:s,:t,:b,1,NOW())');
        foreach (seed_news() as [$slug,$title,$body]) { $nSt->execute([':s'=>$slug, ':t'=>$title, ':b'=>$body]); }
        $log[] = ['ok', 'Разделы и журнал наполнены'];

        // XML расписания
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElement('schedule');
        $root->setAttribute('updated', date('Y-m-d'));
        $root->setAttribute('week', date('W'));
        $xml->appendChild($root);
        $rows = $pdo->query('SELECT s.id, s.topic, s.starts_at, s.duration, s.seats, s.price, c.name
                             FROM slots s JOIN chefs c ON c.id = s.chef_id ORDER BY s.starts_at')->fetchAll();
        $days = [1=>'пн',2=>'вт',3=>'ср',4=>'чт',5=>'пт',6=>'сб',7=>'вс'];
        foreach ($rows as $r) {
            $ts = strtotime($r['starts_at']);
            $el = $xml->createElement('lesson');
            $el->setAttribute('id', 's' . $r['id']);
            $el->setAttribute('topic', $r['topic']);
            $el->setAttribute('chef', $r['name']);
            $el->setAttribute('day', $days[(int)date('N', $ts)]);
            $el->setAttribute('day_index', date('N', $ts));
            $el->setAttribute('date', date('Y-m-d', $ts));
            $el->setAttribute('time', date('H:i', $ts));
            $el->setAttribute('duration', (string)$r['duration']);
            $el->setAttribute('seats', (string)$r['seats']);
            $el->setAttribute('price', (string)$r['price']);
            $root->appendChild($el);
        }
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0775, true);
        $xml->save(__DIR__ . '/data/schedule.xml');
        $log[] = ['ok', 'Расписание выгружено в data/schedule.xml'];

        $done = true;
        $log[] = ['ok', 'Готово. Откройте главную страницу.'];
    } catch (Throwable $e) {
        $log[] = ['bad', 'Ошибка: ' . $e->getMessage()];
    }
}

$state = readiness(true);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Установка каталога</title>
<link rel="stylesheet" href="assets/app.css"></head><body>
<main class="container main" style="max-width:760px">
<div class="page-head"><p class="eyebrow">Установка</p><h1>Развёртывание каталога</h1>
<p class="lead">Создаётся база <?= htmlspecialchars(DB_NAME) ?>, таблицы, сорок рецептов, повара, занятия, курсы и разделы.</p></div>
<section class="panel">
  <div class="panel-head"><h2>Состояние</h2>
    <span class="tag <?= $state['ok'] ? 'tag-ok' : 'tag-warn' ?>"><?= $state['ok'] ? 'установлено' : 'требует установки' ?></span></div>
  <?php if (!$state['ok']): ?><div class="flash flash-warn"><?= htmlspecialchars($state['reason']) ?> <?= htmlspecialchars($state['hint']) ?></div><?php endif; ?>
  <form method="post"><button class="btn btn-solid" type="submit"><?= $state['ok'] ? 'Переустановить' : 'Установить' ?></button>
  <?php if ($state['ok']): ?> <a class="btn btn-quiet" href="index.php">Открыть сайт</a><?php endif; ?></form>
  <?php if ($log): ?>
    <div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>Шаг</th><th>Результат</th></tr></thead><tbody>
    <?php foreach ($log as $i => [$t, $m]): ?>
      <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($m) ?> <span class="tag <?= $t === 'ok' ? 'tag-ok' : 'tag-bad' ?>"><?= $t ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <div class="table-wrap" style="margin-top:18px"><table>
    <thead><tr><th>Логин</th><th>Пароль</th><th>Роль</th></tr></thead><tbody>
    <tr><td>admin</td><td>admin12345</td><td>администратор</td></tr>
    <tr><td>chef</td><td>chef12345</td><td>повар</td></tr>
    <tr><td>reader</td><td>reader12345</td><td>пользователь</td></tr>
  </tbody></table></div>
</section></main></body></html>