<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Attempt,Bookmark,Exam};
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash,DB};
class StudentController extends Controller {
 public function profile(Request $r){return ['user'=>$r->user(),'entitlements'=>$r->user()->entitlements()->whereNull('revoked_at')->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->get()];}
 public function updateProfile(Request $r){$d=$r->validate(['test_type'=>'required|in:academic,general','target_band'=>'required|numeric|min:1|max:9|multiple_of:0.5','exam_date'=>'nullable|date','daily_minutes'=>'required|integer|min:5|max:240','current_level'=>'nullable|numeric|min:1|max:9|multiple_of:0.5','reminders'=>'boolean']);$r->user()->update(['profile'=>$d]);return ['user'=>$r->user()];}
 public function progress(Request $r){
  $all=$r->user()->attempts()->with(['exam:id,title,skill,test_type','assessment'])->latest()->get();$done=$all->whereNotNull('submitted_at');$types=[];$skills=[];
  foreach(['reading','listening','writing','speaking'] as $skill){$items=$done->filter(fn($a)=>$a->exam->skill===$skill);$skills[$skill]=['completed'=>$items->count(),'trend'=>$items->reverse()->map(fn($a)=>['date'=>$a->submitted_at->toDateString(),'accuracy'=>isset($a->result['total'])&&$a->result['total']>0?round(100*$a->result['raw_score']/$a->result['total']):null,'band'=>$a->assessment?->status==='published'?$a->assessment->band:($a->result['estimated_band']??null)])->values()];}
  foreach($done as $a)foreach($a->result['breakdown']??[] as $type=>$stats){$types[$type]??=['correct'=>0,'total'=>0];$types[$type]['correct']+=$stats['correct'];$types[$type]['total']+=$stats['total'];}
  uasort($types,fn($a,$b)=>($a['correct']/$a['total'])<=>($b['correct']/$b['total']));$weak=array_key_first($types);$reason=$weak?'You missed '.($types[$weak]['total']-$types[$weak]['correct']).' of '.$types[$weak]['total'].' '.str_replace('_',' ',$weak).' questions.':'Start with a short Reading exercise to establish a baseline.';
  $recommended=Exam::where('status','published')->where('premium',false)->where('skill','reading')->first();
  return ['skills'=>$skills,'question_types'=>$types,'completed'=>$done->count(),'active'=>$all->whereIn('status',['active','paused'])->values(),'recent'=>$all->take(6)->values(),'activity'=>$all->groupBy(fn($a)=>$a->created_at->toDateString())->map->count(),'recommendation'=>['reason'=>$reason,'exam_id'=>$recommended?->id],'study_plan'=>['daily_minutes'=>$r->user()->profile['daily_minutes']??30,'steps'=>['Practise a skill for half your available time.','Review explanations and save one mistake.','Spend the remaining time on your weakest question type.']]];
 }
 public function bookmarks(Request $r){return Bookmark::where('user_id',$r->user()->id)->with('attempt.exam:id,title,skill')->latest()->get()->map(function($b){$item=collect($b->attempt->result['items']??[])->firstWhere('id',$b->question_id);return $b->only(['id','attempt_id','question_id','note','created_at'])+['item'=>$item,'test'=>$b->attempt->exam->title];});}
 public function bookmark(Request $r,Attempt $attempt){app(AccessService::class)->own($r->user(),$attempt);$d=$r->validate(['question_id'=>'required|string|max:80','note'=>'nullable|string|max:3000']);abort_unless(collect($attempt->result['items']??[])->contains('id',$d['question_id']),422);return Bookmark::updateOrCreate(['user_id'=>$r->user()->id,'attempt_id'=>$attempt->id,'question_id'=>$d['question_id']],['note'=>$d['note']??'']);}
 public function deleteBookmark(Request $r,Bookmark $bookmark){abort_unless($bookmark->user_id===$r->user()->id,404);$bookmark->delete();return response()->noContent();}
 public function notifications(Request $r){return $r->user()->notifications()->latest()->limit(30)->get();}
 public function readNotifications(Request $r){$r->user()->unreadNotifications->markAsRead();return response()->noContent();}
 public function export(Request $r){return response()->json(['user'=>$r->user(),'attempts'=>$r->user()->attempts()->get(),'bookmarks'=>Bookmark::where('user_id',$r->user()->id)->get(),'entitlements'=>$r->user()->entitlements()->get()])->header('Content-Disposition','attachment; filename="ielts-account-export.json"');}
 public function deletion(Request $r){$r->validate(['password'=>'required|string']);abort_unless(Hash::check($r->password,$r->user()->password),422,'Incorrect password.');$r->user()->forceFill(['deletion_requested_at'=>now()])->save();return ['message'=>'Deletion requested. The administrator will review retention requirements and process the request.'];}
}
