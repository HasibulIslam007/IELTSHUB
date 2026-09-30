<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Entitlement;
use App\Models\Exam;
use App\Models\Recording;
use App\Models\User;
use App\Notifications\FeedbackPublished;
use App\Services\AttemptService;
use App\Services\ContentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function exam(string $skill = 'reading', bool $premium = false): Exam
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $questions = $skill === 'writing' ? [
            ['id' => 'task1', 'type' => 'writing', 'task' => 1, 'prompt' => 'Describe the table.', 'min_words' => 150], ['id' => 'task2', 'type' => 'writing', 'task' => 2, 'prompt' => 'Discuss public transport.', 'min_words' => 250],
        ] : ($skill === 'speaking' ? [['id' => 'speak1', 'type' => 'speaking', 'prompt' => 'Describe a useful skill.', 'response_seconds' => 120]] : [['id' => 'q1', 'type' => 'short_answer', 'prompt' => 'What colour is the door?', 'accepted' => ['green'], 'max_words' => 1, 'max_numbers' => 0, 'explanation' => 'The passage describes a green door.']]);
        $exam = Exam::create(['slug' => 'test-'.Str::uuid(), 'title' => 'A test of observation', 'description' => 'Original demonstration.', 'skill' => $skill, 'test_type' => 'academic', 'premium' => $premium, 'duration_seconds' => 120, 'draft' => ['answer_keys_reviewed' => true, 'sections' => [['id' => 'part1', 'title' => 'Part one', 'instructions' => 'Read and answer.', 'passage' => 'The door is green.', 'questions' => $questions]], 'scoring' => ['reviewed' => false]]]);
        app(ContentService::class)->publish($exam, $admin);

        return $exam->fresh();
    }

    private function start(User $user, Exam $exam, string $mode = 'practice'): Attempt
    {
        return app(AttemptService::class)->start($user, $exam, ['mode' => $mode, 'request_key' => (string) Str::uuid()]);
    }

    private function payload(Attempt $attempt, array $extra = []): array
    {
        return array_replace(['revision' => $attempt->revision, 'request_key' => (string) Str::uuid()], $extra);
    }

    public function test_guests_can_browse_without_receiving_answer_keys(): void
    {
        $exam = $this->exam();
        $exam->update(['sample' => true]);
        $this->getJson('/api/v1/tests')->assertOk()->assertJsonPath('data.0.title', 'A test of observation')->assertDontSee('accepted');
        $this->getJson('/api/v1/sample')->assertOk()->assertDontSee('accepted')->assertDontSee('explanation');
        $this->postJson('/api/v1/tests/'.$exam->id.'/attempts', ['mode' => 'practice', 'request_key' => (string) Str::uuid()])->assertUnauthorized();
    }

    public function test_registration_ignores_privileged_fields_and_hashes_password(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'New learner', 'email' => 'new@example.test', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery', 'role' => 'admin', 'suspended' => false])->assertCreated()->assertJsonPath('user.role', 'student');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNotSame('correct-horse-battery', $user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_account_cannot_log_in_or_access_saved_work(): void
    {
        $user = User::factory()->create(['suspended' => true]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_start_save_and_submit_are_idempotent_and_scored_on_server(): void
    {
        $exam = $this->exam();
        $student = User::factory()->create();
        $this->actingAs($student);
        $start = ['mode' => 'practice', 'request_key' => (string) Str::uuid()];
        $first = $this->postJson('/api/v1/tests/'.$exam->id.'/attempts', $start)->assertCreated()->assertDontSee('accepted');
        $id = $first->json('id');
        $this->postJson('/api/v1/tests/'.$exam->id.'/attempts', $start)->assertJsonPath('id', $id);
        $this->assertDatabaseCount('attempts', 1);
        $a = Attempt::findOrFail($id);
        $payload = $this->payload($a, ['answers' => ['q1' => ' GREEN '], 'score' => 100, 'deadline_at' => now()->addYear()]);
        $saved = $this->putJson('/api/v1/attempts/'.$id, $payload)->assertOk()->assertJsonPath('answers.q1', 'GREEN');
        $this->putJson('/api/v1/attempts/'.$id, $payload)->assertJsonPath('revision', $saved->json('revision'));
        $submit = $this->payload($a->fresh());
        $this->postJson('/api/v1/attempts/'.$id.'/submit', $submit)->assertOk()->assertJsonPath('result.raw_score', 1)->assertJsonPath('result.estimated_band', null);
        $this->postJson('/api/v1/attempts/'.$id.'/submit', $submit)->assertOk()->assertJsonPath('result.raw_score', 1);
        $this->assertDatabaseHas('attempts', ['id' => $id, 'status' => 'submitted']);
    }

    public function test_competing_tabs_receive_409_without_overwriting_answers(): void
    {
        $a = $this->start($user = User::factory()->create(), $this->exam());
        $this->actingAs($user);
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a, ['answers' => ['q1' => 'green']]))->assertOk();
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a, ['answers' => ['q1' => 'red']]))->assertConflict();
        $this->assertSame('green', $a->fresh()->answers['q1']);
    }

    public function test_student_can_clear_a_saved_answer_and_note(): void
    {
        $a = $this->start($user = User::factory()->create(), $this->exam());
        $this->actingAs($user);
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a, ['answers' => ['q1' => 'green'], 'notes' => ['part1' => 'A useful detail']]))->assertOk();
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a->fresh(), ['answers' => ['q1' => ''], 'notes' => ['part1' => '']]))->assertOk()->assertJsonPath('answers.q1', '')->assertJsonPath('notes.part1', '');
        $this->assertSame('', $a->fresh()->answers['q1']);
    }

    public function test_refresh_preserves_deadline_and_late_answers_are_rejected(): void
    {
        $this->freezeTime();
        $a = $this->start($user = User::factory()->create(), $this->exam(), 'mock');
        $deadline = $a->deadline_at->toISOString();
        $this->actingAs($user);
        $this->travel(1)->minutes();
        $this->getJson('/api/v1/attempts/'.$a->id)->assertJsonPath('deadline_at', $deadline);
        $this->travel(2)->minutes();
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a, ['answers' => ['q1' => 'green']]))->assertConflict();
        $this->assertDatabaseHas('attempts', ['id' => $a->id, 'status' => 'submitted']);
        $this->assertSame(0, $a->fresh()->result['raw_score']);
    }

    public function test_practice_pause_stops_timer_and_mock_pause_is_rejected(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $exam = $this->exam();
        $a = app(AttemptService::class)->start($user, $exam, ['mode' => 'practice', 'timed' => true, 'request_key' => (string) Str::uuid()]);
        $this->actingAs($user);
        $this->travel(30)->seconds();
        $this->postJson('/api/v1/attempts/'.$a->id.'/pause', $this->payload($a))->assertOk()->assertJsonPath('status', 'paused');
        $this->travel(20)->minutes();
        $this->postJson('/api/v1/attempts/'.$a->id.'/resume', $this->payload($a->fresh()))->assertOk();
        $this->assertSame(90, (int) now()->diffInSeconds($a->fresh()->deadline_at));
        $mock = $this->start($user, $exam, 'mock');
        $this->postJson('/api/v1/attempts/'.$mock->id.'/pause', $this->payload($mock))->assertUnprocessable();
    }

    public function test_student_cannot_read_or_change_another_students_attempt(): void
    {
        $a = $this->start(User::factory()->create(), $this->exam());
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/attempts/'.$a->id)->assertNotFound();
        $this->putJson('/api/v1/attempts/'.$a->id, $this->payload($a, ['answers' => ['q1' => 'green']]))->assertNotFound();
        $this->postJson('/api/v1/attempts/'.$a->id.'/submit', $this->payload($a))->assertNotFound();
    }

    public function test_premium_access_requires_an_active_entitlement(): void
    {
        $this->freezeTime();
        $e = $this->exam(premium: true);
        $u = User::factory()->create();
        $this->actingAs($u);
        $url = '/api/v1/tests/'.$e->id.'/attempts';
        $payload = ['mode' => 'practice', 'request_key' => (string) Str::uuid()];
        $this->postJson($url, $payload)->assertForbidden();
        $grant = Entitlement::create(['user_id' => $u->id, 'exam_id' => $e->id, 'expires_at' => now()->addDay()]);
        $this->postJson($url, $payload)->assertCreated();
        $grant->update(['revoked_at' => now()]);
        $payload['request_key'] = (string) Str::uuid();
        $this->postJson($url, $payload)->assertForbidden();
    }

    public function test_new_publication_cannot_change_historical_results(): void
    {
        $e = $this->exam();
        $a = $this->start($u = User::factory()->create(), $e);
        $old = $a->exam_version_id;
        $draft = $e->draft;
        $draft['sections'][0]['questions'][0]['accepted'] = ['red'];
        $e->update(['draft' => $draft]);
        app(ContentService::class)->publish($e, User::factory()->create(['role' => 'admin']));
        $this->actingAs($u)->postJson('/api/v1/attempts/'.$a->id.'/submit', $this->payload($a, ['answers' => ['q1' => 'green']]))->assertJsonPath('result.raw_score', 1);
        $this->assertSame($old, $a->fresh()->exam_version_id);
        $this->assertDatabaseCount('exam_versions', 2);
    }

    public function test_publishing_invalid_questions_fails_without_creating_version(): void
    {
        $e = $this->exam();
        $draft = $e->draft;
        $draft['sections'][0]['questions'][0]['accepted'] = [];
        $e->update(['draft' => $draft]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/v1/admin/tests/'.$e->id.'/publish')->assertUnprocessable()->assertJsonValidationErrors('sections.0.questions.0.accepted');
        $this->assertDatabaseCount('exam_versions', 1);
    }

    public function test_import_rolls_back_all_rows_on_a_validation_error(): void
    {
        $e = $this->exam();
        $row = $e->only(['slug', 'title', 'description', 'skill', 'test_type', 'duration_seconds', 'draft']);
        $row['slug'] = 'valid-import';
        $bad = $row;
        $bad['slug'] = 'invalid-import';
        $bad['draft']['sections'][0]['questions'][0]['type'] = 'executable_html';
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/v1/admin/import', ['tests' => [$row, $bad], 'commit' => true])->assertOk()->assertJsonPath('committed', false)->assertJsonPath('errors.0.row', 2);
        $this->assertDatabaseMissing('exams', ['slug' => 'valid-import']);
        $this->postJson('/api/v1/admin/import', ['tests' => [$row], 'commit' => false])->assertJsonPath('valid', true);
        $this->assertDatabaseMissing('exams', ['slug' => 'valid-import']);
        $this->postJson('/api/v1/admin/import', ['tests' => [$row], 'commit' => true])->assertJsonPath('committed', true);
        $this->assertDatabaseHas('exams', ['slug' => 'valid-import', 'status' => 'draft']);
    }

    public function test_teacher_review_is_assignment_scoped_and_drafts_stay_private(): void
    {
        Notification::fake();
        $a = $this->start($u = User::factory()->create(), $this->exam('writing'));
        app(AttemptService::class)->finalize($a);
        $review = $a->assessment;
        $teacher = User::factory()->create(['role' => 'teacher']);
        $review->update(['reviewer_id' => $teacher->id]);
        $criteria = ['task1' => ['task' => 6, 'coherence' => 6, 'lexical' => 6, 'grammar' => 6], 'task2' => ['task' => 7, 'coherence' => 7, 'lexical' => 7, 'grammar' => 7]];
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->getJson('/api/v1/reviews/'.$review->id)->assertNotFound();
        $this->actingAs($teacher)->putJson('/api/v1/reviews/'.$review->id, ['revision' => 0, 'criteria' => $criteria, 'feedback' => 'Private draft feedback with examples.', 'publish' => false])->assertOk()->assertJsonPath('status', 'draft');
        $this->actingAs($u)->getJson('/api/v1/progress')->assertDontSee('Private draft feedback');
        $this->getJson('/api/v1/attempts/'.$a->id)->assertDontSee('Private draft feedback');
        $this->actingAs($teacher)->putJson('/api/v1/reviews/'.$review->id, ['revision' => 0, 'criteria' => $criteria, 'feedback' => 'Stale edit must not overwrite.', 'publish' => true])->assertConflict();
        $this->putJson('/api/v1/reviews/'.$review->id, ['revision' => 1, 'criteria' => $criteria, 'feedback' => 'Clear ideas; develop the examples more fully.', 'publish' => true])->assertOk()->assertJsonPath('band', 6.5);
        Notification::assertSentTo($u, FeedbackPublished::class);
        $this->actingAs($u)->getJson('/api/v1/attempts/'.$a->id)->assertJsonPath('assessment.band', 6.5)->assertJsonPath('status', 'reviewed');
    }

    public function test_private_recording_urls_require_owner_or_assigned_reviewer(): void
    {
        Storage::fake('local');
        $a = $this->start($u = User::factory()->create(), $this->exam('speaking'));
        $recording = Recording::create(['attempt_id' => $a->id, 'question_id' => 'speak1', 'upload_key' => (string) Str::uuid(), 'disk' => 'local', 'path' => 'recordings/private.wav', 'mime' => 'audio/wav', 'size' => 100, 'expires_at' => now()->addDay()]);
        Storage::disk('local')->put($recording->path, 'private audio');
        $url = $this->actingAs($u)->getJson('/api/v1/recordings/'.$recording->id.'/url')->assertOk()->json('url');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->getJson('/api/v1/recordings/'.$recording->id.'/url')->assertNotFound();
        $this->actingAs($u)->get($url)->assertOk()->assertHeader('cache-control', 'no-store, private');
    }

    public function test_recording_upload_rejects_non_audio_and_accepts_valid_wav_once(): void
    {
        Storage::fake('local');
        $a = $this->start($u = User::factory()->create(), $this->exam('speaking'));
        $this->actingAs($u);
        $url = '/api/v1/attempts/'.$a->id.'/recordings';
        $this->postJson($url, ['question_id' => 'speak1', 'upload_key' => (string) Str::uuid(), 'recording' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')])->assertUnprocessable();
        $key = (string) Str::uuid();
        $this->postJson($url, ['question_id' => 'speak1', 'upload_key' => $key, 'recording' => new UploadedFile(database_path('content/listening-1.wav'), 'response.wav', 'audio/wav', null, true)])->assertCreated();
        $this->postJson($url, ['question_id' => 'speak1', 'upload_key' => $key, 'recording' => new UploadedFile(database_path('content/listening-1.wav'), 'response.wav', 'audio/wav', null, true)])->assertCreated();
        $this->assertDatabaseCount('recordings', 1);
    }

    public function test_students_and_teachers_cannot_publish_content_or_open_admin_users(): void
    {
        $e = $this->exam();
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->postJson('/api/v1/admin/tests/'.$e->id.'/publish')->assertForbidden();
            $this->get('/admin/users')->assertForbidden();
        }
    }

    public function test_contact_is_persisted_and_unconfigured_services_are_honest(): void
    {
        $this->postJson('/api/v1/contact', ['name' => 'Learner', 'email' => 'hello@example.test', 'message' => 'Please help with my practice.'])->assertCreated()->assertJsonPath('delivery_status', 'stored_not_sent');
        $this->assertDatabaseCount('contacts', 1);
        $this->postJson('/api/v1/auth/forgot-password',['email' => 'hello@example.test'])->assertServiceUnavailable();
        $this->postJson('/api/v1/billing/webhook',['type' => 'payment.success', 'amount' => 999])->assertServiceUnavailable();
        $this->assertDatabaseCount('payments',0);
        $this->assertDatabaseCount('entitlements',0);
    }

    public function test_published_versions_refuse_updates(): void
    {
        $version = $this->exam()->versions()->first();
        $this->expectException(\LogicException::class);
        $version->update(['scoring' => ['reviewed' => true]]);
    }
}
