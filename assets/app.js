/* Фильтрация каталога и расписания, AJAX-обновление, лайки и оценки. */
(function () {
  'use strict';
  var cfg = window.TARELKA || { base: '/', csrf: '', logged: false };
  var $ = window.jQuery;

  function api(action, data, method) {
    var url = cfg.base + 'api.php?action=' + encodeURIComponent(action);
    if (method === 'POST') {
      var body = new URLSearchParams(data || {});
      body.set('csrf', cfg.csrf);
      return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    var qs = new URLSearchParams(data || {}).toString();
    return fetch(url + (qs ? '&' + qs : ''), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function reveal() {
    var els = document.querySelectorAll('.reveal:not(.in)');
    if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: .06 });
    els.forEach(function (e, i) { e.style.setProperty('--d', (i % 6) * 45 + 'ms'); io.observe(e); });
  }

  /* ---- каталог: фильтр без перезагрузки ---- */
  function initCatalog() {
    var box = document.getElementById('catalog-grid');
    var form = document.getElementById('catalog-filters');
    if (!box || !form) return;
    var counter = document.getElementById('catalog-count');
    var status = document.getElementById('catalog-status');
    var timer = null, lastReq = 0;

    function params() {
      var f = new FormData(form), o = {};
      f.forEach(function (v, k) { if (String(v) !== '') o[k] = v; });
      var chip = document.querySelector('.chips .chip.on[data-cat]');
      if (chip) o.cat = chip.dataset.cat;
      return o;
    }
    function load() {
      var id = ++lastReq;
      if (status) status.textContent = 'Обновление…';
      box.style.opacity = '.5';
      api('recipes', params()).then(function (res) {
        if (id !== lastReq) return;
        box.style.opacity = '1';
        if (!res.ok) { if (status) status.textContent = 'Не удалось обновить список'; return; }
        box.innerHTML = res.html || '<div class="empty"><h3>Ничего не найдено</h3><p>Измените запрос или сбросьте фильтры.</p></div>';
        if (counter) counter.textContent = res.total;
        if (status) status.textContent = '';
        bindLikes(box); reveal();
        var url = new URL(location.href);
        var p = params();
        ['q', 'cat', 'sort'].forEach(function (k) { if (p[k]) url.searchParams.set(k, p[k]); else url.searchParams.delete(k); });
        history.replaceState(null, '', url.toString());
      }).catch(function () {
        box.style.opacity = '1';
        if (status) status.textContent = 'Ошибка сети, показаны прежние данные';
      });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
    form.addEventListener('change', load);
    form.addEventListener('input', function (e) {
      if (e.target.name !== 'q') return;
      clearTimeout(timer); timer = setTimeout(load, 320);
    });
    document.querySelectorAll('.chips .chip[data-cat]').forEach(function (chip) {
      chip.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('.chips .chip[data-cat]').forEach(function (c) { c.classList.remove('on'); });
        chip.classList.add('on');
        load();
      });
    });
  }

  /* ---- расписание: фильтр и AJAX ---- */
  function initSchedule() {
    var box = document.getElementById('schedule-box');
    var form = document.getElementById('schedule-filters');
    if (!box || !form) return;
    var status = document.getElementById('schedule-status');
    function load() {
      var f = new FormData(form), o = {};
      f.forEach(function (v, k) { if (String(v) !== '') o[k] = v; });
      if (status) status.textContent = 'Загрузка…';
      if ($) {
        $.ajax({ url: cfg.base + 'api.php', data: Object.assign({ action: 'schedule' }, o), dataType: 'json', timeout: 8000 })
          .done(function (res) { render(res); })
          .fail(function (x, s) { if (status) status.textContent = 'Ошибка загрузки: ' + s; });
      } else {
        api('schedule', o).then(render).catch(function () { if (status) status.textContent = 'Ошибка сети'; });
      }
    }
    function render(res) {
      if (!res.ok) { if (status) status.textContent = 'Не удалось загрузить расписание'; return; }
      box.innerHTML = res.html;
      if (status) status.textContent = res.total ? '' : 'Занятий по фильтру нет';
      reveal();
    }
    form.addEventListener('change', load);
    form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
  }

  /* ---- лайки ---- */
  function bindLikes(scope) {
    (scope || document).querySelectorAll('[data-like]').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!cfg.logged) { location.href = cfg.base + 'login.php'; return; }
        api('like', { recipe_id: btn.dataset.like }, 'POST').then(function (res) {
          if (!res.ok) return;
          btn.classList.toggle('on', res.liked);
          btn.setAttribute('aria-pressed', res.liked ? 'true' : 'false');
          document.querySelectorAll('[data-likes="' + btn.dataset.like + '"] b').forEach(function (b) { b.textContent = res.count; });
          var big = document.getElementById('like-count');
          if (big && big.dataset.recipe === btn.dataset.like) big.textContent = res.count;
        });
      });
    });
  }

  /* ---- оценка ---- */
  function initRating() {
    var form = document.getElementById('rate-form');
    if (!form) return;
    var out = document.getElementById('rate-result');
    form.querySelectorAll('button[data-score]').forEach(function (btn) {
      btn.addEventListener('mouseenter', function () {
        var s = +btn.dataset.score;
        form.querySelectorAll('button[data-score]').forEach(function (b) { b.classList.toggle('on', +b.dataset.score <= s); });
      });
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!cfg.logged) { location.href = cfg.base + 'login.php'; return; }
        api('rate', { recipe_id: form.dataset.recipe, score: btn.dataset.score }, 'POST').then(function (res) {
          if (!res.ok) return;
          if (out) out.textContent = 'Ваша оценка: ' + res.yours + '. Средняя: ' + res.avg + ' (' + res.count + ')';
          var avg = document.getElementById('avg-value');
          if (avg) avg.textContent = res.avg;
        });
      });
    });
    form.addEventListener('mouseleave', function () {
      var cur = +(form.dataset.mine || 0);
      form.querySelectorAll('button[data-score]').forEach(function (b) { b.classList.toggle('on', +b.dataset.score <= cur); });
    });
  }

  /* ---- подбор ингредиентов ---- */
  function initPantry() {
    var wrap = document.getElementById('pantry');
    if (!wrap) return;
    var counter = document.getElementById('pantry-count');
    var search = document.getElementById('pantry-search');
    function update() {
      var n = wrap.querySelectorAll('input:checked').length;
      if (counter) counter.textContent = n;
      var btn = document.getElementById('pantry-submit');
      if (btn) btn.disabled = n === 0;
    }
    wrap.addEventListener('change', update);
    update();
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        wrap.querySelectorAll('label').forEach(function (l) {
          l.style.display = !q || l.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
        });
        wrap.querySelectorAll('.pantry-group').forEach(function (g) {
          var visible = Array.prototype.some.call(g.querySelectorAll('label'), function (l) { return l.style.display !== 'none'; });
          g.style.display = visible ? '' : 'none';
        });
      });
    }
    document.querySelectorAll('[data-group-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var g = wrap.querySelector('[data-group="' + btn.dataset.groupToggle + '"]');
        if (!g) return;
        var boxes = g.querySelectorAll('input');
        var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !all; });
        update();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    reveal(); bindLikes(document); initCatalog(); initSchedule(); initRating(); initPantry();
  });
})();