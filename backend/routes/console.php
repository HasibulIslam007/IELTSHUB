<?php

use App\Models\Attempt;
use App\Models\Recording;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('hub:dev-accounts {--credentials= : Write new credentials to this private file}', function () {
    if (! app()->environment('local', 'testing')) {
        $this->error('Development accounts are disabled outside local/testing.');

        return 1;
    }
    $accounts = [];
    foreach (['student', 'teacher', 'admin'] as $role) {
        $email = $role.'@ielts.local';
        $user = User::where('email', $email)->first();
        if ($user) {
            $this->line($email.' already exists; its password was not changed.');

            continue;
        }$password = bin2hex(random_bytes(12));
        $user = new User(['name' => ucfirst($role).' Demo', 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();
        $accounts[] = ['email' => $email, 'password' => $password];
    }
    if ($accounts) {
        $path = $this->option('credentials') ?: storage_path('app/private/development-accounts.json');
        file_put_contents($path, json_encode($accounts, JSON_PRETTY_PRINT));
        chmod($path, 0600);
        $this->info('Generated random account credentials in '.$path.'. Keep this file private.');
    }
})->purpose('Create local-only demo roles with randomly generated credentials');
Artisan::command('hub:finalize-expired', function () {
    Attempt::where('status', 'active')->where('deadline_at', '<=', now())->chunkById(100, function ($attempts) {
        foreach ($attempts as $attempt) {
            app(AttemptService::class)->refresh($attempt);
        }
    });
    $this->info('Expired attempts finalized.');
});
Artisan::command('hub:prune-recordings', function () {
    Recording::where('expires_at', '<=', now())->chunkById(100, function ($recordings) {
        foreach ($recordings as $r) {
            Storage::disk($r->disk)->delete($r->path);
        }
    });
    $this->info('Expired recording files removed; metadata retained.');
});
Artisan::command('hub:delete-account {user}', function () {
    $user = User::findOrFail($this->argument('user'));
    if (! $user->deletion_requested_at) {
        $this->error('No deletion request exists.');

        return 1;
    }
    DB::transaction(function () use ($user) {
        foreach (Recording::whereHas('attempt', fn ($q) => $q->where('user_id', $user->id))->get() as $recording) {
            Storage::disk($recording->disk)->delete($recording->path);
        }$user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->notifications()->delete();
        $user->delete();
    });
    $this->info('Requested account and associated student data deleted.');
});
Schedule::command('hub:finalize-expired')->everyMinute()->withoutOverlapping();
Schedule::command('hub:prune-recordings')->daily()->withoutOverlapping();
