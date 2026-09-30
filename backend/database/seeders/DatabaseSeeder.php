<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Exam;
use App\Models\MediaAsset;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [];
        foreach (['academic' => 'Academic discovery mock', 'general' => 'General Training essentials'] as $slug => $title) {
            $collections[$slug] = Collection::firstOrCreate(['slug' => $slug], ['title' => $title, 'description' => 'Original demonstration collection; not professionally validated exam material.', 'is_mock' => true]);
        }
        $system = new User;
        $system->id = null;
        $system->role = 'admin';
        $system->suspended = false;
        $tests = json_decode(file_get_contents(database_path('content/tests.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($tests as $test) {
            if (Exam::where('slug', $test['slug'])->exists()) {
                continue;
            }
            $collection = $test['collection'] ?? null;
            unset($test['collection']);
            foreach ($test['draft']['sections'] as &$section) {
                if (isset($section['audio_file'])) {
                    $file = $section['audio_file'];
                    $asset = MediaAsset::firstOrCreate(['name' => $file], ['path' => 'demo/'.$file, 'disk' => 'local', 'kind' => 'audio', 'description' => 'Synthesized demonstration audio; original transcript.', 'transcript' => $section['transcript'], 'license' => 'Development demo — macOS Samantha synthesized voice; review platform licence before production distribution.']);
                    Storage::disk('local')->put('demo/'.$file, file_get_contents(database_path('content/'.$file)));
                    $section['audio_asset_id'] = $asset->id;
                    unset($section['audio_file']);
                }
            }unset($section);
            $test['collection_id'] = $collection ? $collections[$collection]->id : null;
            $exam = Exam::create($test + ['tags' => ['Original demo'], 'status' => 'draft']);
            app(ContentService::class)->publish($exam, $system);
        }
        foreach ($collections as $collection) {
            $collection->update(['sequence' => Exam::where('collection_id', $collection->id)->orderByRaw("CASE skill WHEN 'listening' THEN 1 WHEN 'reading' THEN 2 WHEN 'writing' THEN 3 ELSE 4 END")->pluck('id')->all()]);
        }
        Plan::firstOrCreate(['name' => 'Free practice'], ['description' => 'Free exercises, saved attempts, answer explanations and progress tracking.']);
        Plan::firstOrCreate(['name' => 'Extended practice'], ['description' => 'Additional material with access granted by an administrator. Online checkout is not available.']);
        Setting::firstOrCreate(['key' => 'brand'], ['value' => 'IELTS Practice Hub']);
        Setting::firstOrCreate(['key' => 'support_email'], ['value' => '']);
    }
}
