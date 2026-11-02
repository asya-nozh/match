<?php
$basePath = '..';
$pageTitle = 'Обратная связь';
$metaDescription = 'Страница обратной связи веб-приложения Мэтч. Отзывы, предложения и сообщения об ошибках.';
$heroBadge = 'Обратная связь';
$heroTitle = 'Расскажите, что думаете о «Мэтч»';
$heroText = 'Нам важно получать отзывы пользователей, чтобы делать веб-приложение удобнее, понятнее и безопаснее для общения.';
$heroIcon = '✉';

ob_start();
?>
<section class="info-card info-feedback">
<div class="info-feedback-top">
<div class="info-feedback-icon">💬</div>
<div>
<h2>Ваше мнение помогает нам развиваться</h2>
<p>Если вам понравился наш сайт, вы заметили ошибку, хотите оставить отзыв или предложить идею для улучшения веб-приложения, напишите нам на почту.</p>
<p>Мы внимательно рассматриваем предложения по интерфейсу, работе профиля, ленты, чатов, связей, мест и другим разделам сервиса.</p>
</div>
</div>

<div class="info-feedback-list">
<div class="info-feedback-item">
<h3>Отзывы</h3>
<p>Расскажите, что вам понравилось в веб-приложении и какие функции оказались полезными.</p>
</div>
<div class="info-feedback-item">
<h3>Предложения</h3>
<p>Напишите, какие разделы, возможности или улучшения вы хотели бы видеть в «Мэтч».</p>
</div>
<div class="info-feedback-item">
<h3>Ошибки</h3>
<p>Сообщите, если заметили некорректную работу страницы, кнопки, формы или другого элемента.</p>
</div>
</div>

<div class="info-contact">
<p>Для отзывов, предложений и замечаний используйте электронную почту:</p>
<a class="info-email" href="mailto:feedback@my-match.ru">feedback@my-match.ru</a>
</div>
</section>
<?php
$pageContent = ob_get_clean();
require_once __DIR__ . '/../modules/info-page-layout.php';
?>