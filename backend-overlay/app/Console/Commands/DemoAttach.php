<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Demo helper: uploads a placeholder file for every required attachment so the demo application can be submitted at once. */
class DemoAttach extends Command
{
    protected $signature = 'larms:demo-attach {application=0 : application id (default: the latest draft)}';
    protected $description = 'Attach placeholder files for all required attachments of a draft application (demo/testing only)';

    public function handle(): int
    {
        $app = (int) $this->argument('application') ? Application::findOrFail((int) $this->argument('application')) : Application::where('status', 'draft')->latest('id')->firstOrFail();
        $user = User::where('role', 'rb')->first();
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 200 100]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $n = 0;
        foreach (AttachmentRules::active(AttachmentRules::listFor(config('attachments'), $app->body_type), $app->flags ?? []) as $it) {
            if (! $it['required'] || Attachment::where('application_id', $app->id)->where('kind', 'proposal')->where('slot_no', $it['no'])->exists()) continue;
            $path = "applications/{$app->id}/demo-slot-{$it['no']}.pdf";
            Storage::disk('local')->put($path, $pdf);
            Attachment::create(['application_id' => $app->id, 'kind' => 'proposal', 'slot_no' => $it['no'], 'title' => $it['title'], 'path' => $path, 'original_name' => "demo-{$it['no']}.pdf", 'mime' => 'application/pdf', 'size' => strlen($pdf), 'uploaded_by' => $user->id]);
            $n++;
        }
        $this->info("Application #{$app->id}: $n placeholder attachment(s) added. Now log in as the applicant and submit it.");
        return self::SUCCESS;
    }
}
