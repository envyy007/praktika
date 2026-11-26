<?php
/** Расписание из XML с преобразованием через XSLT. */
require_once __DIR__ . '/inc/ui.php';

$xmlFile = __DIR__ . '/data/schedule.xml';
$xslFile = __DIR__ . '/data/schedule.xsl';
$html = ''; $error = null; $meta = [];

libxml_use_internal_errors(true);
if (!is_file($xmlFile) || !is_file($xslFile)) {
    $error = 'Расписание временно недоступно.';
} else {
    $xml = new DOMDocument();
    if (!$xml->load($xmlFile)) {
        $error = 'Расписание временно недоступно.';
        log_error('schedule.xml повреждён', 'schedule.php');
    } else {
        $meta = ['week' => $xml->documentElement->getAttribute('week'), 'updated' => $xml->documentElement->getAttribute('updated')];
        $xsl = new DOMDocument();
        if ($xsl->load($xslFile) && class_exists('XSLTProcessor')) {
            $proc = new XSLTProcessor();
            $proc->importStylesheet($xsl);
            $html = (string)$proc->transformToXml($xml);
        } else {
            $rowsHtml = '';
            foreach ($xml->getElementsByTagName('lesson') as $n) {
                $rowsHtml .= '<tr><td>' . e($n->getAttribute('date')) . '</td><td>' . e($n->getAttribute('day')) . '</td>'
                          . '<td>' . e($n->getAttribute('time')) . '</td><td>' . e($n->getAttribute('topic')) . '</td>'
                          . '<td>' . e($n->getAttribute('chef')) . '</td><td>' . (int)$n->getAttribute('duration') . '</td>'
                          . '<td>' . ((int)$n->getAttribute('price') ? money((int)$n->getAttribute('price')) : 'бесплатно') . '</td></tr>';
            }
            $html = '<div class="table-wrap"><table><thead><tr><th>Дата</th><th>День</th><th>Время</th><th>Тема</th><th>Повар</th><th>Мин</th><th>Стоимость</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>';
        }
    }
}
libxml_clear_errors();
if ($error) http_response_code(500);

page_top('Расписание занятий', 'Расписание онлайн-занятий с поварами: даты, темы, повара и стоимость.', 'lessons');
?>
<section class="page-head"><p class="eyebrow">Расписание</p><h1>Занятия по дням</h1>
<p class="lead">Общая таблица ближайших онлайн-встреч. Записаться можно на странице занятий.</p>
<div class="btn-row" style="margin-top:14px">
  <a class="btn btn-solid" href="<?= e(base_url('lessons.php')) ?>">Записаться на занятие</a>
  <button class="btn btn-quiet" type="button" onclick="window.print()">Печать</button>
</div></section>

<?php if ($error): ?>
  <div class="flash flash-bad"><?= e($error) ?></div>
<?php else: ?>
  <section class="panel">
    <div class="panel-head"><h2>Неделя <?= e($meta['week']) ?></h2><p class="sub">Обновлено <?= e($meta['updated']) ?></p></div>
    <div class="xml-out"><?= $html ?></div>
  </section>
<?php endif; ?>
<?php page_bottom(); ?>