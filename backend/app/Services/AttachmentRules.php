<?php

namespace App\Services;

/** Which attachments are required for an application (Attachment19: government vs private list + conditional flags). */
class AttachmentRules
{
    public static function listFor(array $cfg, string $bodyType): array
    {
        return $bodyType === 'private' ? $cfg['private'] : $cfg['govt'];
    }

    /** @return array[] items with 'required' (bool) and 'visible' (bool) resolved from the yes/no flags */
    public static function active(array $items, array $flags): array
    {
        return array_map(function ($it) use ($flags) {
            $yes = fn ($k) => ($flags[$k] ?? 'no') === 'yes';
            $visible = $it['mode'] !== 'conditional' || $yes($it['cond']);
            return $it + ['visible' => $visible, 'required' => $visible, 'review' => $it['mode'] === 'review'];
        }, $items);
    }

    /** @param int[] $uploaded slot numbers that have a file */
    public static function missing(array $cfg, string $bodyType, array $flags, array $uploaded): array
    {
        $out = [];
        foreach (self::active(self::listFor($cfg, $bodyType), $flags) as $it) {
            if ($it['required'] && ! in_array($it['no'], $uploaded, true)) $out[] = $it;
        }
        return $out;
    }
}
