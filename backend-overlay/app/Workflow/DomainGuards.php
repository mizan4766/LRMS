<?php

namespace App\Workflow;

use App\Models\Application;
use App\Models\Attachment;
use App\Services\AttachmentRules;

/** Guards that need the database (named in config/steps.php). Return an error message or null. */
class DomainGuards
{
    public static function check(string $guard, array $task, array $data): ?string
    {
        $app = Application::findOrFail($task['app_id']);
        switch ($guard) {
            case 'attachments_ok':
                $uploaded = Attachment::where('application_id', $app->id)->where('kind', 'proposal')->pluck('slot_no')->map(fn ($x) => (int) $x)->all();
                $missing = AttachmentRules::missing(config('attachments'), $app->body_type, $app->flags ?? [], $uploaded);
                if ($missing) return count($missing) . 'টি আবশ্যক সংযুক্তি নেই: ' . implode(', ', array_map(fn ($m) => $m['no'], $missing));
                if (Attachment::where('application_id', $app->id)->where('kind', 'proposal')->where('status', 'fix')->exists()) return 'কিছু সংযুক্তি "সংশোধন প্রয়োজন" চিহ্নিত; সংশোধনের জন্য প্রেরণ করুন';
                return null;
            case 'media_ok':
                $landIds = $app->lands()->pluck('id')->all();
                $have = collect($data['media'] ?? [])->pluck('land_id')->map(fn ($x) => (int) $x)->all();
                foreach ($landIds as $id) if (! in_array((int) $id, $have, true)) return 'প্রতিটি দাগের জন্য ভিডিও/স্থিরচিত্রের বর্ণনা আবশ্যক';
                return null;
            case 'estimate_ok':
                $lines = $data['estimate']['lines'] ?? [];
                if (! $lines) return 'কমপক্ষে একটি রোয়েদাদ লাইন আবশ্যক';
                foreach ($lines as $l) if ((float) ($l['area_acre'] ?? 0) <= 0 || (float) ($l['rate_per_acre'] ?? 0) <= 0) return 'প্রতিটি লাইনে আয়তন ও একরপ্রতি দর আবশ্যক';
                return null;
            case 'deposit_amount_ok':
                return (float) ($data['paid_amount'] ?? 0) + 0.01 >= (float) $app->deposit_required
                    ? null : 'জমাকৃত অর্থ প্রাক্কলিত অঙ্কের (' . number_format((float) $app->deposit_required, 2) . ') চেয়ে কম';
        }
        return "অজানা guard: $guard";
    }
}
