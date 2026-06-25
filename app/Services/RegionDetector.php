<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resolves a city from a Russian region/address string.
 *
 * Ported from TellFax's `common/RegionDetector.php` (423 lines).
 * Two-step approach:
 *
 *   1. Check the address for a hard marker (`г.`, `гор.`, `город`) — extract that.
 *   2. Otherwise look up the region by name → return its capital.
 *
 * Falls back to the original string when nothing matches.
 *
 * The internal capital map and the regex set are kept verbatim from the
 * Yii2 source so behaviour matches TellFax's old scoring pipeline exactly.
 */
class RegionDetector
{
    /**
     * Mapping: normalized region name → capital city.
     * (48 областей, 9 краёв, республики, АО, города фед. значения.)
     *
     * @var array<string, string>
     */
    private const CAPITAL_REFERENCE = [
        // Области
        'амурская область' => 'благовещенск',
        'архангельская область' => 'архангельск',
        'астраханская область' => 'астрахань',
        'белгородская область' => 'белгород',
        'брянская область' => 'брянск',
        'владимирская область' => 'владимир',
        'волгоградская область' => 'волгоград',
        'вологодская область' => 'вологда',
        'воронежская область' => 'воронеж',
        'ивановская область' => 'иваново',
        'иркутская область' => 'иркутск',
        'калининградская область' => 'калининград',
        'калужская область' => 'калуга',
        'кемеровская область - кузбасс' => 'кемерово',
        'кемеровская область' => 'кемерово',
        'кировская область' => 'киров',
        'костромская область' => 'кострома',
        'курганская область' => 'курган',
        'курская область' => 'курск',
        'ленинградская область' => 'санкт-петербург',
        'липецкая область' => 'липецк',
        'магаданская область' => 'магадан',
        'московская область' => 'москва',
        'мурманская область' => 'мурманск',
        'нижегородская область' => 'нижний новгород',
        'новгородская область' => 'великий новгород',
        'новосибирская область' => 'новосибирск',
        'омская область' => 'омск',
        'оренбургская область' => 'оренбург',
        'орловская область' => 'орёл',
        'пензенская область' => 'пенза',
        'псковская область' => 'псков',
        'ростовская область' => 'ростов-на-дону',
        'рязанская область' => 'рязань',
        'самарская область' => 'самара',
        'саратовская область' => 'саратов',
        'сахалинская область' => 'южно-сахалинск',
        'свердловская область' => 'екатеринбург',
        'смоленская область' => 'смоленск',
        'тамбовская область' => 'тамбов',
        'тверская область' => 'тверь',
        'томская область' => 'томск',
        'тульская область' => 'тула',
        'тюменская область' => 'тюмень',
        'ульяновская область' => 'ульяновск',
        'челябинская область' => 'челябинск',
        'ярославская область' => 'ярославская область',
        // Края
        'алтайский край' => 'барнаул',
        'забайкальский край' => 'чита',
        'камчатский край' => 'петропавловск-камчатский',
        'краснодарский край' => 'краснодар',
        'красноярский край' => 'красноярск',
        'пермский край' => 'пермь',
        'приморский край' => 'владивосток',
        'ставропольский край' => 'ставрополь',
        'хабаровский край' => 'хабаровск',
        // Республики
        'республика адыгея' => 'майкоп',
        'республика алтай' => 'горно-алтайск',
        'республика башкортостан' => 'уфа',
        'республика бурятия' => 'улан-удэ',
        'республика дагестан' => 'махачкала',
        'республика ингушетия' => 'магас',
        'республика кабардино-балкарская' => 'нальчик',
        'республика калмыкия' => 'элиста',
        'республика карачаево-черкесская' => 'черкесск',
        'республика карелия' => 'петрозаводск',
        'республика коми' => 'сыктывкар',
        'республика крым' => 'симферополь',
        'республика марий эл' => 'йошкар-ола',
        'республика мордовия' => 'саранск',
        'республика саха (якутия)' => 'якутск',
        'республика северная осетия - алания' => 'владикавказ',
        'республика татарстан' => 'казань',
        'республика тыва' => 'кызыл',
        'республика удмуртская' => 'ижевск',
        'республика хакасия' => 'абакан',
        'республика чеченская' => 'грозный',
        'республика чувашская' => 'чебоксары',
        // Автономные округа
        'ненецкий автономный округ' => 'нарьян-мар',
        'ханты-мансийский автономный округ - югра' => 'ханты-мансийск',
        'ямало-ненецкий автономный округ' => 'салехард',
        'чукотский автономный округ' => 'анадырь',
        // Города федерального значения
        'москва' => 'москва',
        'санкт-петербург' => 'санкт-петербург',
        'севастополь' => 'севастополь',
    ];

    /**
     * Skip-list: address parts that look like city names but aren't.
     * (Streets, micro-districts, building prefixes — `УЛ`, `ПР`, etc.)
     *
     * @var list<string>
     */
    private const NOISE_PREFIXES = [
        'УЛ', 'ПР', 'ПЕР', 'Б-Р', 'Ш', 'Д', 'КВ', 'ЭТ', 'ОФ', 'КОРП', 'СТР',
        'ЛИТ', 'ПОД', 'Р-Н', 'МКР', 'ТЕР', 'СНТ', 'ДНТ',
    ];

    public function detectCity(string $address, bool $onlyCapital = false): string
    {
        if (trim($address) === '') {
            return '';
        }

        $normalized = mb_strtolower(trim($address));

        // 1. Hard marker — extract the city token if present.
        if (preg_match('/(г\.|гор\.|город)\s*([а-яё][а-яё\s\-]+)/u', $normalized, $m)) {
            $cityByMarker = trim($m[2]);
            if ($this->isPlausibleCity($cityByMarker)) {
                return $this->formatCityName($cityByMarker);
            }
        }

        // 2. Region name → capital.
        $capital = $this->lookupCapital($normalized);
        if ($capital !== null) {
            return $this->formatCityName($capital);
        }

        // 3. $onlyCapital = true means we only return on a hit; otherwise
        //    pass the address through (mirrors TellFax behavior).
        return $onlyCapital ? '' : $address;
    }

    private function isPlausibleCity(string $token): bool
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 2) {
            return false;
        }
        $upper = mb_strtoupper($token);
        if (in_array($upper, self::NOISE_PREFIXES, true)) {
            return false;
        }
        // Pure numeric (street number) — not a city.
        if (preg_match('/^\d+$/', $token)) {
            return false;
        }

        return true;
    }

    private function lookupCapital(string $normalized): ?string
    {
        $needle = mb_strtolower($normalized);
        // Longest-prefix match so 'кемеровская область - кузбасс' wins over
        // 'кемеровская область'.
        $best = null;
        $bestLen = 0;
        foreach (self::CAPITAL_REFERENCE as $region => $capital) {
            if ($region === '') {
                continue;
            }
            if (mb_strlen($region) > $bestLen && str_contains($needle, $region)) {
                $best = $capital;
                $bestLen = mb_strlen($region);
            }
        }

        return $best;
    }

    private function formatCityName(string $city): string
    {
        $city = trim($city);
        $lower = mb_strtolower($city);

        // Split by hyphens and whitespace so 'ростов-на-дону' becomes
        // ['ростов', 'на', 'дону']. Then uppercase the first letter of
        // each part EXCEPT short prepositions (1-3 letters: на, в, во, об, etc.).
        $parts = preg_split('/([\-\s]+)/u', $lower, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $p) {
            if ($p === '' || preg_match('/^[\-\s]+$/u', $p)) {
                $out .= $p; // delimiters pass through
                continue;
            }
            // Short Russian prepositions stay lowercase in display form.
            $isPreposition = mb_strlen($p) <= 3
                && in_array($p, ['на', 'в', 'во', 'об', 'обо', 'из', 'изо', 'по', 'ко', 'со'], true);
            $out .= $isPreposition ? $p : (mb_strtoupper(mb_substr($p, 0, 1)) . mb_substr($p, 1));
        }

        return $out;
    }
}