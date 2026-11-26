<?php
require_once __DIR__ . '/inc/ui.php';
$back = safe_back($_GET['back'] ?? $_POST['back'] ?? 'account.php', 'account.php');
if (is_logged()) redirect($back);

$error = ''; $login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    $tries = (int)($_SESSION['login_tries'] ?? 0);
    if (!csrf_ok()) $error = 'Сессия истекла, обновите страницу.';
    elseif ($tries >= 8) { $error = 'Слишком много попыток. Подождите немного.'; http_response_code(429); }
    elseif ($login === '' || $pass === '') $error = 'Введите логин и пароль.';
    else {
        $u = q1('SELECT * FROM users WHERE login = :l', [':l' => $login]);
        if (!$u || !password_verify($pass, $u['pass_hash']) || $u['status'] !== 'active') {
            $_SESSION['login_tries'] = $tries + 1;
            $error = 'Неверный логин или пароль.';
            log_error('Неудачный вход: ' . $login, 'login.php');
            http_response_code(401);
        } else {
            if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) {
                ex('UPDATE users SET pass_hash = :h WHERE id = :id', [':h' => password_hash($pass, PASSWORD_DEFAULT), ':id' => (int)$u['id']]);
            }
            unset($_SESSION['login_tries']);
            login_user($u);
            audit('login', $u['login']);
            flash('ok', 'Вы вошли как ' . $u['name'] . '.');
            redirect($back);
        }
    }
}
page_top('Вход', 'Вход в аккаунт кулинарного каталога.', '');
?>
<section class="page-head"><p class="eyebrow">Аккаунт</p><h1>Вход</h1>
<p class="lead">Оценки, лайки, избранное и заявки доступны после входа.</p></section>
<section class="panel" style="max-width:520px">
  <?php if ($error): ?><div class="flash flash-bad"><?= e($error) ?></div><?php endif; ?>
  <form class="form" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <?= field('login', 'Логин', 'text', $login) ?>
    <div class="field"><label for="f-pass">Пароль</label><input id="f-pass" name="pass" type="password" autocomplete="current-password"></div>
    <div class="btn-row"><button class="btn btn-solid" type="submit">Войти</button>
      <a class="btn btn-quiet" href="<?= e(base_url('register.php')) ?>">Создать аккаунт</a></div>
  </form>
</section>
<?php page_bottom(); ?>