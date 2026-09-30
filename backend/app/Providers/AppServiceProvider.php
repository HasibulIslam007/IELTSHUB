<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{RateLimiter,Gate};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  RateLimiter::for('api',fn(Request $r)=>Limit::perMinute(180)->by($r->user()?->id?:$r->ip()));
  RateLimiter::for('login',fn(Request $r)=>Limit::perMinute(5)->by(mb_strtolower($r->input('email','')).'|'.$r->ip()));
  \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn($user,$token)=>url('/reset-password/'.$token).'?email='.urlencode($user->email));
  foreach([\App\Models\Exam::class,\App\Models\Collection::class,\App\Models\User::class,\App\Models\Entitlement::class,\App\Models\MediaAsset::class,\App\Models\Plan::class,\App\Models\Setting::class,\App\Models\Contact::class] as $model){$model::saved(fn($record)=>\App\Services\Audit::record('record.saved',$record,array_keys($record->getChanges())));}
 }
}
