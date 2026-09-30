<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Attempt,Exam,Recording,MediaAsset};
use App\Services\{AttemptService,AccessService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,URL};
class AttemptController extends Controller {
 public function __construct(private AttemptService $engine,private AccessService $access){}
 public function index(Request $r){return $r->user()->attempts()->with('exam:id,title,skill,test_type')->latest()->paginate(20);}
 public function start(Request $r,Exam $exam){$d=$r->validate(['request_key'=>'required|uuid','mode'=>'required|in:practice,mock','timed'=>'boolean','section_ids'=>'array','section_ids.*'=>'string']);return response()->json($this->engine->serialize($this->engine->start($r->user(),$exam,$d)),201);}
 public function show(Request $r,Attempt $attempt){$this->access->own($r->user(),$attempt);return $this->engine->serialize($this->engine->refresh($attempt));}
 public function mutate(Request $r,Attempt $attempt,string $action='save'){
  $this->access->own($r->user(),$attempt);abort_unless(in_array($action,['save','submit','pause','resume']),404);
  $d=$r->validate(['request_key'=>'required|uuid','revision'=>'required|integer|min:0','answers'=>'array|max:150','flags'=>'array|max:150','flags.*'=>'string|max:80','notes'=>'array|max:150','notes.*'=>'string|max:5000','highlights'=>'array|max:150','highlights.*'=>'string|max:3000']);
  return $this->engine->serialize($this->engine->mutate($attempt,$d,$action));
 }
 public function upload(Request $r,Attempt $attempt){
  $this->access->own($r->user(),$attempt);$d=$r->validate(['question_id'=>'required|string','upload_key'=>'required|uuid','recording'=>'required|file|max:25600|mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/mp4,video/mp4,audio/mpeg,audio/x-m4a,audio/wav,audio/x-wav']);
  $a=$this->engine->refresh($attempt);abort_unless($a->status==='active',409,'The attempt is no longer accepting recordings.');
  $record=DB::transaction(function()use($a,$d,$r){$a=Attempt::lockForUpdate()->findOrFail($a->id);abort_unless($a->status==='active'&&!$this->engine->expired($a),409,'The deadline has passed.');$old=Recording::where('attempt_id',$a->id)->where('upload_key',$d['upload_key'])->first();if($old)return $old;
   $q=collect($a->version->content['sections'])->whereIn('id',$a->section_ids)->flatMap(fn($s)=>$s['questions'])->firstWhere('id',$d['question_id']);abort_unless($q&&$q['type']==='speaking',422);
   $file=$r->file('recording');$disk=config('filesystems.default');$path=$file->store('recordings/'.$a->user_id,$disk);abort_unless($path,503,'Storage unavailable. Keep your recording and retry.');
   try{$rec=Recording::create(['attempt_id'=>$a->id,'question_id'=>$d['question_id'],'upload_key'=>$d['upload_key'],'disk'=>$disk,'path'=>$path,'mime'=>$file->getMimeType(),'size'=>$file->getSize(),'expires_at'=>now()->addDays(config('hub.recording_retention_days'))]);$a->update(['answers'=>array_replace($a->answers,[$d['question_id']=>$rec->id]),'revision'=>$a->revision+1]);return $rec;}catch(\Throwable $e){Storage::disk($disk)->delete($path);throw $e;}
  });return response()->json(['recording'=>$record,'attempt'=>$this->engine->serialize($a->fresh())],201);
 }
 public function mediaUrl(Request $r,Recording $recording){$this->access->recording($r->user(),$recording->attempt);abort_if($recording->expires_at?->isPast(),410,'Recording retention period has ended.');return ['url'=>URL::temporarySignedRoute('recording.stream',now()->addMinutes(5),['recording'=>$recording->id])];}
 public function stream(Request $r,Recording $recording){$this->access->recording($r->user(),$recording->attempt);abort_if($recording->expires_at?->isPast(),410);return Storage::disk($recording->disk)->response($recording->path,null,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
 public function audio(Request $r,Attempt $attempt, string $section){
  $this->access->own($r->user(),$attempt);$a=$this->engine->refresh($attempt);$s=collect($a->version->content['sections'])->firstWhere('id',$section);abort_unless($s&&in_array($section,$a->section_ids)&&isset($s['audio_asset_id']),404);
  DB::transaction(function()use($a,$section){$locked=Attempt::lockForUpdate()->findOrFail($a->id);$play=$locked->playback;if($locked->mode==='mock'&&$locked->status==='active'&&isset($play[$section]))abort(409,'This part has already started. Resume using the saved playback position.');$play[$section]=now()->toISOString();$locked->update(['playback'=>$play]);});
  return ['url'=>URL::temporarySignedRoute('attempt.audio',now()->addHours(3),['attempt'=>$a->id,'section'=>$section])];
 }
 public function audioStream(Request $r,Attempt $attempt,string $section){$this->access->own($r->user(),$attempt);$s=collect($attempt->version->content['sections'])->firstWhere('id',$section);abort_unless($s&&in_array($section,$attempt->section_ids),404);$asset=MediaAsset::findOrFail($s['audio_asset_id']);return Storage::disk($asset->disk)->response($asset->path,null,['Cache-Control'=>'private, no-store']);}
 public function audioRecovery(Request $r,Attempt $attempt,string $section){$this->access->own($r->user(),$attempt);abort_unless(isset($attempt->playback[$section]),404);return ['url'=>URL::temporarySignedRoute('attempt.audio',now()->addHours(3),['attempt'=>$attempt->id,'section'=>$section])];}
}
