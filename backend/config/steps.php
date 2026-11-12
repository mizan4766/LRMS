<?php

/**
 * LARMS workflow (follows "LARMS software workflow (Updated Oct 12, 2026)").
 * Every step: label, role, fields (form), actions { label, fields?, guards?, effects?, next[] }.
 * next spec keys: step, assign (field holding a user id), assign_many (field holding user ids), assign_parent_actor,
 *   assign_creator (application creator), per ('upazila' | 'field:NAME'), add_groups, drop_groups, join (groups that must be finished),
 *   when [field, value], inherit_upazila. Group names may contain {u} = upazila id of the current task.
 */
$f = fn (string $n, string $label, string $type = 'text', bool $req = false, array $x = []) => ['name' => $n, 'label' => $label, 'type' => $type, 'required' => $req] + $x;
$comment = fn (string $default = '', bool $req = true) => $f('comment', 'মন্তব্য', 'textarea', $req, ['default' => $default]);
$members = $f('members', 'কমিটির সদস্য', 'component', true, ['component' => 'committee']);
$yesno = ['yes' => 'হ্যাঁ', 'no' => 'না'];

return [
    // ------------------------------------------------------------------ Phase 1: receive, verify (Step 02-05)
    'S02' => ['label' => 'আবেদন গ্রহণ ও প্রক্রিয়াকরণ', 'step_no' => '02', 'role' => 'dc', 'phase' => 'আবেদন গ্রহণ ও প্রক্রিয়াকরণ',
        'fields' => [$comment('Please process')],
        'actions' => ['forward' => ['label' => 'প্রক্রিয়াকরণে প্রেরণ করুন', 'next' => [['step' => 'S03']]]]],

    'S03' => ['label' => 'আবেদন পর্যালোচনা', 'step_no' => '03.0', 'role' => 'adc', 'phase' => 'আবেদন গ্রহণ ও প্রক্রিয়াকরণ',
        'fields' => [$comment('Please proceed')],
        'actions' => ['approve' => ['label' => 'অনুমোদন করে প্রক্রিয়াকরণে প্রেরণ', 'next' => [['step' => 'S03_1']]]]],

    'S03_1' => ['label' => 'আবেদন যাচাই-বাছাই শুরু (কানুনগো ও এসি ল্যান্ড)', 'step_no' => '03.1', 'role' => 'lao', 'phase' => 'যাচাইকরণ ও সংশোধন', 'sla' => [10, 10],
        'fields' => [
            $f('kanungo_id', 'সংযুক্তি যাচাইয়ের জন্য কানুনগো', 'user', true, ['role' => 'kanungo']),
            $comment('আবেদনের সংযুক্তিগুলো প্রাথমিক যাচাই-বাছাই করা হয়েছে। চ-ফর্মের তথ্য যাচাই-বাছাইয়ের জন্য অনুরোধ করা হল।'),
        ],
        'actions' => ['send' => ['label' => 'দাখিল করুন', 'next' => [
            ['step' => 'S05A', 'assign' => 'kanungo_id', 'add_groups' => ['verify']],
            ['step' => 'S04', 'per' => 'upazila', 'add_groups' => ['verify']],
        ]]]],

    // --- Cha form verification (per upazila): AC Land -> ULAO -> Kanungo -> (Surveyor -> Kanungo) -> AC Land
    'S04' => ['label' => 'চ-ফরম যাচাইকরণ (ইউনিয়ন ভূমি সহকারী কর্মকর্তা নির্বাচন)', 'step_no' => '04', 'role' => 'ac_land', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$f('ulao_ids', 'ইউনিয়ন ভূমি সহকারী কর্মকর্তা (এক বা একাধিক)', 'users', true, ['role' => 'ulao']), $comment('')],
        'actions' => ['send' => ['label' => 'প্রেরণ করুন', 'next' => [['step' => 'S04_1', 'assign_many' => 'ulao_ids', 'inherit_upazila' => true, 'add_groups' => ['cha:{u}']]]]]],

    'S04_1' => ['label' => 'চ-ফরম যাচাইকরণ (ইউনিয়ন ভূমি সহকারী কর্মকর্তা)', 'step_no' => '04.1', 'role' => 'ulao', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$f('kanungo_id', 'কানুনগো', 'user', true, ['role' => 'kanungo']), $f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true)],
        'actions' => ['send' => ['label' => 'কানুনগোর নিকট প্রেরণ', 'next' => [['step' => 'S04_2', 'assign' => 'kanungo_id', 'inherit_upazila' => true]]]]],

    'S04_2' => ['label' => 'চ-ফরম যাচাইকরণ (কানুনগো)', 'step_no' => '04.2', 'role' => 'kanungo', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$comment('')],
        'actions' => [
            'correct' => ['label' => 'তথ্য সঠিক - এসি ল্যান্ডের নিকট প্রেরণ', 'next' => [['step' => 'S04_5', 'join' => ['cha:{u}'], 'drop_groups' => ['cha:{u}'], 'inherit_upazila' => true]]],
            'assign_surveyor' => ['label' => 'যাচাইয়ের জন্য সার্ভেয়ার নিয়োগ', 'fields' => [$f('surveyor_id', 'সার্ভেয়ার', 'user', true, ['role' => 'surveyor'])],
                'next' => [['step' => 'S04_3', 'assign' => 'surveyor_id', 'inherit_upazila' => true]]],
        ]],

    'S04_3' => ['label' => 'চ-ফরম যাচাইকরণ (সার্ভেয়ার)', 'step_no' => '04.3', 'role' => 'surveyor', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true)],
        'actions' => ['send' => ['label' => 'কানুনগোর নিকট প্রেরণ', 'next' => [['step' => 'S04_4', 'assign_parent_actor' => true, 'inherit_upazila' => true]]]]],

    'S04_4' => ['label' => 'সার্ভেয়ারের চ-ফরম পর্যালোচনা (কানুনগো)', 'step_no' => '04.4', 'role' => 'kanungo', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true)],
        'actions' => ['correct' => ['label' => 'তথ্য সঠিক - এসি ল্যান্ডের নিকট প্রেরণ', 'next' => [['step' => 'S04_5', 'join' => ['cha:{u}'], 'drop_groups' => ['cha:{u}'], 'inherit_upazila' => true]]]]],

    'S04_5' => ['label' => 'চ-ফরম পর্যালোচনা (এসি ল্যান্ড)', 'step_no' => '04.5', 'role' => 'ac_land', 'phase' => 'চ-ফর্ম যাচাই',
        'fields' => [$f('comment', 'যাচাইকারীর মন্তব্য', 'textarea', true, ['default' => 'আমি চেক করে দেখেছি এবং সমস্ত তথ্য সঠিক পেয়েছি'])],
        'actions' => ['send' => ['label' => 'এলএও-র নিকট প্রেরণ', 'next' => [['step' => 'S05', 'join' => ['verify'], 'drop_groups' => ['verify']]]]]],

    // --- Attachment verification: Kanungo -> Surveyor -> Kanungo -> LAO
    'S05A' => ['label' => 'সংযুক্তি যাচাইকরণ - সার্ভেয়ার নিয়োগ (কানুনগো)', 'step_no' => '05', 'role' => 'kanungo', 'phase' => 'সংযুক্তি যাচাই',
        'fields' => [$f('surveyor_id', 'সার্ভেয়ার', 'user', true, ['role' => 'surveyor']), $f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true)],
        'actions' => ['send' => ['label' => 'দাখিল করুন', 'next' => [['step' => 'S05B', 'assign' => 'surveyor_id']]]]],

    'S05B' => ['label' => 'সংযুক্তি যাচাইকরণ (সার্ভেয়ার)', 'step_no' => '05.2', 'role' => 'surveyor', 'phase' => 'সংযুক্তি যাচাই',
        'fields' => [$f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true)],
        'actions' => ['send' => ['label' => 'কানুনগোর নিকট প্রেরণ', 'next' => [['step' => 'S05C', 'assign_parent_actor' => true]]]]],

    'S05C' => ['label' => 'সার্ভেয়ারের সংযুক্তি পর্যালোচনা (কানুনগো)', 'step_no' => '05.3', 'role' => 'kanungo', 'phase' => 'সংযুক্তি যাচাই',
        'fields' => [$f('comment', 'সারসংক্ষেপ মন্তব্য', 'textarea', true, ['default' => 'সংযুক্তিগুলো সঠিক হিসেবে গণ্য হয়েছে এবং এলএও-র নিকট প্রেরণ করা হল।'])],
        'actions' => ['correct' => ['label' => 'এলএও-র নিকট প্রেরণ', 'next' => [['step' => 'S05', 'join' => ['verify'], 'drop_groups' => ['verify']]]]]],

    // --- LAO: proposal verification, refinement loop
    'S05' => ['label' => 'প্রস্তাব যাচাইকরণ ও সংশোধন (এলএও)', 'step_no' => '5', 'role' => 'lao', 'phase' => 'যাচাইকরণ ও সংশোধন',
        'fields' => [$comment('সংযুক্তিসমূহ সঠিক হিসাবে বিবেচনা করা যেতে পারে।')],
        'actions' => [
            'ok' => ['label' => 'চ-ফর্ম ও সংযুক্তি সঠিক - প্রেরণ করুন', 'guards' => ['attachments_ok'], 'next' => [['step' => 'S06']]],
            'refine' => ['label' => 'সংশোধনের জন্য প্রত্যাশী সংস্থার নিকট প্রেরণ', 'style' => 'danger', 'effects' => ['return_to_rb'],
                'fields' => [$f('refine_note', 'সংশোধনের বিবরণ', 'textarea', true, ['default' => 'সংশোধন প্রয়োজন'])],
                'next' => [['step' => 'S05R', 'assign_creator' => true]]],
        ]],

    'S05R' => ['label' => 'অধিগ্রহণের আবেদন সংশোধন (প্রত্যাশী সংস্থা)', 'step_no' => '5.3', 'role' => 'rb', 'phase' => 'যাচাইকরণ ও সংশোধন', 'component' => 'refine',
        'fields' => [$comment('প্রয়োজনীয় সংশোধন সম্পন্ন করা হয়েছে')],
        'actions' => ['resubmit' => ['label' => 'সংশোধিত আবেদন দাখিল করুন', 'effects' => ['resubmit_refined'], 'next' => [['step' => 'S05_4']]]]],

    'S05_4' => ['label' => 'আবেদন পুনরায় যাচাই (এলএও)', 'step_no' => '5.4', 'role' => 'lao', 'phase' => 'যাচাইকরণ ও সংশোধন',
        'fields' => [$f('upazila_ids', 'যে উপজেলার চ-ফরম পুনরায় যাচাই প্রয়োজন', 'upazilas', true), $comment('চ-ফর্ম যাচাইয়ের জন্য এসি ল্যান্ডের কাছে পাঠানো হল')],
        'actions' => ['send' => ['label' => 'এসি ল্যান্ডের নিকট প্রেরণ', 'next' => [['step' => 'S04R', 'per' => 'field:upazila_ids', 'add_groups' => ['reverify']]]]]],

    'S04R' => ['label' => 'পুনরায় আবেদন যাচাই-বাছাই (চ-ফর্ম) - এসি ল্যান্ড', 'step_no' => '5.5', 'role' => 'ac_land', 'phase' => 'যাচাইকরণ ও সংশোধন',
        'fields' => [$f('comment', 'যাচাইকারীর মন্তব্য', 'textarea', true, ['default' => 'আমি চেক করে দেখেছি এবং সমস্ত তথ্য সঠিক পেয়েছি'])],
        'actions' => ['correct' => ['label' => 'এলএও-র নিকট প্রেরণ', 'next' => [['step' => 'S05', 'join' => ['reverify'], 'drop_groups' => ['reverify']]]]]],

    // ------------------------------------------------------------------ Phase 2: decision, feasibility (Step 6-8)
    'S06' => ['label' => 'অধিগ্রহণ আবেদনের প্রাথমিক সিদ্ধান্ত', 'step_no' => '6', 'role' => 'dc', 'phase' => 'প্রাথমিক সিদ্ধান্ত',
        'fields' => [$comment('যাচাইকরণ সম্পন্ন হয়েছে। সম্ভাব্যতা যাচাইকরণ প্রক্রিয়া শুরুর অনুমোদন দেওয়া হল।')],
        'actions' => [
            'approve' => ['label' => 'অনুমোদন নিশ্চিত করুন', 'next' => [['step' => 'S07_1']]],
            'reject' => ['label' => 'নামঞ্জুর', 'style' => 'danger', 'effects' => ['close_application:rejected'], 'next' => []],
        ]],

    'S07_1' => ['label' => 'সম্ভাব্যতা যাচাই কমিটি গঠন ও সময়সূচী নির্ধারণ', 'step_no' => '7.1', 'role' => 'adc', 'phase' => 'সম্ভাব্যতা যাচাই',
        'fields' => [$members, $f('meeting_date', 'তারিখ', 'date', true), $f('meeting_time', 'সময়', 'time', true), $f('place', 'স্থান', 'text', true)],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['save_record:committee_feasibility'], 'next' => [['step' => 'S07_2']]]]],

    'S07_2' => ['label' => 'সম্ভাব্যতা যাচাই প্রতিবেদন দাখিল', 'step_no' => '7.2', 'role' => 'adc', 'phase' => 'সম্ভাব্যতা যাচাই', 'sla' => [21, 21],
        'fields' => [
            $f('details', 'প্রতিবেদনের বিস্তারিত', 'textarea', true, ['default' => 'দাখিলকৃত অধিগ্রহণ প্রস্তাব, সংশ্লিষ্ট কাগজপত্র ও প্রদত্ত তথ্যাদি পর্যালোচনা করা হয়েছে। প্রকল্পটির জনস্বার্থ ও জনপ্রয়োজন বিবেচনায় প্রস্তাবটি পর্যালোচনা করা হয়। প্রস্তাবিত ভূমির প্রয়োজনীয়তা, বিকল্প স্থান, ক্ষতিগ্রস্ত/পুনর্বাসনের সম্ভাবনা, ভূমির শ্রেণি, বিদ্যমান স্থাপনা ও ধর্মীয় প্রতিষ্ঠানসহ সংশ্লিষ্ট বিষয়সমূহ যাচাই করা হয়েছে।']),
            $f('items', 'যাচাইয়ের বিষয়সমূহ (মতামত)', 'component', true, ['component' => 'feasibility']),
            $f('summary', 'প্রতিবেদনের সারাংশ', 'textarea', true, ['default' => 'দাখিলকৃত তথ্য ও কাগজপত্র পর্যালোচনায় প্রস্তাবিত প্রকল্পের জন্য ভূমি অধিগ্রহণের প্রয়োজনীয়তা প্রতীয়মান হয়েছে। সম্ভাব্যতা যাচাইয়ের আওতাভুক্ত বিষয়সমূহ পর্যালোচনা করা হয়েছে।']),
        ],
        'actions' => ['submit' => ['label' => 'সংরক্ষণ করুন এবং প্রেরণ', 'effects' => ['save_record:feasibility_report'], 'next' => [['step' => 'S08_1']]]]],

    'S08_1' => ['label' => 'সম্ভাব্যতা যাচাই প্রতিবেদন অনুমোদন ও সভা কমিটি নির্বাচন', 'step_no' => '8.1', 'role' => 'dc', 'phase' => 'অনুমোদন সভা',
        'fields' => [$f('committee', 'সভা আহ্বানের জন্য কমিটি', 'select', true, ['options' => ['district' => 'জেলা ভূমি বরাদ্দ কমিটি', 'central' => 'কেন্দ্রীয় ভূমি বরাদ্দ কমিটি']]),
            $comment('উপস্থাপিত সম্ভাব্যতা যাচাই প্রতিবেদনটি সন্তোষজনক বিবেচিত হওয়ায় অনুমোদন প্রদান করা হল।')],
        'actions' => ['approve' => ['label' => 'সুপারিশ করা হল - দাখিল করুন', 'next' => [['step' => 'S08_2']]]]],

    'S08_2' => ['label' => 'অনুমোদন সভার আলোচ্য বিষয় ও সময়সূচী নির্ধারণ', 'step_no' => '8.2', 'role' => 'dc', 'phase' => 'অনুমোদন সভা',
        'fields' => [$f('subject', 'বিষয়', 'text', true), $f('meeting_date', 'তারিখ', 'date', true), $f('meeting_time', 'সময়', 'time', true), $f('place', 'স্থান', 'text', true)],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['save_record:meeting'], 'next' => [['step' => 'S08_3']]]]],

    'S08_3' => ['label' => 'অনুমোদন সভার কার্যবিবরণী দাখিল', 'step_no' => '8.3', 'role' => 'dc', 'phase' => 'অনুমোদন সভা',
        'fields' => [$f('discussion', 'আলোচনা', 'textarea', true, ['default' => '১. আবেদনকারী প্রতিষ্ঠান কর্তৃক দাখিলকৃত ভূমি অধিগ্রহণ প্রস্তাব, সম্ভাব্যতা যাচাই প্রতিবেদন, প্রয়োজনীয় কাগজপত্র এবং সংশ্লিষ্ট তথ্যাদি সভায় বিস্তারিতভাবে পর্যালোচনা করা হয়।']),
            $comment('উপস্থাপিত নথিপত্র ও আলোচনার প্রেক্ষিতে প্রস্তাবিত ভূমি অধিগ্রহণের আবেদনটি অনুমোদনের জন্য সুপারিশ করা হল।')],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['save_record:meeting_minutes'], 'next' => [['step' => 'S08_4']]]]],

    'S08_4' => ['label' => 'অধিগ্রহণের প্রাথমিক সিদ্ধান্ত', 'step_no' => '8.4', 'role' => 'dc', 'phase' => 'অনুমোদন সভা',
        'fields' => [$comment('অনুমোদনের প্রাথমিক সিদ্ধান্ত গ্রহণ করা হল')],
        'actions' => [
            'approve' => ['label' => 'সিদ্ধান্ত দাখিল', 'next' => [['step' => 'S09']]],
            'reject' => ['label' => 'নামঞ্জুর', 'style' => 'danger', 'effects' => ['close_application:rejected'], 'next' => []],
        ]],

    // ------------------------------------------------------------------ Phase 3: media, sec 4(1) notice, joint inquiry
    'S09' => ['label' => 'ভিডিও/স্থিরচিত্র সংগ্রহ', 'step_no' => '9', 'role' => 'lao', 'phase' => 'তথ্য সংগ্রহ',
        'fields' => [$f('media', 'দাগভিত্তিক ভিডিও/স্থিরচিত্র ও বর্ণনা', 'component', true, ['component' => 'parcel_media']), $comment('ভিডিওতে প্রদর্শিত অবকাঠামো, গাছপালা ও অন্যান্য উপরিস্থিত সম্পদের অস্তিত্ব স্থল পরিদর্শনে নিশ্চিত হওয়ায় তথ্যসমূহ গ্রহণযোগ্য বিবেচিত হল।')],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'guards' => ['media_ok'], 'effects' => ['save_record:media'], 'next' => [['step' => 'S10']]]]],

    'S10' => ['label' => 'ভিডিও/স্থিরচিত্র অনুমোদন (এলএ কেস নম্বর নির্ধারণ)', 'step_no' => '10', 'role' => 'dc', 'phase' => 'তথ্য সংগ্রহ',
        'fields' => [$comment('ভিডিও/স্থিরচিত্র অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন', 'effects' => ['assign_la_case_no'], 'next' => [['step' => 'S11']]]]],

    'S11' => ['label' => '৪(১) ধারার নোটিশ জারি', 'step_no' => '11', 'role' => 'lao', 'phase' => '৪(১) ধারার নোটিশ',
        'fields' => [$f('notice_body', 'নোটিশের পাঠ (সম্পাদনযোগ্য)', 'component', true, ['component' => 'notice', 'notice' => '4_1'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'effects' => ['draft_notice:4_1'], 'next' => [['step' => 'S12_1']]]]],

    'S12_1' => ['label' => '৪(১) ধারার নোটিশ অনুমোদন', 'step_no' => '12.1', 'role' => 'dc', 'phase' => '৪(১) ধারার নোটিশ',
        'fields' => [$comment('বিধি ৪(১) ধারার নোটিশ অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন', 'effects' => ['approve_notice:4_1'], 'next' => [['step' => 'S12_2']]]]],

    'S12_2' => ['label' => 'যৌথ তদন্ত কমিটি গঠন ও সময়সূচী নির্ধারণ', 'step_no' => '12.2', 'role' => 'dc', 'phase' => 'যৌথ তদন্ত',
        'fields' => [$members, $f('meeting_date', 'তারিখ', 'date', true), $f('meeting_time', 'সময়', 'time', true), $f('place', 'স্থান', 'text', true)],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['save_record:committee_joint'], 'next' => [['step' => 'S13']]]]],

    // ------------------------------------------------------------------ Phase 4: field book, class change
    'S13' => ['label' => 'ফিল্ডবহি প্রস্তুতকরণ', 'step_no' => '13', 'role' => 'adc', 'phase' => 'ফিল্ডবহি',
        'fields' => [$f('field_book', 'যৌথ তদন্তের ফিল্ড বহি (দাগভিত্তিক)', 'component', true, ['component' => 'field_book']),
            $f('class_change_needed', 'শ্রেণি পরিবর্তনের প্রয়োজন আছে কি?', 'select', true, ['options' => $yesno, 'default' => 'no']),
            $comment('যৌথ তদন্ত কমিটির স্থল পরিদর্শনে উপরোক্ত তথ্য যাচাই করা হয়েছে এবং বাস্তব অবস্থার সঙ্গে সামঞ্জস্য পাওয়া গেছে।')],
        'actions' => ['forward' => ['label' => 'ফরোয়ার্ড করুন', 'effects' => ['save_field_book'], 'next' => [
            ['step' => 'S14', 'when' => ['class_change_needed', 'no']],
            ['step' => 'S13_1', 'when' => ['class_change_needed', 'yes']],
        ]]]],

    'S13_1' => ['label' => 'শ্রেণি পরিবর্তনের মিসকেস', 'step_no' => '13.1', 'role' => 'adc', 'phase' => 'ফিল্ডবহি',
        'fields' => [$f('subject', 'বিষয়', 'text', true, ['default' => 'অধিগ্রহণভুক্ত ভূমির শ্রেণি পরিবর্তন সংক্রান্ত বিষয়']),
            $f('details', 'বিস্তারিত', 'textarea', true, ['default' => 'অধিগ্রহণভুক্ত ভূমির রেকর্ডীয় শ্রেণি ও বাস্তব শ্রেণির মধ্যে অমিল পরিলক্ষিত হওয়ায় শ্রেণি পরিবর্তনের বিষয়টি যাচাই ও প্রয়োজনীয় কার্যক্রম গ্রহণের জন্য উপস্থাপন করা হল।'])],
        'actions' => ['forward' => ['label' => 'ফরোয়ার্ড করুন', 'effects' => ['save_record:class_change_case'], 'next' => [['step' => 'S13_2']]]]],

    'S13_2' => ['label' => 'শ্রেণি পরিবর্তনের রিপোর্ট (এলএও)', 'step_no' => '13.2', 'role' => 'lao', 'phase' => 'ফিল্ডবহি',
        'fields' => [$f('class_changes', 'পরিবর্তিত জমির শ্রেণি (দাগভিত্তিক)', 'component', true, ['component' => 'class_changes']), $f('subject', 'বিষয়', 'text', true, ['default' => 'শ্রেণী পরিবর্তনের রিপোর্ট'])],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['apply_class_changes'], 'next' => [['step' => 'S13_3']]]]],

    'S13_3' => ['label' => 'ফিল্ডবহি পুনরায় ফরোয়ার্ড (এডিসি)', 'step_no' => '13.3', 'role' => 'adc', 'phase' => 'ফিল্ডবহি',
        'fields' => [$comment('শ্রেণি পরিবর্তনসহ ফিল্ডবহি ফরোয়ার্ড করা হল', false)],
        'actions' => ['forward' => ['label' => 'ফরোয়ার্ড করুন', 'next' => [['step' => 'S14']]]]],

    'S14' => ['label' => 'ফিল্ডবহি অনুমোদন', 'step_no' => '14.1', 'role' => 'dc', 'phase' => 'ফিল্ডবহি',
        'fields' => [$comment('যৌথ তদন্তের ফিল্ড বহি (দাগভিত্তিক) অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন', 'effects' => ['approve_field_book'], 'next' => [['step' => 'S15']]]]],

    'S15' => ['label' => '৭(১) ধারার নোটিশ প্রস্তুত ও দাখিল', 'step_no' => '15', 'role' => 'lao', 'phase' => '৭(১) ধারার নোটিশ',
        'fields' => [$f('notice_body', 'নোটিশের পাঠ (সম্পাদনযোগ্য)', 'component', true, ['component' => 'notice', 'notice' => '7_1'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'effects' => ['draft_notice:7_1'], 'next' => [['step' => 'S16']]]]],

    'S16' => ['label' => '৭(১) ধারার নোটিশ অনুমোদন ও প্রকাশ', 'step_no' => '16', 'role' => 'dc', 'phase' => '৭(১) ধারার নোটিশ',
        'fields' => [$comment('বিধি ৭(১) ধারার নোটিশ অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন', 'effects' => ['approve_notice:7_1'], 'next' => [['step' => 'S20']]]]],

    // ------------------------------------------------------------------ Phase 5: ownership claims (citizen-initiated)
    'S18' => ['label' => 'মালিকানা দাবি যাচাই', 'step_no' => '18', 'role' => 'lao', 'phase' => 'মালিকানা দাবি', 'component' => 'claim',
        'fields' => [$comment('')],
        'actions' => [
            'verified' => ['label' => 'সঠিক - যাচাই সম্পন্ন', 'effects' => ['claim_verified'], 'next' => []],
            'send_to_dc' => ['label' => 'ফিল্ডবহি সংশোধনের জন্য ডিসির নিকট প্রেরণ', 'effects' => ['claim_sent_to_dc'], 'next' => [['step' => 'S19']]],
            'reject' => ['label' => 'দাবি নাকচ', 'style' => 'danger', 'effects' => ['claim_rejected'], 'next' => []],
        ]],

    'S19' => ['label' => 'ফিল্ডবহি সংশোধন (ডিসি)', 'step_no' => '19', 'role' => 'dc', 'phase' => 'মালিকানা দাবি', 'component' => 'claim',
        'fields' => [$f('field_book_update', 'ফিল্ড বহি পরিবর্তনের বিবরণ', 'textarea', true), $comment('')],
        'actions' => ['approve' => ['label' => 'অনুমোদন', 'effects' => ['claim_field_book_updated'], 'next' => []]]],

    // ------------------------------------------------------------------ Phase 6: valuation, estimate, roydad
    'S20' => ['label' => 'মূল্যতালিকার জন্য অন্যান্য বিভাগীয় কর্মকর্তাদের নিকট চিঠি প্রেরণ', 'step_no' => '20', 'role' => 'lao', 'phase' => 'মূল্য নির্ধারণ',
        'fields' => [
            $f('subject', 'বিষয়', 'text', true, ['default' => 'মূল্যতালিকা যাচাই ও মতামত প্রদানের জন্য সাব-রেজিস্ট্রারসহ অন্যান্য বিভাগীয় কর্মকর্তাদের নিকট চিঠি প্রেরণ']),
            $f('desc_sub_registrar', 'বর্ণনা (সাব-রেজিস্ট্রার)', 'textarea', true, ['default' => 'উপযুক্ত বিষয়ের প্রেক্ষিতে জানানো যাচ্ছে যে, সংশ্লিষ্ট এলাকার প্রস্তাবিত ভূমির অধিগ্রহণ কার্যক্রমের নিমিত্তে সঠিক বাজার মূল্য নির্ধারণ করা আবশ্যক। সাব-রেজিস্ট্রি অফিসের রেকর্ডপত্র ও সাম্প্রতিক দলিল হস্তান্তরের তথ্যাদি পর্যালোচনা করে প্রস্তাবিত ভূমির সঠিক মূল্যতালিকা প্রস্তুত ও প্রত্যয়নপূর্বক জরুরি ভিত্তিতে এই দপ্তরে প্রেরণের জন্য অনুরোধ করা হল।']),
            $f('desc_officers', 'বর্ণনা (অন্যান্য বিভাগীয় কর্মকর্তা)', 'textarea', true, ['default' => 'চলমান ভূমি অধিগ্রহণ প্রক্রিয়ার অংশ হিসেবে প্রস্তাবিত প্রকল্পের আওতাধীন এলাকার ভূমির সঠিক বাজার মূল্য যাচাই করা প্রয়োজন। উক্ত ভূমির বর্তমান উপযোগিতা, অবকাঠামোগত অবস্থা এবং আপনার বিভাগের সংশ্লিষ্ট রেকর্ড পর্যালোচনা করে বাস্তবসম্মত মূল্যতালিকা প্রস্তুত ও আপনার মতামত জরুরি ভিত্তিতে এই দপ্তরে প্রেরণের জন্য অনুরোধ করা হল।']),
        ],
        'actions' => ['send' => ['label' => 'চিঠি প্রেরণ করুন', 'guards' => ['no_pending:claims'], 'effects' => ['save_record:price_letter'], 'next' => [['step' => 'S20_1']]]]],

    'S20_1' => ['label' => 'মূল্য নির্ধারণের তালিকা', 'step_no' => '20.1', 'role' => 'lao', 'phase' => 'মূল্য নির্ধারণ',
        'fields' => [$f('valuation', 'দাগভিত্তিক মূল্য নির্ধারণ (দলিলমূল্য নমুনা)', 'component', true, ['component' => 'valuation']), $comment('মূল্য নির্ধারণ ও নথি সংযুক্ত করা হয়েছে', false)],
        'actions' => ['save' => ['label' => 'সেভ করুন', 'effects' => ['save_record:valuation'], 'next' => [['step' => 'S20_2']]]]],

    'S20_2' => ['label' => 'প্রাক্কলন ও রোয়েদাদ তৈরি', 'step_no' => '20.2', 'role' => 'lao', 'phase' => 'রোয়েদাদ', 'sla' => [30, 15],
        'fields' => [$f('estimate', 'ক্ষতিপূরণের প্রাক্কলন ও রোয়েদাদ বহি', 'component', true, ['component' => 'estimate'])],
        'actions' => ['submit' => ['label' => 'রোয়েদাদ বহি দাখিল করুন', 'guards' => ['estimate_ok'], 'effects' => ['save_roydad'], 'next' => [['step' => 'S21']]]]],

    'S21' => ['label' => 'রোয়েদাদ অনুমোদন ও প্রকাশ', 'step_no' => '21.1', 'role' => 'dc', 'phase' => 'রোয়েদাদ',
        'fields' => [$comment('রোয়েদাদ অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন ও গণবিজ্ঞপ্তি দাখিল', 'effects' => ['approve_roydad'], 'next' => [['step' => 'S21_2']]]]],

    'S21_2' => ['label' => 'ক্ষতিপূরণের অর্থ জমাদানের চিঠি প্রেরণ', 'step_no' => '21.2', 'role' => 'dc', 'phase' => 'ক্ষতিপূরণ জমা',
        'fields' => [$f('subject', 'বিষয়', 'text', true, ['default' => 'ক্ষতিপূরণের অর্থ জমাদান সংক্রান্ত']),
            $f('details', 'বিস্তারিত', 'textarea', true, ['default' => 'অধিগ্রহণকৃত ভূমির বিপরীতে প্রযোজ্য ক্ষতিপূরণের অর্থ নির্ধারিত বিধি অনুযায়ী সংশ্লিষ্ট দপ্তরে জমাদানের জন্য প্রয়োজনীয় চিঠি প্রেরণ সংক্রান্ত বিষয়।'])],
        'actions' => ['send' => ['label' => 'ক্ষতিপূরণের অর্থ প্রদান করুন (চিঠি প্রেরণ)', 'effects' => ['create_deposit_demand'], 'next' => [['step' => 'S22', 'assign_creator' => true]]]]],

    'S22' => ['label' => 'ক্ষতিপূরণের অর্থ জমা (প্রত্যাশী সংস্থা)', 'step_no' => '22', 'role' => 'rb', 'phase' => 'ক্ষতিপূরণ জমা', 'component' => 'deposit', 'sla' => [120, 120],
        'fields' => [$f('method', 'জমার মাধ্যম', 'select', true, ['options' => ['challan' => 'ট্রেজারি চালান', 'cheque' => 'ব্যাংক চেক/পে-অর্ডার', 'bank_transfer' => 'ব্যাংক ট্রান্সফার']]),
            $f('reference_no', 'চালান/রেফারেন্স নং', 'text', true), $f('paid_amount', 'জমাকৃত অর্থ (৳)', 'number', true), $f('paid_date', 'জমার তারিখ', 'date', true)],
        'actions' => ['pay' => ['label' => 'ক্ষতিপূরণের অর্থ জমা দিন', 'guards' => ['deposit_amount_ok'], 'effects' => ['record_deposit'], 'next' => [['step' => 'S22V']]]]],

    'S22V' => ['label' => 'ক্ষতিপূরণের জমা যাচাই (হিসাব কর্মকর্তা)', 'step_no' => '22.1', 'role' => 'accounts', 'phase' => 'ক্ষতিপূরণ জমা', 'component' => 'deposit_verify',
        'fields' => [$comment('জমাকৃত অর্থ চালান/ব্যাংক বিবরণীর সাথে যাচাই করা হয়েছে', true)],
        'actions' => [
            'verify' => ['label' => 'জমা যাচাই সম্পন্ন - এলএও-র নিকট প্রেরণ', 'effects' => ['verify_deposit'], 'next' => [['step' => 'S23']]],
            'reject' => ['label' => 'জমা গ্রহণযোগ্য নয় - প্রত্যাশী সংস্থার নিকট ফেরত', 'style' => 'danger', 'effects' => ['reject_deposit'], 'next' => [['step' => 'S22', 'assign_creator' => true]]],
        ]],

    'S23' => ['label' => '৮(৩) ধারা নোটিশ প্রস্তুত', 'step_no' => '23', 'role' => 'lao', 'phase' => '৮(৩) ধারার নোটিশ',
        'fields' => [$f('distribution_date', 'ক্ষতিপূরণ বিতরণের তারিখ', 'date', true), $f('place', 'স্থান', 'text', true),
            $f('notice_body', 'নোটিশের পাঠ (সম্পাদনযোগ্য)', 'component', true, ['component' => 'notice', 'notice' => '8_3'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন (নোটিশ সংরক্ষণ)', 'guards' => ['no_pending:objections'], 'effects' => ['draft_notice:8_3'], 'next' => [['step' => 'S24']]]]],

    'S24' => ['label' => '৮(৩) ধারার নোটিশ অনুমোদন ও প্রকাশ', 'step_no' => '24', 'role' => 'dc', 'phase' => '৮(৩) ধারার নোটিশ',
        'fields' => [$comment('৮(৩) ধারার নোটিশ অনুমোদন ও প্রকাশ করা হল', false)],
        'actions' => ['publish' => ['label' => 'অনুমোদন ও প্রকাশ করুন', 'effects' => ['approve_notice:8_3'], 'next' => [['step' => 'S28']]]]],

    // ------------------------------------------------------------------ Phase 7: compensation applications (citizen-initiated)
    'S26' => ['label' => 'ক্ষতিপূরণের আবেদন যাচাই', 'step_no' => '26', 'role' => 'lao', 'phase' => 'ক্ষতিপূরণ আবেদন', 'component' => 'comp_app',
        'fields' => [$comment('')],
        'actions' => [
            'approve' => ['label' => 'অনুমোদন - ক্ষতিপূরণ বিতরণের জন্য প্রেরণ', 'effects' => ['comp_app_approved'], 'next' => [['step' => 'S27']]],
            'reject' => ['label' => 'আবেদন নাকচ', 'style' => 'danger', 'effects' => ['comp_app_rejected'], 'next' => []],
        ]],

    'S27' => ['label' => 'ক্ষতিপূরণ বিতরণ (ডিসি)', 'step_no' => '27', 'role' => 'dc', 'phase' => 'ক্ষতিপূরণ বিতরণ', 'sla' => [60, 60], 'component' => 'comp_app',
        'fields' => [$f('method', 'প্রদানের মাধ্যম', 'select', true, ['options' => ['mobile_banking' => 'মোবাইল ব্যাংকিং', 'bank_transfer' => 'ব্যাংক ট্রান্সফার', 'cheque' => 'চেক']]),
            $f('reference', 'লেনদেন/চেক নং', 'text', true)],
        'actions' => ['pay' => ['label' => 'ক্ষতিপূরণ প্রদান করুন', 'effects' => ['distribute_payment'], 'next' => []]]],

    'S28' => ['label' => 'দখল হস্তান্তরের চিঠি ও চুক্তিপত্র প্রেরণ', 'step_no' => '28', 'role' => 'lao', 'phase' => 'দখল হস্তান্তর',
        'fields' => [$f('handover_place', 'দখল হস্তান্তরের স্থান', 'text', true), $f('handover_date', 'তারিখ', 'date', true), $f('handover_time', 'সময়', 'time', true),
            $f('agreement_note', 'প্রত্যাশী সংস্থার নিকট চুক্তিপত্র প্রেরণ সংক্রান্ত মন্তব্য', 'textarea', true, ['default' => 'ধারা ১৭ অনুযায়ী চুক্তিপত্র প্রেরণ করা হল।'])],
        'actions' => ['send' => ['label' => 'চিঠি প্রেরণ করুন', 'guards' => ['no_pending:compensation'], 'effects' => ['save_record:handover_letter'], 'next' => [['step' => 'S29']]]]],

    'S29' => ['label' => 'বিধিভূমির দখল প্রদান (ফর্ম ১৬) অনুমোদন', 'step_no' => '29', 'role' => 'dc', 'phase' => 'দখল হস্তান্তর',
        'fields' => [$comment('ফর্ম ১৬ অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন', 'effects' => ['approve_form16'], 'next' => [['step' => 'S30']]]]],

    'S30' => ['label' => 'ঘোষণা ফর্ম তৈরি ও দাখিল', 'step_no' => '30', 'role' => 'lao', 'phase' => 'গেজেট',
        'fields' => [$f('declaration', 'ঘোষণা (বিবরণ)', 'textarea', true, ['default' => 'ধারা ১৩ অনুযায়ী ক্ষতিপূরণ প্রদান সম্পন্ন হওয়ায় অধিগ্রহণকৃত সম্পত্তি দায়মুক্ত হয়ে সম্পূর্ণরূপে সরকারে ন্যস্ত হয়েছে মর্মে ঘোষণা।'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'effects' => ['save_record:declaration'], 'next' => [['step' => 'S31A']]]]],

    'S31A' => ['label' => 'গ্যাজেট প্রকাশের জন্য ফরম (ফর্ম ১৭) অনুমোদন', 'step_no' => '31a', 'role' => 'dc', 'phase' => 'গেজেট',
        'fields' => [$comment('ফর্ম ১৭ অনুমোদন করা হল', false)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন', 'next' => [['step' => 'S31B']]]]],

    'S31B' => ['label' => 'গেজেট প্রকাশ', 'step_no' => '31b', 'role' => 'dc', 'phase' => 'গেজেট', 'sla' => [90, 90],
        'fields' => [$f('gazette_no', 'গেজেট নং', 'text', true), $f('gazette_date', 'গেজেট প্রকাশের তারিখ', 'date', true)],
        'actions' => ['publish' => ['label' => 'গেজেট প্রকাশ রেকর্ড করুন', 'effects' => ['save_record:gazette'], 'next' => [['step' => 'S31C']]]]],

    'S31C' => ['label' => 'হস্তান্তর দলিল (ফর্ম ১৯) অনুমোদন', 'step_no' => '31c', 'role' => 'dc', 'phase' => 'গেজেট',
        'fields' => [$f('deed_no', 'দলিল নং', 'text', true), $f('deed_date', 'দলিলের তারিখ', 'date', true)],
        'actions' => ['approve' => ['label' => 'অনুমোদন করুন - মামলা সম্পন্ন', 'effects' => ['save_record:transfer_deed', 'complete_application'], 'next' => []]]],

    // ------------------------------------------------------------------ Objection flow (citizen files, DC/Commissioner decide)
    'O02A' => ['label' => 'আপত্তির নিষ্পত্তি সভার সময়সূচী নির্ধারণ', 'step_no' => 'আপত্তি-২a', 'role' => 'dc', 'phase' => 'আপত্তি ও শুনানি', 'component' => 'objection',
        'fields' => [$f('hearing_date', 'তারিখ', 'date', true), $f('hearing_time', 'সময়', 'time', true)],
        'actions' => ['save' => ['label' => 'সংরক্ষণ করুন', 'effects' => ['objection_hearing_set'], 'next' => [['step' => 'O02B']]]]],

    'O02B' => ['label' => 'শুনানির প্রতিবেদন দাখিল ও ফরোয়ার্ড', 'step_no' => 'আপত্তি-২b', 'role' => 'dc', 'phase' => 'আপত্তি ও শুনানি', 'component' => 'objection',
        'fields' => [$f('decision_text', 'আপিলের বিরুদ্ধে সিদ্ধান্ত', 'textarea', true, ['default' => 'আবেদনকারী/স্বার্থসংশ্লিষ্ট পক্ষের দাখিলকৃত কাগজপত্র, সংশ্লিষ্ট নথি ও বক্তব্য পর্যালোচনা করে প্রযোজ্য আইন ও বিধি অনুসারে পরবর্তী প্রয়োজনীয় কার্যক্রম গ্রহণের জন্য সুপারিশ করা হল।']),
            $f('forward_to', 'ফরোয়ার্ড', 'select', true, ['options' => ['commissioner' => 'বিভাগীয় কমিশনার', 'ministry' => 'ভূমি মন্ত্রণালয়'], 'default' => 'commissioner'])],
        'actions' => ['forward' => ['label' => 'প্রেরণ করুন', 'effects' => ['objection_report'], 'next' => [['step' => 'O02C']]]]],

    'O02C' => ['label' => 'আপত্তির প্রাথমিক সিদ্ধান্ত (বিভাগীয় কমিশনার)', 'step_no' => 'আপত্তি-২c', 'role' => 'commissioner', 'phase' => 'আপত্তি ও শুনানি', 'component' => 'objection',
        'fields' => [$f('decision_text', 'বিভাগীয় কমিশনারের সিদ্ধান্ত', 'textarea', true, ['default' => 'আপিলকারী ও সংশ্লিষ্ট পক্ষগণের দাখিলকৃত আবেদনের লিখিত বক্তব্য, শুনানির প্রতিবেদন এবং সংশ্লিষ্ট নথিপত্র নিরপেক্ষভাবে পর্যালোচনা করা হল। পর্যালোচনা শেষে সার্বিক তথ্যাদি বিবেচনা করে প্রযোজ্য আইন ও বিধি মোতাবেক পরবর্তী প্রয়োজনীয় প্রশাসনিক ও আইনানুগ ব্যবস্থা গ্রহণের জন্য সুপারিশ করা হল।'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'effects' => ['objection_primary_decision'], 'next' => [['step' => 'O02D']]]]],

    'O02D' => ['label' => 'আপত্তির চূড়ান্ত সিদ্ধান্ত (জেলা প্রশাসক)', 'step_no' => 'আপত্তি-২d', 'role' => 'dc', 'phase' => 'আপত্তি ও শুনানি', 'component' => 'objection',
        'fields' => [$f('decision_text', 'জেলা প্রশাসকের সিদ্ধান্ত / সুনির্দিষ্ট মন্তব্য', 'textarea', true, ['default' => 'প্রকল্পের লক্ষ্য ও উদ্দেশ্য বাস্তবায়নে প্রস্তাবিত জমির প্রয়োজনীয়তা, সরেজমিন তদন্ত প্রতিবেদন এবং শুনানি প্রক্রিয়ায় দাখিলকৃত তথ্যাদি ও নথিপত্র যথাযথভাবে পর্যালোচনা করা হল। সার্বিক বিষয় বিবেচনায় জমিটি অধিগ্রহণ করা যুক্তিযুক্ত প্রতীয়মান হওয়ায় প্রচলিত স্থাবর সম্পত্তি অধিগ্রহণ ও হুকুমদখল আইন এবং বিধি অনুযায়ী পরবর্তী প্রয়োজনীয় ব্যবস্থা গ্রহণের জন্য সুপারিশ প্রদান করা হল।'])],
        'actions' => ['submit' => ['label' => 'দাখিল করুন', 'effects' => ['objection_final_decision'], 'next' => []]]],
];
