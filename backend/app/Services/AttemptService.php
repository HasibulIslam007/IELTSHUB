<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptService
{
    public function __construct(private AccessService $access, private ScoringService $scoring, private ContentService $content) {}

    public function start(User $user, Exam $exam, array $data): Attempt
    {
        return DB::transaction(function () use ($user, $exam, $data) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $existing = Attempt::where('user_id', $user->id)->where('start_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->exam_id === $exam->id, 409, 'This request key belongs to another test.');

                return $existing;
            }
            abort_unless($exam->status === 'published', 404);
            abort_unless($this->access->canStart($user, $exam), 403, 'This test requires premium access. Checkout is not configured; contact the administrator.');
            $version = $exam->versions()->where('number', $exam->published_version)->firstOrFail();
            $allIds = array_column($version->content['sections'], 'id');
            $ids = $data['mode'] === 'mock' ? $allIds : ($data['section_ids'] ?? $allIds);
            if (! $ids || array_diff($ids, $allIds)) {
                throw ValidationException::withMessages(['section_ids' => 'Select existing parts.']);
            }
            $duration = $version->content['meta']['duration_seconds'];
            $timed = $data['mode'] === 'mock' || ($data['timed'] ?? false);

            return Attempt::create(['user_id' => $user->id, 'exam_id' => $exam->id, 'exam_version_id' => $version->id, 'start_key' => $data['request_key'], 'mode' => $data['mode'], 'section_ids' => array_values(array_unique($ids)), 'answers' => [], 'flags' => [], 'notes' => [], 'highlights' => [], 'playback' => [], 'started_at' => now(), 'deadline_at' => $timed ? now()->addSeconds($duration) : null]);
        });
    }

    public function expired(Attempt $a): bool
    {
        return $a->status === 'active' && $a->deadline_at && now()->gte($a->deadline_at);
    }

    public function finalize(Attempt $a): Attempt
    {
        if (in_array($a->status, ['submitted', 'awaiting_review', 'reviewed'])) {
            return $a;
        }
        $skill = $a->version->content['meta']['skill'];
        $subjective = in_array($skill, ['writing', 'speaking']);
        $a->update(['status' => $subjective ? 'awaiting_review' : 'submitted', 'submitted_at' => now(), 'paused_at' => null, 'result' => $subjective ? ['status' => 'awaiting_review', 'estimated_band' => null] : $this->scoring->score($a->version->content, $a->answers, $a->section_ids, $a->version->scoring), 'revision' => $a->revision + 1]);
        if ($subjective) {
            Assessment::firstOrCreate(['attempt_id' => $a->id]);
        }

return $a;
    }

    public function refresh(Attempt $a): Attempt
    {
        return DB::transaction(function () use ($a) {
            $a = Attempt::lockForUpdate()->findOrFail($a->id);
            if ($this->expired($a)) {
                $this->finalize($a);
            }

return $a;
        });
    }

    public function mutate(Attempt $attempt, array $data, string $action = 'save'): Attempt
    {
        $result = DB::transaction(function () use ($attempt, $data, $action) {
            $a = Attempt::lockForUpdate()->findOrFail($attempt->id);
            if (DB::table('attempt_mutations')->where('attempt_id', $a->id)->where('request_key', $data['request_key'])->exists()) {
                return $a;
            }
            if ($action === 'submit' && in_array($a->status, ['submitted', 'awaiting_review', 'reviewed'])) {
                return $a;
            }
            if ($this->expired($a)) {
                $this->finalize($a);

                return ['error' => 'The deadline has passed. Saved answers were submitted; later unsynced answers were not accepted.'];
            }
            abort_unless(in_array($a->status, ['active', 'paused']), 409, 'This attempt is already submitted. Unsynced answers cannot be accepted.');
            abort_unless($a->revision === $data['revision'], 409, 'Another tab saved newer work. Reload the saved version before continuing. Your local draft is retained.');
            if ($action === 'pause') {
                abort_unless($a->mode === 'practice' && $a->status === 'active', 422, 'Only active practice can be paused.');
                $a->status = 'paused';
                $a->paused_at = now();
                $a->remaining_seconds = $a->deadline_at ? max(0, (int) ceil(now()->diffInSeconds($a->deadline_at))) : null;
                $a->deadline_at = null;
            } elseif ($action === 'resume') {
                abort_unless($a->status === 'paused', 422);
                $a->status = 'active';
                $a->paused_at = null;
                $a->deadline_at = $a->remaining_seconds !== null ? now()->addSeconds($a->remaining_seconds) : null;
            } else {
                abort_unless($a->status === 'active', 409, 'Resume before changing answers.');
                $questions = collect($a->version->content['sections'])->whereIn('id', $a->section_ids)->flatMap(fn ($s) => $s['questions'])->keyBy('id');
                foreach ($data['answers'] ?? [] as $id => $value) {
                    if (! $questions->has($id)) {
                        throw ValidationException::withMessages(['answers' => 'Unknown question ID.']);
                    }
                    $q = $questions[$id];
                    if ($q['type'] === 'speaking') {
                        throw ValidationException::withMessages(['answers' => 'Speaking responses must be uploaded.']);
                    }
                    if ($q['type'] === 'multiple_choice') {
                        if (! is_array($value) || count($value) > ($q['select_count'] ?? 2) || array_diff($value, $q['options'])) {
                            throw ValidationException::withMessages(['answers' => 'Invalid multiple-choice response.']);
                        }
                    } elseif (! is_string($value) || mb_strlen($value) > ($q['type'] === 'writing' ? 30000 : 500)) {
                        throw ValidationException::withMessages(['answers' => 'Invalid response length or type.']);
                    }
                }
                if (isset($data['answers'])) {
                    $a->answers = array_replace($a->answers, $data['answers']);
                }
                foreach (['flags', 'notes', 'highlights'] as $field) {
                    if (isset($data[$field])) {
                        $a->$field = $data[$field];
                    }
                }
            }
            $a->revision++;
            $a->save();
            if ($action === 'submit') {
                $this->finalize($a);
            }
            DB::table('attempt_mutations')->insert(['attempt_id' => $a->id, 'request_key' => $data['request_key'], 'revision' => $a->revision, 'created_at' => now(), 'updated_at' => now()]);

            return $a;
        });
        if (is_array($result)) {
            abort(409, $result['error']);
        }

return $result;
    }

    public function serialize(Attempt $a): array
    {
        $a->loadMissing(['version', 'exam', 'recordings', 'assessment.reviewer']);
        $done = in_array($a->status, ['submitted', 'awaiting_review', 'reviewed']);
        $result = $a->toArray();
        unset($result['version'],$result['start_key'],$result['exam'],$result['assessment']);
        $result['test'] = $a->version->content['meta'] + ['id' => $a->exam_id];
        $result['content'] = $this->content->publicContent($a->version->content, $a->section_ids);
        $result['server_now'] = now()->toISOString();
        if ($done) {
            $result['transcripts'] = collect($a->version->content['sections'])->whereIn('id', $a->section_ids)->map(fn ($s) => ['id' => $s['id'], 'transcript' => $s['transcript'] ?? null])->values();
        }
        if ($a->assessment?->status === 'published') {
            $result['assessment'] = ['criteria' => $a->assessment->criteria, 'feedback' => $a->assessment->feedback, 'band' => $a->assessment->band, 'rubric_version' => $a->assessment->rubric_version, 'reviewer' => $a->assessment->reviewer?->name, 'published_at' => $a->assessment->published_at];
        }

        return $result;
    }
}
