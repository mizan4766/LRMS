<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Chat\ChatBot;
use App\Services\Chat\KnowledgeBase;
use Illuminate\Http\Request;

/** Public assistant of the login page. Reveals nothing but the stage of an application whose tracking number the asker already knows. */
class ChatController extends Controller
{
    private const STATUS = ['submitted' => 'দাখিলকৃত', 'processing' => 'প্রক্রিয়াধীন', 'needs_refinement' => 'সংশোধন প্রয়োজন', 'under_verification' => 'পুনঃযাচাইয়ে', 'completed' => 'সম্পন্ন', 'rejected' => 'নামঞ্জুর'];

    private function bot(): ChatBot
    {
        $steps = config('steps');
        return new ChatBot(KnowledgeBase::entries(config('attachments'), $steps), $steps, config('larms.roles'), function (string $q) {
            $a = Application::where('status', '!=', 'draft')->where(fn ($x) => $x->where('tracking_no', $q)->orWhere('la_case_no', $q))->first();
            return $a ? ['tracking_no' => $a->tracking_no, 'la_case_no' => $a->la_case_no, 'status' => self::STATUS[$a->status] ?? $a->status,
                'phase' => $a->phase ?: '—', 'submitted_at' => $a->submitted_at?->format('d/m/Y') ?? '—'] : null;
        });
    }

    public function ask(Request $r)
    {
        $d = $r->validate(['message' => 'nullable|string|max:500', 'topic' => 'nullable|string|max:40']);
        return $this->bot()->reply($d['message'] ?? '', $d['topic'] ?? null);
    }

    public function start()
    {
        return $this->bot()->reply('');
    }
}
