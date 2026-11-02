<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Главная | МЭТЧ</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/public.css">
</head>

<body>
    <?php
    $basePath = '.';
    $activePublicPage = 'home';
    require __DIR__ . '/modules/icons.php';
    require __DIR__ . '/modules/public-header.php';
    ?>

    <main class="main-content">

        <!-- Первый рекламный блок -->
        <section class="hero" id="promo">
            <div class="hero__inner">

                <!-- Левая группа карточек -->
                <div class="hero__cards hero__cards--left">

                    <article class="hero-card hero-card--cinema">
                        <img src="images/cinema.png" alt="Люди в кино" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--violet">
                            <img class="hero-card__icon" src="images/cinema-icon.svg" alt="">
                            <span>Кино</span>
                        </div>
                    </article>

                    <article class="hero-card hero-card--games">
                        <img src="images/games.png" alt="Люди играют в настольные игры" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--orange">
                            <img class="hero-card__icon" src="images/games-icon.svg" alt="">
                            <span>Настолки</span>
                        </div>
                    </article>

                    <article class="hero-card hero-card--walk">
                        <img src="images/walks.png" alt="Девушки гуляют с кофе" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--violet">
                            <img class="hero-card__icon" src="images/walks-icon.svg" alt="">
                            <span>Прогулки</span>
                        </div>
                    </article>

                </div>

                <!-- Центральная часть первого блока -->
                <div class="hero__content">

                    <div class="hero__title-block">
                        <svg class="hero__top-icon" aria-hidden="true">
                            <use href="#icon-users"></use>
                        </svg>

                        <h1 class="hero__title">
                            <span><span class="decor-letter">Н</span>айди людей</span>
                            <span class="hero__title-accent">
                                <b><span class="hero__title-word--orange">по</span> <span class="hero__title-word--violet">интересам</span></b>
                            </span>
                        </h1>
                    </div>

                    <p class="hero__text">
                        Кино, прогулки, настолки, концерты, кофе и десятки других общих тем —
                        знакомься через то, что тебе действительно нравится
                    </p>

                    <div class="hero__actions">
                        <a href="pages/register.php" class="hero__btn hero__btn--primary">Зарегистрироваться</a>
                        <a href="#about" class="hero__btn hero__btn--secondary">
                            <span>Как это работает</span>
                            <svg class="hero__btn-arrow-icon" aria-hidden="true">
                                <use href="#icon-arrow-right"></use>
                            </svg>
                        </a>
                    </div>

                    <!-- Нижняя декоративная плашка с интересами -->
                    <div class="hero__interests">
                        <div class="hero__interest-icon hero__interest-icon--cream">
                            <img class="hero__interest-svg" src="images/color-cinema-icon.png" alt="">
                        </div>

                        <div class="hero__interest-icon hero__interest-icon--pink">
                            <img class="hero__interest-svg" src="images/color-games-icon.png" alt="">
                        </div>

                        <div class="hero__interest-icon hero__interest-icon--cream">
                            <img class="hero__interest-svg" src="images/color-music-icon.png" alt="">
                        </div>

                        <div class="hero__interest-icon hero__interest-icon--pink">
                            <img class="hero__interest-svg" src="images/color-coffee-icon.png" alt="">
                        </div>

                        <div class="hero__interest-icon hero__interest-icon--pink">
                            <img class="hero__interest-svg" src="images/color-walks-icon.png" alt="">
                        </div>

                        <div class="hero__interest-icon hero__interest-icon--cream">
                            <img class="hero__interest-svg" src="images/color-art-icon.png" alt="">
                        </div>
                    </div>

                    <p class="hero__note">Общие интересы — лучшая компания!</p>
                </div>

                <!-- Правая группа карточек -->
                <div class="hero__cards hero__cards--right">

                    <article class="hero-card hero-card--concert">
                        <img src="images/concerts.png" alt="Люди на концерте" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--orange">
                            <img class="hero-card__icon" src="images/music-icon.svg" alt="">
                            <span>Концерты</span>
                        </div>
                    </article>

                    <article class="hero-card hero-card--coffee">
                        <img src="images/coffee.png" alt="Девушки пьют кофе" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--violet">
                            <img class="hero-card__icon" src="images/coffee-icon.svg" alt="">
                            <span>Кофе</span>
                        </div>
                    </article>

                    <article class="hero-card hero-card--art">
                        <img src="images/arts.png" alt="Девушка рисует картину" class="hero-card__img">
                        <div class="hero-card__tag hero-card__tag--orange">
                            <img class="hero-card__icon" src="images/art-icon.svg" alt="">
                            <span>Искусство</span>
                        </div>
                    </article>

                </div>

            </div>
        </section>

        <!-- Второй блок: описание сервиса -->
        <section class="about" id="about">
            <div class="about__container">

                <!-- Левая большая карточка -->
                <div class="about__main-card">
                    <div class="about__text-block">
                        <h2 class="about__title">
                            <span class="decor-letter">Ч</span>то такое
                            <span class="about__brand">
                                <span class="decor-letter">М</span><span class="decor-letter">Э</span><span class="decor-letter">Т</span><span class="decor-letter">Ч</span>
                            </span>
                        </h2>


                        <p class="about__text">
                            МЭТЧ — это сервис, где люди находят компанию по интересам.
                            Для прогулок, кофе, мероприятий и душевного общения рядом,
                            когда и где вам удобно.
                        </p>
                    </div>

                    <img src="images/about-people.png" alt="Люди общаются" class="about__illustration">
                </div>

                <!-- Правая большая карточка с преимуществами -->
                <div class="about__features">

                    <article class="about-feature">
                        <div class="about-feature__icon about-feature__icon--violet">
                            <img class="about-feature__icon-svg" src="images/about-users.svg" alt="">
                        </div>
                        <div class="about-feature__content">
                            <h3>Люди с похожими интересами</h3>
                            <p>Находите тех, кто разделяет ваши увлечения и взгляды.</p>
                        </div>
                    </article>

                    <article class="about-feature">
                        <div class="about-feature__icon about-feature__icon--orange">
                            <img class="about-feature__icon-svg" src="images/about-places.svg" alt="">
                        </div>
                        <div class="about-feature__content">
                            <h3>Встречи в удобных местах</h3>
                            <p>Выбирайте локации рядом с вами — кафе, парки, выставки и не только.</p>
                        </div>
                    </article>

                    <article class="about-feature">
                        <div class="about-feature__icon about-feature__icon--violet">
                            <img class="about-feature__icon-svg" src="images/about-chat.svg" alt="">
                        </div>
                        <div class="about-feature__content">
                            <h3>Комфортное общение</h3>
                            <p>Лёгкое знакомство, уважение и безопасная атмосфера.</p>
                        </div>
                    </article>

                    <article class="about-feature">
                        <div class="about-feature__icon about-feature__icon--orange">
                            <img class="about-feature__icon-svg" src="images/about-impressions.svg" alt="">
                        </div>
                        <div class="about-feature__content">
                            <h3>Новые впечатления</h3>
                            <p>Открывайте новое вместе и создавайте приятные воспоминания.</p>
                        </div>
                    </article>

                </div>

            </div>
        </section>

        <!-- Блок с историями пользователей -->
        <section class="stories" id="stories">
            <div class="stories__container">

                <div class="stories__head">
                    <h2 class="stories__title">
                        <span class="decor-letter">И</span>стории <span class="stories__brand decor-letter">МЭТЧ</span>
                    </h2>
                    <p class="stories__subtitle">
                        Реальные люди. Настоящие знакомства. Новые друзья рядом и онлайн.
                    </p>
                </div>

                <div class="stories-slider">

                    <button class="stories-slider__arrow stories-slider__arrow--prev" type="button" aria-label="Предыдущая история">
                        <svg aria-hidden="true">
                            <use href="#icon-arrow-right"></use>
                        </svg>
                    </button>

                    <div class="stories-slider__viewport">
                        <div class="stories-slider__track">

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Anna-profilepic.png" alt="Анна" class="story-card__avatar">
                                    <div>
                                        <h3>Анна</h3>
                                        <p>Санкт-Петербург</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Нашла классную компанию через МЭТЧ и теперь мы вместе ходим на концерты и открываем новые группы!»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/concerts.png" alt="Концерт" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--violet">Концерт</span>
                                </div>
                            </article>

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Mikhail-profilepic.png" alt="Михаил" class="story-card__avatar">
                                    <div>
                                        <h3>Михаил</h3>
                                        <p>Казань</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Переехал в новый город и не знал, с чего начать. Через МЭТЧ нашёл ребят — теперь вместе гуляем и изучаем город.»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/walks.png" alt="Прогулка по городу" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--orange">Прогулка</span>
                                </div>
                            </article>

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Ekaterina-profilepic.png" alt="Екатерина" class="story-card__avatar">
                                    <div>
                                        <h3>Екатерина</h3>
                                        <p>Новосибирск</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Люблю уютные кофейни и душевные разговоры. В МЭТЧ нашла людей, с которыми приятно просто быть собой.»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/coffee.png" alt="Встреча в кофейне" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--violet">Кофе</span>
                                </div>
                            </article>

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Dasha-profilepic.png" alt="Даша" class="story-card__avatar">
                                    <div>
                                        <h3>Даша</h3>
                                        <p>Онлайн</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Я не всегда хочу встречаться офлайн, но люблю играть по вечерам. Через МЭТЧ нашла компанию для онлайн-игр и общения в голосе.»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/gaming.png" alt="Онлайн-игры с друзьями" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--orange">Онлайн-игры</span>
                                </div>
                            </article>

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Lera-profilepic.png" alt="Лера" class="story-card__avatar">
                                    <div>
                                        <h3>Лера</h3>
                                        <p>Москва</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Долго хотела сходить на выставку, но одной было скучно. В итоге нашла человека с таким же интересом к искусству.»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/arts.png" alt="Выставка и искусство" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--violet">Искусство</span>
                                </div>
                            </article>

                            <article class="story-card">
                                <div class="story-card__user">
                                    <img src="images/Igor-profilepic.png" alt="Игорь" class="story-card__avatar">
                                    <div>
                                        <h3>Игорь</h3>
                                        <p>Екатеринбург</p>
                                    </div>
                                </div>
                                <p class="story-card__text">
                                    «Мы собрались на вечер настолок почти случайно, а теперь встречаемся каждую неделю. Очень тёплая компания получилась.»
                                </p>
                                <div class="story-card__photo-wrap">
                                    <img src="images/games.png" alt="Настольные игры" class="story-card__photo">
                                    <span class="story-card__tag story-card__tag--orange">Настолки</span>
                                </div>
                            </article>

                        </div>
                    </div>

                    <button class="stories-slider__arrow stories-slider__arrow--next" type="button" aria-label="Следующая история">
                        <svg aria-hidden="true">
                            <use href="#icon-arrow-right"></use>
                        </svg>
                    </button>

                    <div class="stories-slider__dots" aria-label="Навигация по историям"></div>

                </div>
            </div>
        </section>

    </main>

    <?php require __DIR__ . '/modules/public-footer.php'; ?>

    <script src="js/public.js"></script>
</body>

</html>