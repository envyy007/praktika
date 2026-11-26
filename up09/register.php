<?php
require_once __DIR__ . '/inc/ui.php';
if (is_logged()) redirect('account.php');

$errors = []; $old = ['login' => '', 'email' => '', 'name' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) $old[$k] = trim((string)($_POST[$k] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    $pass2 = (string)($_POST['pass2'] ?? '');
    $agree = isset($_POST['agree']);
    if (!csrf_ok()) $errors['csrf'] = 'Сессия истекла.';
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $old['login'])) $errors['login'] = 'Латиница, цифры, подчёркивание, 3–32 символа.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Некорректная почта.';
    if (mb_strlen($old['name']) < 2) $errors['name'] = 'Укажите имя.';
    if (strlen($pass) < 8) $errors['pass'] = 'Пароль от 8 символов.';
    if ($pass !== $pass2) $errors['pass2'] = 'Пароли не совпадают.';
    if (!$agree) $errors['agree'] = 'Нужно согласие на обработку данных.';
    if (!isset($errors['login']) && q1('SELECT id FROM users WHERE login = :l', [':l' => $old['login']])) $errors['login'] = 'Логин занят.';
    if (!isset($errors['email']) && q1('SELECT id FROM users WHERE email = :e', [':e' => $old['email']])) $errors['email'] = 'Почта уже используется.';
    if (!$errors) {
        ex('INSERT INTO users (login,email,pass_hash,name,role,bio,created_at) VALUES (:l,:e,:p,:n,"user","",NOW())',
           [':l' => $old['login'], ':e' => $old['email'], ':p' => password_hash($pass, PASSWORD_DEFAULT), ':n' => $old['name']]);
        $u = q1('SELECT id,login,role FROM users WHERE login = :l', [':l' => $old['login']]);
        login_user($u);
        notify((int)$u['id'], 'Аккаунт создан. Можно добавить свой рецепт и записаться на занятие.', 'submit.php');
        audit('register', $old['login']);
        flash('ok', 'Аккаунт создан. Добро пожаловать!');
        redirect('account.php');
    }
    http_response_code(422);
}
page_top('Регистрация', 'Создание аккаунта: оценки, лайки, избранное и запись на занятия.', '');
?>
<section class="page-head"><p class="eyebrow">Аккаунт</p><h1>Регистрация</h1>
<p class="lead">Аккаунт нужен, чтобы ставить оценки, собирать избранное, отправлять рецепты и записываться на занятия.</p></section>
<section class="panel" style="max-width:620px">
  <?php if (isset($errors['csrf'])): ?><div class="flash flash-bad"><?= e($errors['csrf']) ?></div><?php endif; ?>
  <form class="form" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?= field('login', 'Логин', 'text', $old['login'], $errors['login'] ?? '', 'Латиница, цифры, подчёркивание') ?>
    <?= field('email', 'Почта', 'email', $old['email'], $errors['email'] ?? '') ?>
    <?= field('name', 'Имя', 'text', $old['name'], $errors['name'] ?? '') ?>
    <div class="split">
      <?= field('pass', 'Пароль', 'password', '', $errors['pass'] ?? '', 'Не короче 8 символов') ?>
      <?= field('pass2', 'Повтор пароля', 'password', '', $errors['pass2'] ?? '') ?>
    </div>
    <div class="field<?= isset($errors['agree']) ? ' bad' : '' ?>">
      <label class="check"><input type="checkbox" name="agree" value="1"><span>Согласен на обработку персональных данных</span></label>
      <?php if (isset($errors['agree'])): ?><p class="err"><?= e($errors['agree']) ?></p><?php endif; ?></div>
    <div class="btn-row"><button class="btn btn-solid" type="submit">Создать аккаунт</button>
      <a class="btn btn-quiet" href="<?= e(base_url('login.php')) ?>">У меня есть аккаунт</a></div>
  </form>
</section>
<?php page_bottom(); ?>