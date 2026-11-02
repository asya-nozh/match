<?php

declare(strict_types=1);

/**
 * Общие функции для скрытой обработки интересов из текста профиля.
 * В интерфейсе сайта слова AI/нейросеть не выводятся.
 * Лента сортируется по уже сохранённым интересам, а не вызывает API при каждой загрузке.
 */

if (!function_exists('ai_norm_text')) {
    function ai_norm_text(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('/[\x{00A0}\t\r\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('ai_debug_log')) {
    function ai_debug_log(string $message, array $context = []): void
    {
        $debugEnabled = defined('OPENROUTER_DEBUG_LOG') ? (bool)OPENROUTER_DEBUG_LOG : true;
        if (!$debugEnabled) {
            return;
        }

        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        unset($context['text'], $context['prompt'], $context['api_key'], $context['OPENROUTER_API_KEY']);

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents($logDir . '/interest_api.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        error_log($line);
    }
}

if (!function_exists('ai_set_last_api_status')) {
    function ai_set_last_api_status(string $status, string $detail = ''): void
    {
        $GLOBALS['MATCH_INTEREST_API_STATUS'] = [
            'status' => $status,
            'detail' => $detail,
            'time' => date('Y-m-d H:i:s')
        ];
    }
}

if (!function_exists('ai_get_last_api_status')) {
    function ai_get_last_api_status(): array
    {
        return $GLOBALS['MATCH_INTEREST_API_STATUS'] ?? [
            'status' => 'not_called',
            'detail' => '',
            'time' => date('Y-m-d H:i:s')
        ];
    }
}

if (!function_exists('ai_interest_categories')) {
    function ai_interest_categories(): array
    {
        return [
            'Музыка', 'K-Pop', 'Кино и сериалы', 'Аниме', 'Книги', 'Манга и комиксы',
            'Прогулки', 'Разговоры', 'Новые знакомства', 'Кафе', 'Еда и напитки', 'Кулинария', 'Сладости',
            'Путешествия', 'Города и архитектура', 'Природа', 'Животные', 'Растения',
            'Спорт', 'Фитнес', 'Йога', 'Бег', 'Велосипед', 'Активный отдых', 'Походы',
            'Видеоигры', 'Настольные игры', 'Фотография', 'Искусство', 'Театр', 'Музеи', 'Выставки',
            'Рисование', 'Рукоделие', 'Дизайн', 'Творчество', 'Технологии', 'Программирование',
            'Изучение языков', 'Учёба', 'Наука', 'История', 'Психология', 'Мода', 'Красота', 'Танцы',
            'Домашний отдых', 'Уютные вечера', 'Спокойное общение', 'Совместный досуг', 'Волонтёрство',
            'Шумные компании', 'Спешка', 'Грубость'
        ];
    }
}

if (!function_exists('ai_category_map')) {
    function ai_category_map(): array
    {
        return [
            'литература' => 'Книги',
            'чтение' => 'Книги',
            'комиксы' => 'Манга и комиксы',
            'манга' => 'Манга и комиксы',
            'к поп' => 'K-Pop',
            'к-поп' => 'K-Pop',
            'kpop' => 'K-Pop',
            'k-pop' => 'K-Pop',
            'кей поп' => 'K-Pop',
            'дорамы' => 'Кино и сериалы',
            'фильмы' => 'Кино и сериалы',
            'сериалы' => 'Кино и сериалы',
            'кафе' => 'Кафе',
            'кофе' => 'Кафе',
            'чай' => 'Кафе',
            'еда' => 'Еда и напитки',
            'напитки' => 'Еда и напитки',
            'молоко' => 'Еда и напитки',
            'готовка' => 'Кулинария',
            'выпечка' => 'Кулинария',
            'десерты' => 'Сладости',
            'сладкое' => 'Сладости',
            'кошки' => 'Животные',
            'котики' => 'Животные',
            'кошечки' => 'Животные',
            'собаки' => 'Животные',
            'питомцы' => 'Животные',
            'зелёный цвет' => 'Природа',
            'зеленый цвет' => 'Природа',
            'лес' => 'Природа',
            'парк' => 'Природа',
            'цветы' => 'Растения',
            'растения' => 'Растения',
            'архитектура' => 'Города и архитектура',
            'города' => 'Города и архитектура',
            'видеоигры' => 'Видеоигры',
            'компьютерные игры' => 'Видеоигры',
            'настолки' => 'Настольные игры',
            'настольные игры' => 'Настольные игры',
            'фото' => 'Фотография',
            'фотография' => 'Фотография',
            'галереи' => 'Выставки',
            'выставки' => 'Выставки',
            'рисование' => 'Рисование',
            'рукоделие' => 'Рукоделие',
            'вязание' => 'Рукоделие',
            'лепка' => 'Рукоделие',
            'айти' => 'Технологии',
            'it' => 'Технологии',
            'код' => 'Программирование',
            'программирование' => 'Программирование',
            'языки' => 'Изучение языков',
            'английский' => 'Изучение языков',
            'корейский' => 'Изучение языков',
            'японский' => 'Изучение языков',
            'учеба' => 'Учёба',
            'учёба' => 'Учёба',
            'университет' => 'Учёба',
            'колледж' => 'Учёба',
            'стиль' => 'Мода',
            'одежда' => 'Мода',
            'макияж' => 'Красота',
            'косметика' => 'Красота',
            'домоседство' => 'Домашний отдых',
            'домашний отдых' => 'Домашний отдых',
            'уют' => 'Уютные вечера',
            'спокойное общение' => 'Спокойное общение',
            'шумные компании' => 'Шумные компании',
            'спешка' => 'Спешка',
            'суета' => 'Спешка',
            'хамство' => 'Грубость',
            'грубость' => 'Грубость'
        ];
    }
}

if (!function_exists('ai_normalize_category')) {
    function ai_normalize_category(string $value): string
    {
        $clean = ai_norm_text($value);
        $map = ai_category_map();

        if (isset($map[$clean])) {
            return $map[$clean];
        }

        foreach (ai_interest_categories() as $category) {
            if (ai_norm_text($category) === $clean) {
                return $category;
            }
        }

        return '';
    }
}

if (!function_exists('ai_has_unsafe_content')) {
    function ai_has_unsafe_content(string $text): bool
    {
        $text = ai_norm_text($text);

        $adultPattern = '/(18\+|nsfw|порно|эротик|сексуальн|\bсекс\b|интим|нюдс?|обнажен|обнажён|голые|голая|голый|минет|оральн|анальн|полов[оа]й|проститут|эскорт|фетиш)/iu';
        $profanityPattern = '/(\bхуй|\bхуе|\bхуя|пизд|еба|ёба|ебл|ёбл|бля|сука|мраз|долбо|залуп|шлюх|гандон)/iu';

        return preg_match($adultPattern, $text) === 1 || preg_match($profanityPattern, $text) === 1;
    }
}

if (!function_exists('ai_clean_json_from_model')) {
    function ai_clean_json_from_model(string $text): string
    {
        $text = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/su', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $start = mb_strpos($text, '{');
        $end = mb_strrpos($text, '}');

        if ($start !== false && $end !== false) {
            $text = mb_substr($text, $start, $end - $start + 1);
        }

        return trim($text);
    }
}

if (!function_exists('ai_normalize_result')) {
    function ai_normalize_result(array $data): array
    {
        $result = ['interests' => [], 'anti_interests' => []];

        foreach (['interests', 'anti_interests'] as $blockName) {
            if (empty($data[$blockName]) || !is_array($data[$blockName])) {
                continue;
            }

            foreach ($data[$blockName] as $item) {
                $category = ai_normalize_category((string)($item['name'] ?? $item['category'] ?? ''));
                if ($category === '') {
                    continue;
                }

                $topics = $item['topics'] ?? [];
                if (!is_array($topics)) {
                    $topics = [];
                }

                $cleanTopics = [];
                foreach ($topics as $topic) {
                    $topic = trim((string)$topic);
                    $topic = preg_replace('/\s+/u', ' ', $topic) ?? $topic;
                    $topic = mb_substr($topic, 0, 60, 'UTF-8');

                    if ($topic === '' || ai_has_unsafe_content($topic)) {
                        continue;
                    }

                    $cleanTopics[] = $topic;
                }

                if ($cleanTopics === []) {
                    $cleanTopics[] = $category;
                }

                $result[$blockName][] = [
                    'name' => $category,
                    'topics' => array_values(array_unique($cleanTopics))
                ];
            }
        }

        foreach (['interests', 'anti_interests'] as $blockName) {
            $merged = [];
            foreach ($result[$blockName] as $item) {
                $name = $item['name'];
                if (!isset($merged[$name])) {
                    $merged[$name] = ['name' => $name, 'topics' => []];
                }
                $merged[$name]['topics'] = array_values(array_unique(array_merge($merged[$name]['topics'], $item['topics'])));
            }
            $result[$blockName] = array_values($merged);
        }

        return $result;
    }
}

if (!function_exists('ai_keyword_rules')) {
    function ai_keyword_rules(): array
    {
        return [
            'K-Pop' => ['k-pop', 'kpop', 'к-поп', 'к поп', 'кей поп', 'айдол', 'айдолы', 'bts', 'stray kids', 'skz', 'blackpink', 'txt', 'enhypen', 'seventeen', 'ateez', 'twice'],
            'Музыка' => ['музык', 'песн', 'петь', 'вокал', 'концерт', 'плейлист', 'альбом', 'рок', 'поп', 'рэп', 'реп', 'инди', 'джаз', 'классическ', 'гитара', 'фортепиано', 'пианино'],
            'Кино и сериалы' => ['кино', 'фильм', 'сериал', 'дорама', 'мультфильм', 'мульт', 'комедия', 'драма', 'триллер', 'ужасы', 'фэнтези', 'фантастик', 'марвел', 'netflix'],
            'Аниме' => ['аниме', 'отаку'],
            'Книги' => ['книг', 'читать', 'чтение', 'литератур', 'роман', 'фэнтези', 'детектив', 'поэзия', 'стихи', 'книжн'],
            'Манга и комиксы' => ['манга', 'манхва', 'комикс', 'манхуа', 'вебтун', 'webtoon'],
            'Прогулки' => ['гулять', 'гуляю', 'прогул', 'пешком', 'бродить', 'по городу', 'набережн', 'вечерние прогулки', 'тихие прогулки'],
            'Разговоры' => ['общаться', 'общение', 'разговор', 'разговаривать', 'бесед', 'переписк', 'обсуждать', 'душевные разговоры', 'глубокие разговоры', 'созвон'],
            'Новые знакомства' => ['знакомств', 'новые люди', 'новых людей', 'найти друзей', 'друзей', 'дружба', 'подруга', 'друг'],
            'Кафе' => ['кофе', 'кафе', 'кофейня', 'кофейни', 'чай', 'матча', 'латте', 'капучино', 'какао'],
            'Еда и напитки' => ['еда', 'поесть', 'ресторан', 'пицца', 'суши', 'роллы', 'лапша', 'молоко', 'лимонад', 'сок', 'напитк'],
            'Кулинария' => ['готовить', 'готовлю', 'кулинар', 'рецепт', 'выпечк', 'печь', 'готовка', 'кухня'],
            'Сладости' => ['десерт', 'сладк', 'торт', 'пирожн', 'морожен', 'шоколад', 'конфет'],
            'Путешествия' => ['путешеств', 'поездк', 'отпуск', 'море', 'горы', 'аэропорт', 'поезд', 'самолет', 'самолёт', 'страны', 'туризм'],
            'Города и архитектура' => ['архитектур', 'город', 'улицы', 'старый город', 'здания', 'урбан', 'красивые места', 'достопримечательн'],
            'Природа' => ['природ', 'лес', 'парк', 'озеро', 'река', 'море', 'горы', 'поле', 'зелень', 'зеленый цвет', 'зелёный цвет', 'закат', 'рассвет', 'свежий воздух'],
            'Растения' => ['растен', 'цветы', 'цветочки', 'сад', 'ботаник', 'комнатные растения', 'суккулент'],
            'Животные' => ['животн', 'кот', 'коты', 'котик', 'котики', 'кошка', 'кошки', 'кошеч', 'собак', 'пес', 'пёс', 'щенок', 'питом', 'хомяк', 'кролик', 'попугай', 'лошад'],
            'Спорт' => ['спорт', 'футбол', 'волейбол', 'баскетбол', 'теннис', 'плаван', 'тренировк', 'зал', 'каток', 'коньки', 'лыжи'],
            'Фитнес' => ['фитнес', 'тренажер', 'тренажёр', 'растяжк', 'пилатес', 'зарядк'],
            'Йога' => ['йога', 'медитац', 'дыхательн'],
            'Бег' => ['бег', 'бегать', 'пробежк', 'марафон'],
            'Велосипед' => ['велосипед', 'велик', 'самокат'],
            'Активный отдых' => ['активный отдых', 'активные прогулки', 'активности', 'движ', 'квест', 'лазертаг', 'батут', 'скалодром'],
            'Походы' => ['поход', 'походы', 'трекинг', 'палатк', 'кемпинг'],
            'Видеоигры' => ['видеоигр', 'играю', 'гейм', 'геймер', 'компьютерные игры', 'minecraft', 'genshin', 'valorant', 'dota', 'cs2', 'sims', 'roblox', 'stardew'],
            'Настольные игры' => ['настол', 'мафия', 'монополия', 'dnd', 'd&d', 'днд', 'карточные игры'],
            'Фотография' => ['фото', 'фотограф', 'снимать', 'съемк', 'съёмк', 'камера', 'пленка', 'плёнка', 'портрет'],
            'Искусство' => ['искусств', 'арт', 'худож', 'картины', 'живопись', 'скульптур'],
            'Театр' => ['театр', 'спектакл', 'мюзикл'],
            'Музеи' => ['музе'],
            'Выставки' => ['выставк', 'галере', 'экспозици'],
            'Рисование' => ['рисовать', 'рисую', 'скетч', 'иллюстрац', 'акварель', 'карандаш'],
            'Рукоделие' => ['вязать', 'вязание', 'шить', 'шитье', 'шитьё', 'вышив', 'лепить', 'лепка', 'бисер', 'керамик', 'глина'],
            'Дизайн' => ['дизайн', 'figma', 'фигма', 'макет', 'интерфейс', 'графический дизайн'],
            'Творчество' => ['творч', 'креатив', 'делать руками', 'handmade', 'хендмейд'],
            'Технологии' => ['технолог', 'айти', 'it', 'гаджет', 'нейросет', 'искусственный интеллект', 'компьютер'],
            'Программирование' => ['программ', 'код', 'верстк', 'вёрстк', 'php', 'javascript', 'python', 'html', 'css', 'база данных', 'mysql'],
            'Изучение языков' => ['язык', 'английск', 'корейск', 'японск', 'китайск', 'немецк', 'французск', 'испанск', 'лингвист'],
            'Учёба' => ['учеб', 'учёб', 'учиться', 'универ', 'университет', 'колледж', 'школа', 'экзамен', 'диплом', 'пары'],
            'Наука' => ['наука', 'биология', 'химия', 'физика', 'астроном', 'космос', 'математик'],
            'История' => ['истор', 'археолог', 'мифолог'],
            'Психология' => ['психолог', 'саморазвит', 'личность', 'эмоции', 'отношения'],
            'Мода' => ['мода', 'стиль', 'одежд', 'лук', 'образ', 'шопинг', 'винтаж'],
            'Красота' => ['макияж', 'косметик', 'уход', 'бьюти', 'маникюр', 'прически', 'причёски'],
            'Танцы' => ['танц', 'хореограф', 'dance'],
            'Домашний отдых' => ['сидеть дома', 'дома', 'домосед', 'домашний отдых', 'никуда не ходить', 'сериалы дома'],
            'Уютные вечера' => ['уют', 'плед', 'свечи', 'спокойный вечер', 'тихий вечер', 'уютные вечера'],
            'Спокойное общение' => ['спокойн', 'тихое общение', 'тихие разговоры', 'без шума', 'без толпы', 'уютное общение', 'небольшая компания'],
            'Совместный досуг' => ['совместный досуг', 'куда-нибудь сходить', 'сходить вместе', 'провести время', 'заняться чем-нибудь'],
            'Волонтёрство' => ['волонтер', 'волонтёр', 'помощь людям', 'благотвор', 'приют'],
            'Шумные компании' => ['шумные компании', 'шумн', 'толпа', 'тусовк', 'вечеринк', 'клуб'],
            'Спешка' => ['спешк', 'торопиться', 'суета', 'быстрый темп', 'вечно бегать'],
            'Грубость' => ['грубость', 'грубые', 'хамство', 'хамск', 'агрессия', 'агрессивн', 'токсичн']
        ];
    }
}

if (!function_exists('ai_contains_any')) {
    function ai_contains_any(string $text, array $needles): ?string
    {
        foreach ($needles as $needle) {
            $needle = ai_norm_text((string)$needle);
            if ($needle !== '' && mb_stripos($text, $needle, 0, 'UTF-8') !== false) {
                return $needle;
            }
        }

        return null;
    }
}

if (!function_exists('ai_negative_chunks')) {
    function ai_negative_chunks(string $normText): array
    {
        $chunks = [];
        $sentences = preg_split('/[.!?;]+/u', $normText) ?: [$normText];
        $marker = '/(?:не\s+люблю|не\s+нравится|не\s+нравятся|ненавижу|терпеть\s+не\s+могу|не\s+интересует|не\s+интересуют|не\s+хочу|не\s+мое|не\s+моё|избегаю|без\s+интереса\s+к?)\s*(.*)$/u';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if (preg_match($marker, $sentence, $matches) === 1) {
                $chunk = trim((string)$matches[1]);
                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }
            }
        }

        return array_values(array_unique($chunks));
    }
}

if (!function_exists('ai_topic_for')) {
    function ai_topic_for(string $category, string $normText, string $matchedNeedle): string
    {
        return match ($category) {
            'K-Pop' => 'k-pop',
            'Музыка' => str_contains($normText, 'концерт') ? 'концерты и музыка' : 'музыка',
            'Кино и сериалы' => str_contains($normText, 'дорама') ? 'дорамы' : (str_contains($normText, 'сериал') ? 'сериалы' : 'кино'),
            'Аниме' => 'аниме',
            'Книги' => str_contains($normText, 'поэз') || str_contains($normText, 'стих') ? 'поэзия' : 'книги',
            'Манга и комиксы' => str_contains($normText, 'манг') ? 'манга' : 'комиксы',
            'Прогулки' => 'прогулки',
            'Разговоры' => 'разговоры',
            'Новые знакомства' => 'новые знакомства',
            'Кафе' => str_contains($normText, 'чай') ? 'чай и кафе' : 'кофе и кафе',
            'Еда и напитки' => str_contains($normText, 'молок') ? 'молоко' : 'еда и напитки',
            'Кулинария' => str_contains($normText, 'выпеч') ? 'выпечка' : 'готовка',
            'Сладости' => 'сладости',
            'Путешествия' => 'путешествия',
            'Города и архитектура' => 'города и архитектура',
            'Природа' => str_contains($normText, 'зелен') ? 'зелёный цвет и природа' : 'природа',
            'Растения' => 'растения',
            'Животные' => preg_match('/кошк|кошеч|котик|\bкот\b/u', $normText) === 1 ? 'кошки' : (preg_match('/собак|пес|пёс/u', $normText) === 1 ? 'собаки' : 'животные'),
            'Домашний отдых' => 'домашний отдых',
            'Уютные вечера' => 'уютные вечера',
            'Спокойное общение' => 'спокойное общение',
            'Шумные компании' => 'шумные компании',
            'Спешка' => 'спешка',
            'Грубость' => 'грубость',
            default => $matchedNeedle !== '' ? $matchedNeedle : $category,
        };
    }
}

if (!function_exists('ai_keyword_result')) {
    function ai_keyword_result(string $text): array
    {
        $norm = ai_norm_text($text);
        $negativeChunks = ai_negative_chunks($norm);
        $positiveText = $norm;

        foreach ($negativeChunks as $chunk) {
            $positiveText = str_replace($chunk, ' ', $positiveText);
        }

        $items = [];
        $antiItems = [];
        $rules = ai_keyword_rules();

        foreach ($rules as $category => $needles) {
            $positiveNeedle = ai_contains_any($positiveText, $needles);
            if ($positiveNeedle !== null) {
                $items[$category][] = ai_topic_for($category, $norm, $positiveNeedle);
            }

            foreach ($negativeChunks as $chunk) {
                $negativeNeedle = ai_contains_any($chunk, $needles);
                if ($negativeNeedle !== null) {
                    $antiItems[$category][] = ai_topic_for($category, $norm, $negativeNeedle);
                    break;
                }
            }
        }

        if (
            preg_match('/не\s+люблю[^.!?]*(шумн|спешк|сует|груб|хамств|агресс|токсич)/u', $norm) === 1
            && empty($items['Спокойное общение'])
        ) {
            $items['Спокойное общение'][] = 'спокойное общение';
        }

        if (
            preg_match('/люблю[^.!?]*(дома|уют|спокойн|тих)/u', $norm) === 1
            && empty($items['Домашний отдых'])
        ) {
            $items['Домашний отдых'][] = 'домашний отдых';
        }

        $result = ['interests' => [], 'anti_interests' => []];

        foreach ($items as $name => $topics) {
            $result['interests'][] = ['name' => $name, 'topics' => array_values(array_unique($topics))];
        }

        foreach ($antiItems as $name => $topics) {
            $result['anti_interests'][] = ['name' => $name, 'topics' => array_values(array_unique($topics))];
        }

        return ai_normalize_result($result);
    }
}

if (!function_exists('ai_openrouter_result')) {
    function ai_openrouter_result(string $text): ?array
    {
        ai_set_last_api_status('not_called', '');

        $configPath = __DIR__ . '/ai_config.php';
        if (!file_exists($configPath)) {
            ai_set_last_api_status('fallback', 'config_not_found');
            ai_debug_log('Interest API fallback: config_not_found');
            return null;
        }

        require_once $configPath;

        $apiKey = trim((string)(defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : ''));
        if ($apiKey === '') {
            ai_set_last_api_status('fallback', 'empty_api_key');
            ai_debug_log('Interest API fallback: empty_api_key');
            return null;
        }

        if (!function_exists('curl_init')) {
            ai_set_last_api_status('fallback', 'curl_not_enabled');
            ai_debug_log('Interest API fallback: curl_not_enabled');
            return null;
        }

        $categories = ai_interest_categories();
        $prompt = 'Ты извлекаешь интересы пользователя для сайта поиска друзей. Верни только валидный JSON без markdown. ' .
            'Если текст содержит 18+, сексуальный, нецензурный или небезопасный контент, верни {"safe":false,"message":"Текст нельзя обработать"}. ' .
            'Иначе верни {"safe":true,"interests":[{"name":"категория","topics":["подтема"]}],"anti_interests":[{"name":"категория","topics":["подтема"]}]}. ' .
            'Категории name выбирай строго из списка: ' . implode(', ', $categories) . '. ' .
            'interests — то, что пользователь любит или хочет. anti_interests — только то, что пользователь прямо не любит, избегает или не хочет. ' .
            'Не выдумывай интересы, которых нет в тексте. Если человек пишет "люблю сидеть дома", это Домашний отдых, а не антиинтерес к прогулкам. ' .
            'Если человек пишет "не люблю активные прогулки", добавь Активный отдых в anti_interests. Текст пользователя: ' . $text;

        $requestData = [
            'model' => defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'openrouter/free',
            'messages' => [
                ['role' => 'system', 'content' => 'Ты возвращаешь только валидный JSON для обработки интересов.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ];

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: ' . (defined('OPENROUTER_SITE_URL') ? OPENROUTER_SITE_URL : 'http://localhost'),
                'X-Title: ' . (defined('OPENROUTER_APP_TITLE') ? OPENROUTER_APP_TITLE : 'Match')
            ],
            CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE)
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($response) || $response === '') {
            ai_set_last_api_status('fallback', 'empty_response_or_curl_error');
            ai_debug_log('Interest API fallback: empty_response_or_curl_error', ['curl_error' => $curlError]);
            return null;
        }

        if ($httpCode !== 200) {
            $shortResponse = mb_substr($response, 0, 500, 'UTF-8');
            ai_set_last_api_status('fallback', 'http_' . $httpCode);
            ai_debug_log('Interest API fallback: http_error', ['http_code' => $httpCode, 'response' => $shortResponse]);
            return null;
        }

        $apiResponse = json_decode($response, true);
        if (!is_array($apiResponse)) {
            ai_set_last_api_status('fallback', 'bad_api_json');
            ai_debug_log('Interest API fallback: bad_api_json');
            return null;
        }

        $answer = $apiResponse['choices'][0]['message']['content'] ?? '';
        if (!is_string($answer) || trim($answer) === '') {
            ai_set_last_api_status('fallback', 'empty_model_answer');
            ai_debug_log('Interest API fallback: empty_model_answer');
            return null;
        }

        $decoded = json_decode(ai_clean_json_from_model($answer), true);
        if (!is_array($decoded)) {
            ai_set_last_api_status('fallback', 'bad_model_json');
            ai_debug_log('Interest API fallback: bad_model_json', ['answer' => mb_substr($answer, 0, 500, 'UTF-8')]);
            return null;
        }

        if (($decoded['safe'] ?? true) === false) {
            ai_set_last_api_status('blocked', 'unsafe_by_model');
            ai_debug_log('Interest API blocked: unsafe_by_model');
            return ['unsafe' => true, 'message' => (string)($decoded['message'] ?? 'Текст нельзя обработать.')];
        }

        ai_set_last_api_status('ok', 'openrouter');
        ai_debug_log('Interest API success');
        return ai_normalize_result($decoded);
    }
}

if (!function_exists('ai_extract_interests_from_text')) {
    function ai_extract_interests_from_text(string $text): array
    {
        if (ai_has_unsafe_content($text)) {
            return [
                'ok' => false,
                'source' => 'blocked',
                'message' => 'Текст содержит недопустимый контент, поэтому интересы не были обработаны.',
                'api_status' => ai_get_last_api_status()
            ];
        }

        $apiResult = ai_openrouter_result($text);
        if (is_array($apiResult) && ($apiResult['unsafe'] ?? false) === true) {
            return [
                'ok' => false,
                'source' => 'blocked',
                'message' => 'Текст содержит недопустимый контент, поэтому интересы не были обработаны.',
                'api_status' => ai_get_last_api_status()
            ];
        }

        if (is_array($apiResult)) {
            $hasInterests = !empty($apiResult['interests']) || !empty($apiResult['anti_interests']);
            if ($hasInterests) {
                return ['ok' => true, 'source' => 'api', 'data' => $apiResult, 'api_status' => ai_get_last_api_status()];
            }
        }

        $localResult = ai_keyword_result($text);
        return ['ok' => true, 'source' => 'local', 'data' => $localResult, 'api_status' => ai_get_last_api_status()];
    }
}
