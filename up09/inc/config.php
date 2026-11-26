<?php
/** Настройки сайта и базы данных. */

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'tarelka';
const DB_USER = 'root';
const DB_PASS = '';

const SITE_NAME  = 'Тарелка';
const SITE_TAG   = 'рецепты, занятия с поварами и курсы';
const SITE_URL   = 'http://localhost/tarelka';
const SITE_MAIL  = 'hello@tarelka.ru';

/** Сколько недостающих ингредиентов допускается в подборе блюда. */
const MAX_MISSING = 2;