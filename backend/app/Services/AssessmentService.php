<?php
namespace App\Services;
use App\Models\{Assessment,User};
use App\Notifications\FeedbackPublished;
use Illuminate\Support\Facades\{DB,Validator};
class AssessmentService {
 public function save(Assessment $assessment,User $actor,array $data,bool $publish=false): Assessment {
  return DB::transaction(function()use($assessment,$actor,$data,$publish){
   $a=Assessment::lockForUpdate()->findOrFail($assessment->id);app(AccessService::class)->review($actor,$a->attempt);
   abort_unless($a->revision===(int)$data['revision'],409,'A newer review exists. Reload before editing.');
   if($publish){
    $skill=$a->attempt->version->content['meta']['skill'];$keys=$skill==='writing'?['task1','task2']:['speaking'];$rules=['feedback'=>'required|string|min:20|max:20000','criteria'=>'required|array'];
    foreach($keys as $key){$rules["criteria.$key"]='required|array|size:4';$rules["criteria.$key.*"]='required|numeric|min:0|max:9|multiple_of:0.5';}
    Validator::make($data,$rules)->validate();$band=app(ScoringService::class)->assessmentBand($skill,$data['criteria']);
   }
   $a->update(['criteria'=>$data['criteria']??[],'feedback'=>$data['feedback']??'','revision'=>$a->revision+1,'status'=>$publish?'published':'draft','published_at'=>$publish?now():null,'band'=>$publish?$band:null]);
   if($publish){$a->attempt->update(['status'=>'reviewed']);$a->attempt->user->notify(new FeedbackPublished($a->attempt_id));}else{$a->attempt->update(['status'=>'awaiting_review']);}
   Audit::record($publish?'assessment.published':'assessment.draft',$a,['revision'=>$a->revision,'correction_reason'=>$data['correction_reason']??null]);return $a;
  });
 }
}
