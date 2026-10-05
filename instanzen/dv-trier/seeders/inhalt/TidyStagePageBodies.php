<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Die neuen Stufenseiten zeigen Alter und Leitsatz im farbigen Kopfband,
 * die Übersicht zeigt Altersleiste und Stufenkacheln. Die bisher im Text
 * doppelt stehenden Teile werden entfernt – nur, wenn sie noch genau so
 * im Text stehen (redaktionelle Änderungen bleiben unberührt).
 *
 * Früher Migration 2026_10_02_090000_tidy_stage_page_bodies.
 */
class TidyStagePageBodies extends Seeder
{
    public const AGE_LINE = '#^\s*<p><strong>\d+ bis \d+ Jahre</strong> – [^<]*</p>\s*#u';

    public const STAGE_LIST = '#\s*<ul>\s*(?:<li><strong><a href="/stufe-[a-z]+">[^<]+</a></strong> \(\d+ bis \d+ Jahre\) – [^<]*</li>\s*){5}</ul>\s*$#u';

    public function run(): void
    {
        foreach (DB::table('pages')->whereNotNull('stage')->get(['id', 'body']) as $page) {
            $body = preg_replace(self::AGE_LINE, '', (string) $page->body, 1);
            if ($body !== $page->body) {
                DB::table('pages')->where('id', $page->id)->update(['body' => $body]);
            }
        }

        $page = DB::table('pages')->where('slug', config('dpsg.stages_page', 'stufen'))->first(['id', 'body']);
        if ($page) {
            $body = preg_replace(self::STAGE_LIST, '', (string) $page->body, 1);
            if ($body !== $page->body) {
                DB::table('pages')->where('id', $page->id)->update(['body' => $body]);
            }
        }
    }
}
