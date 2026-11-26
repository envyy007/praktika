<?php
require_once __DIR__ . '/inc/ui.php';
audit('logout');
logout_user();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
flash('ok', 'Вы вышли из аккаунта.');
redirect('index.php');