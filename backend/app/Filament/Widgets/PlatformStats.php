<?php
namespace App\Filament\Widgets;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\{User,Attempt,Assessment};
class PlatformStats extends StatsOverviewWidget {
 protected function getStats(): array {
  if(auth()->user()->role==='teacher'){return [Stat::make('Assigned reviews',Assessment::where('reviewer_id',auth()->id())->count()),Stat::make('Awaiting your feedback',Assessment::where('reviewer_id',auth()->id())->where('status','!=','published')->count())];}
  $started=Attempt::count();$completed=Attempt::whereNotNull('submitted_at')->count();
  return [Stat::make('Registered learners',User::where('role','student')->count()),Stat::make('Active learners',Attempt::where('updated_at','>=',now()->subDays(30))->distinct()->count('user_id'))->description('Saved attempt activity in the past 30 days'),Stat::make('Attempts started / completed',"$started / $completed")->description('Completion: '.($started?round(100*$completed/$started):0).'%'),Stat::make('Review backlog',Assessment::where('status','!=','published')->count()),Stat::make('Billing','Not configured')->description('No revenue claimed')];
 }
}
