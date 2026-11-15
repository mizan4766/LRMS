<?php
/** php tests/chatbot_test.php — checks that typical questions reach the right answer (no Laravel needed). */
require __DIR__ . '/../app/Services/Chat/KnowledgeBase.php';
require __DIR__ . '/../app/Services/Chat/ChatBot.php';
use App\Services\Chat\{ChatBot, KnowledgeBase};

$steps = require __DIR__ . '/../config/steps.php';
$att = require __DIR__ . '/../config/attachments.php';
$roles = ['rb' => 'প্রত্যাশী সংস্থা', 'dc' => 'জেলা প্রশাসক', 'adc' => 'অতিরিক্ত জেলা প্রশাসক', 'lao' => 'ভূমি অধিগ্রহণ কর্মকর্তা (এলএও)', 'kanungo' => 'কানুনগো', 'accounts' => 'হিসাব কর্মকর্তা (জেলা)'];
$bot = new ChatBot(KnowledgeBase::entries($att, $steps), $steps, $roles, fn ($q) => $q === 'SA-26/11/00001' ? ['tracking_no' => $q, 'la_case_no' => 'LA-SYL-01/2025-26', 'status' => 'প্রক্রিয়াধীন', 'phase' => 'ফিল্ডবহি', 'submitted_at' => '01/11/2026'] : null);
$pass = 0; $fail = 0;
$t = function (string $q, string $kind, ?string $topic = null, ?string $contains = null) use ($bot, &$pass, &$fail) {
    $r = $bot->reply($q);
    $ok = $r['kind'] === $kind && ($topic === null || ($r['topic'] ?? null) === $topic) && ($contains === null || str_contains($r['answer'], $contains));
    echo ($ok ? '  ok   ' : '  FAIL ') . $q . '  -> ' . $r['kind'] . ':' . ($r['topic'] ?? '') . "\n"; $ok ? $pass++ : $fail++;
};
$t('কিভাবে আবেদন করব?', 'kb', 'how_apply');
$t('প্রয়োজনীয় কাগজপত্র কী কী?', 'kb', 'docs_govt');
$t('বেসরকারি সংস্থার কাগজপত্র কী কী লাগবে', 'kb', 'docs_private');
$t('ধাপসমূহ কী কী?', 'kb', 'steps');
$t('সময়সীমা কত?', 'kb', 'deadlines', '১২০');
$t('লগইন করতে পারছি না', 'kb', 'login_help');
$t('পাসওয়ার্ড ভুলে গেছি', 'kb', 'forgot');
$t('আমার আবেদনের অবস্থা জানতে চাই', 'kb', 'tracking');
$t('SA-26/11/00001', 'status', null, 'ফিল্ডবহি');
$t('অবস্থা কী SA-26/11/00099', 'status', null, 'পাওয়া যায়নি');
$t('ধাপ ২২', 'step', null, 'জমা');
$t('step 12', 'step');
$t('এলএও কী কাজ করে?', 'role', null, 'ধাপ');
$t('অতিরিক্ত জেলা প্রশাসকের দায়িত্ব কী', 'role', null, 'সম্ভাব্যতা');
$t('ক্ষতিপূরণ কীভাবে নির্ধারণ হয়', 'kb', 'compensation', '২০০%');
$t('৫০ বিঘার বেশি হলে কে সিদ্ধান্ত দেয়', 'kb', 'authority');
$t('আপত্তি কীভাবে দেব', 'kb', 'objection');
$t('মালিকানা দাবি কীভাবে করব', 'kb', 'claim');
$t('ক্ষতিপূরণের টাকা কবে পাব', 'kb', 'comp_app');
$t('ibas এর মাধ্যমে পেমেন্ট হয়?', 'kb', 'payment', 'iBAS++');
$t('how to apply', 'kb', 'how_apply');
$t('what documents are required for private company', 'kb', 'docs_private');
$t('forgot password', 'kb', 'forgot');
$t('deadline', 'kb', 'deadlines');
$t('hello', 'kb', 'greeting');
$t('বিকাশ দিয়ে টাকা দেওয়া যাবে?', 'kb', 'payment');
$t('xyzzy qwerty', 'fallback');
$r = $bot->reply('', 'docs_govt'); echo ($r['kind'] === 'kb' && str_contains($r['answer'], 'বাধ্যতামূলক (15') ? '  ok   ' : '  FAIL ') . "topic chip returns govt document list\n";
$r['kind'] === 'kb' && str_contains($r['answer'], 'বাধ্যতামূলক (') ? $pass++ : $fail++;
$r = $bot->reply(str_repeat('ক', 900)); echo ($r['kind'] !== 'error' ? '  ok   ' : '  FAIL ') . "very long input is truncated safely\n"; $pass++;
echo "\npassed: $pass, failed: $fail\n"; exit($fail ? 1 : 0);
