<?php

namespace App\Services\Chat;

/**
 * Rule-based assistant for the login page (no outside service, works offline).
 * Order of intents: tracking-number lookup -> "step N" -> "what does <role> do" -> best matching knowledge-base entry -> fallback.
 */
class ChatBot
{
    private const ROLE_WORDS = [
        'rb' => ['প্রত্যাশী সংস্থা', 'প্রত্যাশী', 'requiring body'], 'dc' => ['জেলা প্রশাসক', 'ডিসি', 'dc'], 'adc' => ['অতিরিক্ত জেলা প্রশাসক', 'এডিসি', 'adc'],
        'lao' => ['ভূমি অধিগ্রহণ কর্মকর্তা', 'এলএও', 'lao'], 'ac_land' => ['এসি ল্যান্ড', 'সহকারী কমিশনার', 'ac land'], 'ulao' => ['ইউএলএও', 'ইউনিয়ন ভূমি সহকারী', 'ulao'],
        'kanungo' => ['কানুনগো', 'kanungo', 'qanungo'], 'surveyor' => ['সার্ভেয়ার', 'surveyor'], 'commissioner' => ['বিভাগীয় কমিশনার', 'commissioner'], 'accounts' => ['হিসাব কর্মকর্তা', 'accounts officer'],
    ];
    private const ROLE_VERBS = ['কাজ', 'করে', 'করেন', 'করবে', 'দায়িত্ব', 'ভূমিকা', 'what does', 'role', 'responsib', 'duties', 'does'];

    /** @param callable|null $statusLookup fn(string $trackingOrCase): ?array */
    public function __construct(private array $kb, private array $steps, private array $roles = [], private $statusLookup = null)
    {
    }

    public static function norm(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        $s = str_replace('বেসরকারি', 'প্রাইভেট', $s);                 // "বেসরকারি" contains "সরকারি"
        $s = preg_replace('/[^\p{L}\p{M}\p{N}\/\.\-]+/u', ' ', $s);
        return ' ' . trim(preg_replace('/\s+/u', ' ', $s)) . ' ';
    }

    private function has(string $norm, string $key): bool
    {
        $k = trim(self::norm($key));
        if ($k === '') return false;
        if (preg_match('/^[\x20-\x7e]+$/', $k)) return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($k, '/') . '/', $norm);   // English: word start
        return mb_strpos($norm, $k) !== false;
    }

    private function entry(string $id): ?array
    {
        foreach ($this->kb as $e) if ($e['id'] === $id) return $e;
        return null;
    }

    private function chips(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) if ($e = $this->entry($id)) $out[] = ['label' => $e['title'], 'topic' => $id];
        return $out;
    }

    private function starters(): array
    {
        return array_map(fn ($t) => ['label' => $t, 'message' => $t], KnowledgeBase::STARTERS);
    }

    private function say(array $e, string $lang): array
    {
        return ['kind' => 'kb', 'topic' => $e['id'], 'answer' => $lang === 'en' ? ($e['en'] ?? $e['bn']) : $e['bn'], 'suggestions' => $this->chips($e['next'] ?? [])];
    }

    /** @return array{kind:string,answer:string,suggestions:array,topic?:string} */
    public function reply(string $message, ?string $topic = null): array
    {
        $raw = trim(mb_substr($message, 0, 500));
        $lang = preg_match('/\p{Bengali}/u', $raw) ? 'bn' : 'en';
        if ($topic && ($e = $this->entry($topic))) return $this->say($e, 'bn');
        if ($raw === '') return ['kind' => 'start', 'answer' => 'আমি LARMS সহায়ক। কী জানতে চান?', 'suggestions' => $this->starters()];
        $norm = self::norm($raw);

        // 1. tracking / LA case number
        if (preg_match('/SA-\d{2}\/\d{2}\/\d{5}|LA-[A-Z]{2,6}-\d{1,3}\/\d{4}-\d{2}/i', $raw, $m) && $this->statusLookup) {
            $found = ($this->statusLookup)(strtoupper($m[0]));
            if (! $found) return ['kind' => 'status', 'answer' => 'এই নম্বরে কোনো দাখিলকৃত আবেদন পাওয়া যায়নি। নম্বরটি আবার দেখুন (যেমন SA-26/11/00001)।', 'suggestions' => $this->chips(['tracking'])];
            return ['kind' => 'status', 'answer' => "আবেদন {$found['tracking_no']}" . ($found['la_case_no'] ? " (এল.এ কেস {$found['la_case_no']})" : '') . ":\n• অবস্থা: {$found['status']}\n• বর্তমান ধাপ: {$found['phase']}\n• দাখিলের তারিখ: {$found['submitted_at']}\nবিস্তারিত দেখতে লগইন করুন।", 'suggestions' => $this->chips(['steps', 'deadlines'])];
        }

        // 2. "step 22" / "ধাপ ২২"
        if (preg_match('/(?:ধাপ|step|স্টেপ)\s*[:#\-]?\s*(\d{1,2}(?:\.\d)?[a-c]?)/u', $norm, $m)) {
            $no = $m[1];
            $hits = array_filter($this->steps, fn ($s) => mb_strtolower((string) $s['step_no']) === $no || str_starts_with((string) $s['step_no'], $no . '.') || str_starts_with((string) $s['step_no'], $no));
            $hits = array_slice(array_values($hits), 0, 4);
            if ($hits) {
                $lines = array_map(fn ($s) => "ধাপ {$s['step_no']}: {$s['label']}\n  কে করবেন: " . ($this->roles[$s['role']] ?? $s['role']) . "\n  কার্যক্রম: " . implode('; ', array_map(fn ($a) => $a['label'], $s['actions'])), $hits);
                return ['kind' => 'step', 'answer' => implode("\n\n", $lines), 'suggestions' => $this->chips(['steps', 'roles'])];
            }
        }

        // 3. "what does <role> do"
        $bestRole = null; $bestLen = 0;
        foreach (self::ROLE_WORDS as $code => $words) foreach ($words as $w) if ($this->has($norm, $w) && mb_strlen($w) > $bestLen) { $bestRole = $code; $bestLen = mb_strlen($w); }
        $verb = false;
        foreach (self::ROLE_VERBS as $v) if ($this->has($norm, $v)) { $verb = true; break; }

        // 4. best knowledge-base entry
        $scores = [];
        foreach ($this->kb as $i => $e) {
            $sc = 0;
            foreach ($e['keys'] as $k) if ($this->has($norm, $k)) $sc += mb_strlen(trim($k)) >= 6 ? 3 : 2;
            $scores[$i] = $sc;
        }
        arsort($scores); $topI = array_key_first($scores); $top = $scores[$topI];

        if ($bestRole && ($verb || $top < 4)) {
            $mine = array_filter($this->steps, fn ($s) => $s['role'] === $bestRole);
            $list = implode("\n", array_map(fn ($s) => "• ধাপ {$s['step_no']}: {$s['label']}", $mine));
            return ['kind' => 'role', 'answer' => ($this->roles[$bestRole] ?? $bestRole) . " যে ধাপগুলোতে কাজ করেন:\n" . $list, 'suggestions' => $this->chips(['steps', 'roles'])];
        }
        if ($top >= 2) return $this->say($this->kb[$topI], $lang);

        // 5. fallback: closest topics
        $close = array_keys(array_filter($scores, fn ($s) => $s >= 1));
        $ids = array_map(fn ($i) => $this->kb[$i]['id'], array_slice($close, 0, 3));
        return ['kind' => 'fallback', 'answer' => $lang === 'en' ? 'Sorry, I could not understand. Please choose a topic below or ask in other words.' : 'দুঃখিত, প্রশ্নটি ঠিক বুঝতে পারিনি। নিচের বিষয়গুলো থেকে বেছে নিন, অথবা অন্যভাবে লিখুন (যেমন: "কাগজপত্র", "সময়সীমা", "ধাপ ২২", ট্র্যাকিং নম্বর)।',
            'suggestions' => $ids ? $this->chips($ids) : $this->starters()];
    }
}
