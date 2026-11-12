<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationLand;
use App\Models\District;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\LandClass;
use App\Models\LandOwner;
use App\Models\Mouza;
use App\Models\RequiringBody;
use App\Models\Upazila;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Lookups + one demo user per role (same login ids as the workflow script) + one draft application with the script's five lands. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $div = [];
        foreach ([['10', 'বরিশাল'], ['20', 'চট্টগ্রাম'], ['30', 'ঢাকা'], ['40', 'খুলনা'], ['45', 'ময়মনসিংহ'], ['50', 'রাজশাহী'], ['55', 'রংপুর'], ['60', 'সিলেট']] as [$c, $n]) {
            $div[$c] = Division::updateOrCreate(['code' => $c], ['name_bn' => $n]);
        }
        $dist = District::updateOrCreate(['code' => '91'], ['division_id' => $div['60']->id, 'short_code' => 'SYL', 'name_bn' => 'সিলেট']);
        $up = [];
        foreach ([['KNG', 'কানাইঘাট'], ['JKG', 'জকিগঞ্জ'], ['BLG', 'বালাগঞ্জ'], ['SSD', 'সিলেট সদর']] as [$c, $n]) $up[$c] = Upazila::updateOrCreate(['code' => $c], ['district_id' => $dist->id, 'name_bn' => $n]);
        $mouza = [
            'KNG' => Mouza::firstOrCreate(['upazila_id' => $up['KNG']->id, 'name_bn' => 'দর্পনগর পূর্ব'], ['jl_no' => '45']),
            'JKG' => Mouza::firstOrCreate(['upazila_id' => $up['JKG']->id, 'name_bn' => 'পূর্ব খলাছরা'], ['jl_no' => '68']),
            'BLG' => Mouza::firstOrCreate(['upazila_id' => $up['BLG']->id, 'name_bn' => 'শ্রীনাথপুর'], ['jl_no' => '22']),
            'SSD' => Mouza::firstOrCreate(['upazila_id' => $up['SSD']->id, 'name_bn' => 'দর্শা'], ['jl_no' => '84']),
        ];
        $cls = [];
        foreach (['aman' => 'আমন', 'nal' => 'নাল', 'bhiti' => 'ভিটি', 'bagan' => 'বাগান', 'pukur' => 'পুকুর', 'doba' => 'ডোবা', 'jolashoy' => 'জলাশয়', 'commercial' => 'বাণিজ্যিক', 'industrial' => 'শিল্প',
            'graveyard' => 'কবরস্থান/শ্মশান', 'religious' => 'ধর্মীয় প্রতিষ্ঠান', 'education' => 'শিক্ষা প্রতিষ্ঠান', 'forest' => 'বন', 'other' => 'অন্যান্য'] as $c => $n) {
            $cls[$c] = LandClass::updateOrCreate(['code' => $c], ['name_bn' => $n]);
        }
        foreach (['02-21' => 'শহীদ দিবস', '03-26' => 'স্বাধীনতা দিবস', '04-14' => 'পহেলা বৈশাখ', '05-01' => 'মে দিবস', '12-16' => 'বিজয় দিবস'] as $md => $n) {
            foreach ([2026, 2027, 2028] as $y) Holiday::updateOrCreate(['holiday_date' => "$y-$md"], ['name' => $n]);
        }

        $body = RequiringBody::updateOrCreate(['name_bn' => 'স্থানীয় সরকার প্রকৌশল অধিদপ্তর (এলজিইডি)'], ['name_en' => 'Local Government Engineering Department (LGED)', 'body_type' => 'govt',
            'ministry' => 'স্থানীয় সরকার, পল্লী উন্নয়ন ও সমবায় মন্ত্রণালয়', 'address' => 'এলজিইডি ভবন, আগারগাঁও, শেরে বাংলা নগর, ঢাকা-১২০৭']);
        $pw = config('larms.default_password');
        $mk = fn (string $username, string $name, string $role, array $x = []) => User::updateOrCreate(['username' => $username], $x + ['name_bn' => $name, 'role' => $role, 'password' => $pw, 'is_active' => true,
            'division_id' => in_array($role, ['rb', 'ministry', 'admin', 'citizen'], true) ? null : $div['60']->id, 'district_id' => in_array($role, ['rb', 'ministry', 'admin', 'commissioner', 'citizen'], true) ? null : $dist->id]);

        $rb = $mk('RB-LGED', 'জাহিদুল ইসলাম (প্রত্যাশী সংস্থা)', 'rb', ['email' => 'zahidul.islam@ddclbd.com', 'mobile' => '01834234231', 'nid' => '2342342112312432', 'designation' => 'প্রতিনিধি', 'requiring_body_id' => $body->id, 'office_name' => $body->name_bn]);
        $mk('LAND2745918', 'জেলা প্রশাসক, সিলেট', 'dc', ['designation' => 'জেলা প্রশাসক']);
        $mk('LAND5099890', 'অতিরিক্ত জেলা প্রশাসক (রাজস্ব), সিলেট', 'adc', ['designation' => 'অতিরিক্ত জেলা প্রশাসক']);
        $mk('LAND9000105', 'ভূমি অধিগ্রহণ কর্মকর্তা, সিলেট', 'lao', ['designation' => 'ভূমি অধিগ্রহণ কর্মকর্তা']);
        foreach (['BLG' => 'LAND6587920', 'JKG' => 'LAND3613124', 'SSD' => 'LAND8346861', 'KNG' => 'LAND5429368'] as $c => $uname) {
            $mk($uname, 'সহকারী কমিশনার (ভূমি), ' . $up[$c]->name_bn, 'ac_land', ['upazila_id' => $up[$c]->id, 'designation' => 'এসি ল্যান্ড']);
        }
        foreach (['KNG' => 'LAND8116313', 'JKG' => 'LAND8116314', 'BLG' => 'LAND8116315', 'SSD' => 'LAND8116316'] as $c => $uname) {
            $mk($uname, 'ইউনিয়ন ভূমি সহকারী কর্মকর্তা, ' . $up[$c]->name_bn, 'ulao', ['upazila_id' => $up[$c]->id, 'designation' => 'ইউনিয়ন ভূমি সহকারী কর্মকর্তা']);
        }
        $mk('LAND5929445', 'কানুনগো, সিলেট', 'kanungo', ['designation' => 'কানুনগো']);
        $mk('LAND5333311', 'সার্ভেয়ার, সিলেট', 'surveyor', ['designation' => 'সার্ভেয়ার']);
        $mk('LAND7000001', 'হিসাব কর্মকর্তা, সিলেট', 'accounts', ['designation' => 'জেলা হিসাবরক্ষণ কর্মকর্তা']);
        $mk('COMM-SYL', 'বিভাগীয় কমিশনার, সিলেট', 'commissioner', ['designation' => 'বিভাগীয় কমিশনার', 'division_id' => $div['60']->id]);
        $mk('MOL-001', 'ভূমি মন্ত্রণালয় (অধিগ্রহণ অধিশাখা-১)', 'ministry', ['designation' => 'সিনিয়র সহকারী সচিব']);
        $mk('01817079954', 'কামাল উদ্দিন (নাগরিক)', 'citizen', ['mobile' => '01817079954', 'nid' => '1990000000001']);
        $mk('01931232938', 'রহিমা বেগম (নাগরিক)', 'citizen', ['mobile' => '01931232938', 'nid' => '1990000000002']);
        User::updateOrCreate(['username' => 'admin'], ['name_bn' => 'সিস্টেম অ্যাডমিন', 'role' => 'admin', 'password' => env('LARMS_ADMIN_PASSWORD', 'ChangeMe@123'), 'is_active' => true]);

        // Demo draft application: project and lands of the workflow script (the applicant only has to upload attachments and submit)
        if (! Application::where('project_name_en', 'like', 'Important Rural Infrastructure%')->exists()) {
            $app = Application::create(['requiring_body_id' => $body->id, 'created_by' => $rb->id, 'body_type' => 'govt', 'status' => 'draft', 'phase' => 'খসড়া', 'district_id' => $dist->id, 'division_id' => $div['60']->id,
                'project_name_bn' => 'সিলেট বিভাগের গুরুত্বপূর্ণ গ্রামীণ অবকাঠামো উন্নয়ন প্রকল্প (তৃতীয় সংশোধিত)', 'project_name_en' => 'Important Rural Infrastructure Improvement Project in Sylhet Division (Third Revision)',
                'pd_name_bn' => 'মোঃ আব্দুর রহিম শেখ', 'pd_name_en' => 'Md. Abdur Rahim Shaikh', 'pd_designation' => 'প্রকল্প পরিচালক (পিডি)', 'pd_office_address' => 'এলজিইডি ভবন, লেভেল-৯, আগারগাঁও, শেরে বাংলা নগর, ঢাকা-১২০৭',
                'pd_mobile' => '01872344562', 'pd_email' => 'projectdirectorlgedsylhet@gmail.com', 'pd_nid' => '2342342112312432',
                'subject' => 'প্রকল্পের লক্ষ্য ১০২৬.১৯ কিলোমিটার গ্রামীণ পরিবহন নেটওয়ার্ক তৈরি, ৪৮৬১.২০ মিটার সেতু/কালভার্ট নির্মাণ, ২৫টি গ্রামীণবাজার উন্নয়ন এবং ৭টি নৌকা অবতরণঘাট নির্মাণ।',
                'has_prior_acquisition' => true, 'prior_project_name' => 'Greater Dhaka Rural Infrastructure Improvement Project-3', 'prior_la_case_no' => '09/2025-2026', 'flags' => array_fill_keys(array_keys(config('attachments.flags')), 'no')]);
            $lands = [
                ['KNG', 'MUTATION', '45', '101', '201', 'aman', 'nal', 0.50, 0.20, [['মোঃ কামাল উদ্দিন', '1990000000001', 1.0]]],
                ['JKG', 'MUTATION', '68', '804', '1651', 'aman', 'nal', 0.14, 0.14, [['মোঃ কামাল উদ্দিন', '1990000000001', 0.5], ['রহিমা বেগম', '1990000000002', 0.5]]],
                ['BLG', 'MUTATION', '22', '310', '640', 'aman', 'aman', 0.80, 0.30, [['আব্দুল মান্নান', '1990000000003', 1.0]]],
                ['SSD', 'MUTATION', '84', '757', '1144', 'bhiti', 'bhiti', 0.25, 0.25, [['রহিমা বেগম', '1990000000002', 1.0]]],
                ['SSD', 'MUTATION', '84', '758', '873', 'aman', 'nal', 0.60, 0.40, [['সুলতান আহমেদ', '1990000000004', 1.0]]],
            ];
            foreach ($lands as [$u, $sv, $jl, $kh, $dag, $rc, $uc, $tot, $prop, $owners]) {
                $land = ApplicationLand::create(['application_id' => $app->id, 'upazila_id' => $up[$u]->id, 'mouza_id' => $mouza[$u]->id, 'survey_name' => $sv, 'jl_no' => $jl, 'khatian_no' => $kh, 'dag_no' => $dag,
                    'record_class_id' => $cls[$rc]->id, 'usage_class_id' => $cls[$uc]->id, 'total_area_acre' => $tot, 'proposed_area_acre' => $prop]);
                foreach ($owners as [$name, $nid, $share]) LandOwner::create(['land_id' => $land->id, 'name' => $name, 'nid' => $nid, 'mobile' => '01700000000', 'address' => 'গ্রাম: ' . $mouza[$u]->name_bn . ', ' . $up[$u]->name_bn . ', সিলেট', 'share' => $share]);
            }
            $app->update(['total_area_acre' => $app->lands()->sum('proposed_area_acre')]);
        }
    }
}
