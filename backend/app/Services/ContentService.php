<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentService
{
    const TYPES = ['single_choice', 'multiple_choice', 'true_false_not_given', 'yes_no_not_given', 'short_answer', 'sentence_completion', 'form_completion', 'note_completion', 'table_completion', 'summary_completion', 'matching_headings', 'matching_information', 'matching_features', 'diagram_label', 'map_label', 'plan_label', 'writing', 'speaking'];

    const CHOICES = ['single_choice', 'multiple_choice', 'matching_headings', 'matching_information', 'matching_features'];

    public function validate(array $content, string $skill): array
    {
        $v = Validator::make($content, [
            'sections' => 'required|array|min:1|max:12', 'sections.*.id' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|distinct', 'sections.*.title' => 'required|string|max:200',
            'sections.*.passage' => 'nullable|string|max:60000', 'sections.*.transcript' => 'nullable|string|max:40000', 'sections.*.audio_asset_id' => 'nullable|integer|exists:media_assets,id',
            'sections.*.instructions' => 'required|string|max:3000', 'sections.*.questions' => 'required|array|min:1|max:60',
            'sections.*.questions.*.id' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|max:80', 'sections.*.questions.*.type' => ['required', Rule::in(self::TYPES)],
            'sections.*.questions.*.prompt' => 'required|string|max:10000', 'sections.*.questions.*.options' => 'nullable|array|max:30', 'sections.*.questions.*.options.*' => 'string|max:500',
            'sections.*.questions.*.accepted' => 'nullable|array|max:20', 'sections.*.questions.*.accepted.*' => 'string|max:300',
            'sections.*.questions.*.explanation' => 'nullable|string|max:6000', 'sections.*.questions.*.reference' => 'nullable|string|max:1000',
            'sections.*.questions.*.max_words' => 'nullable|integer|min:0|max:20', 'sections.*.questions.*.max_numbers' => 'nullable|integer|min:0|max:5',
            'sections.*.questions.*.select_count' => 'nullable|integer|min:2|max:10', 'sections.*.questions.*.min_words' => 'nullable|integer|min:1|max:1000',
            'sections.*.questions.*.preparation_seconds' => 'nullable|integer|min:0|max:300', 'sections.*.questions.*.response_seconds' => 'nullable|integer|min:10|max:600',
            'sections.*.questions.*.diagram' => 'nullable|string|max:6000', 'sections.*.questions.*.task' => 'nullable|integer|min:1|max:2',
            'answer_keys_reviewed' => 'required|accepted', 'scoring' => 'nullable|array', 'scoring.reviewed' => 'nullable|boolean', 'scoring.thresholds' => 'nullable|array', 'scoring.source' => 'nullable|string|max:1000',
        ]);
        $v->after(function ($v) use ($content, $skill) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $ids = [];
            foreach ($content['sections'] ?? [] as $i => $s) {
                if ($skill === 'listening' && empty($s['audio_asset_id'])) {
                    $v->errors()->add("sections.$i.audio_asset_id", 'Listening sections need an audio asset and transcript.');
                }
                if ($skill === 'listening' && empty($s['transcript'])) {
                    $v->errors()->add("sections.$i.transcript", 'Provide the matching transcript.');
                }
                foreach ($s['questions'] ?? [] as $j => $q) {
                    $k = "sections.$i.questions.$j";
                    $type = $q['type'] ?? '';
                    $id = $q['id'] ?? '';
                    if (in_array($id, $ids)) {
                        $v->errors()->add("$k.id", 'Question IDs must be unique throughout the test.');
                    } $ids[] = $id;
                    if (in_array($skill, ['writing', 'speaking']) ? $type !== $skill : in_array($type, ['writing', 'speaking'])) {
                        $v->errors()->add("$k.type", 'Question type must match the test skill.');
                    }
                    if (! in_array($type, ['writing', 'speaking']) && (empty($q['accepted']) || empty($q['explanation']))) {
                        $v->errors()->add("$k.accepted", 'Reviewed accepted answers and an explanation are required.');
                    }
                    if (in_array($type, self::CHOICES) && count($q['options'] ?? []) < 2) {
                        $v->errors()->add("$k.options", 'Provide at least two options.');
                    }
                    $options = match ($type) {
                        'true_false_not_given' => ['True', 'False', 'Not Given'],'yes_no_not_given' => ['Yes', 'No', 'Not Given'],default => $q['options'] ?? []
                    };
                    if ($options && array_diff($q['accepted'] ?? [], $options)) {
                        $v->errors()->add("$k.accepted", 'Accepted choices must occur in the options.');
                    }
                    if ($type === 'multiple_choice' && count($q['accepted'] ?? []) !== ($q['select_count'] ?? 0)) {
                        $v->errors()->add("$k.select_count", 'The number of accepted choices must equal select count.');
                    }
                    if (str_ends_with($type, '_label') && empty($q['diagram'])) {
                        $v->errors()->add("$k.diagram", 'Provide a labelled diagram or accessible spatial description.');
                    }
                    if (! in_array($type, array_merge(self::CHOICES, ['true_false_not_given', 'yes_no_not_given', 'writing', 'speaking'])) && ! isset($q['max_words'])) {
                        $v->errors()->add("$k.max_words", 'Specify word and number limits.');
                    }
                }
            }
            if (($content['scoring']['reviewed'] ?? false) && (count($ids) !== 40 || empty($content['scoring']['source']) || empty($content['scoring']['thresholds']))) {
                $v->errors()->add('scoring', 'Reviewed band conversion requires 40 questions, thresholds and a review/source reference.');
            }
            $previous = -1;
            foreach ($content['scoring']['thresholds'] ?? [] as $row) {
                if (! is_array($row) || ! isset($row['min'],$row['band']) || ! is_numeric($row['min']) || ! is_numeric($row['band']) || $row['min'] < 0 || $row['min'] > 40 || $row['min'] <= $previous || $row['band'] < 0 || $row['band'] > 9 || fmod((float) $row['band'], 0.5) !== 0.0) {
                    $v->errors()->add('scoring.thresholds', 'Thresholds require ascending unique marks (0–40) and half-band scores (0–9).');
                    break;
                } $previous = $row['min'];
            }
        });
        $v->validate();

        return $content;
    }

    public function publish(Exam $exam, User $actor): ExamVersion
    {
        abort_unless($actor->role === 'admin' && ! $actor->suspended, 403);

        return DB::transaction(function () use ($exam, $actor) {
            $exam = Exam::lockForUpdate()->findOrFail($exam->id);
            $content = $this->validate($exam->draft, $exam->skill);
            $number = ($exam->versions()->max('number') ?? 0) + 1;
            $content['meta'] = ['title' => $exam->title, 'skill' => $exam->skill, 'test_type' => $exam->test_type, 'duration_seconds' => $exam->duration_seconds, 'demo' => $exam->demo];
            $version = $exam->versions()->create(['number' => $number, 'content' => $content, 'scoring' => $content['scoring'] ?? ['reviewed' => false], 'published_by' => $actor->id, 'created_at' => now()]);
            $exam->update(['published_version' => $number, 'status' => 'published']);
            Audit::record('content.published', $exam, ['version' => $number]);

            return $version;
        });
    }

    public function publicContent(array $content, array $sectionIds = []): array
    {
        return ['sections' => collect($content['sections'])->filter(fn ($s) => ! $sectionIds || in_array($s['id'], $sectionIds))->map(fn ($s) => [
            'id' => $s['id'], 'title' => $s['title'], 'instructions' => $s['instructions'], 'passage' => $s['passage'] ?? null, 'audio_asset_id' => $s['audio_asset_id'] ?? null,
            'questions' => array_map(fn ($q) => array_intersect_key($q, array_flip(['id', 'type', 'prompt', 'options', 'max_words', 'max_numbers', 'select_count', 'min_words', 'preparation_seconds', 'response_seconds', 'diagram', 'task'])), $s['questions']),
        ])->values()->all()];
    }

    public function import(array $rows, User $actor, bool $commit = false): array
    {
        abort_unless($actor->role === 'admin', 403);
        $errors = [];
        foreach ($rows as $i => $row) {
            try {
                Validator::make($row, ['slug' => 'required|alpha_dash|max:150', 'title' => 'required|string|max:200', 'description' => 'required|string|max:3000', 'skill' => ['required', Rule::in(['reading', 'listening', 'writing', 'speaking'])], 'test_type' => ['required', Rule::in(['academic', 'general'])], 'duration_seconds' => 'required|integer|min:60|max:14400', 'draft' => 'required|array'])->validate();
                $this->validate($row['draft'], $row['skill']);
                if (Exam::where('slug', $row['slug'])->exists()) {
                    throw ValidationException::withMessages(['slug' => 'Duplicate slug; import as a new slug.']);
                } if (count(array_filter($rows, fn ($r) => ($r['slug'] ?? '') === $row['slug'])) > 1) {
                    throw ValidationException::withMessages(['slug' => 'Duplicate slug within this import.']);
                }
            } catch (ValidationException $e) {
                $errors[] = ['row' => $i + 1, 'errors' => $e->errors()];
            }
        }
        if ($errors || ! $commit) {
            return ['valid' => ! $errors, 'errors' => $errors, 'count' => count($rows), 'committed' => false];
        }

        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $exam = Exam::create(array_intersect_key($row, array_flip(['slug', 'title', 'description', 'skill', 'test_type', 'duration_seconds', 'draft'])) + ['status' => 'draft']);
                Audit::record('content.imported', $exam);
            }

return ['valid' => true, 'errors' => [], 'count' => count($rows), 'committed' => true];
        });
    }
}
