<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Assessment,Exam,User};
use App\Services\{AccessService,AssessmentService,ContentService,Audit};
use Illuminate\Http\Request;
class WorkspaceController extends Controller {
 public function reviews(Request $r){abort_unless(in_array($r->user()->role,['teacher','admin']),403);$q=Assessment::with('attempt.exam:id,title,skill','attempt.user:id,name','reviewer:id,name');if($r->user()->role==='teacher')$q->where('reviewer_id',$r->user()->id);if($r->filled('status'))$q->where('status',$r->status);return $q->paginate(20);}
 public function review(Request $r,Assessment $assessment){app(AccessService::class)->review($r->user(),$assessment->attempt);return $assessment->load('attempt.version','attempt.recordings','attempt.user:id,name','reviewer:id,name');}
 public function saveReview(Request $r,Assessment $assessment,AssessmentService $service){$d=$r->validate(['revision'=>'required|integer','criteria'=>'array','criteria.*'=>'array','criteria.*.*'=>'numeric|min:0|max:9|multiple_of:0.5','feedback'=>'nullable|string|max:20000','publish'=>'boolean','correction_reason'=>'nullable|string|max:1000']);return $service->save($assessment,$r->user(),$d,$d['publish']??false);}
 public function assign(Request $r,Assessment $assessment){abort_unless($r->user()->role==='admin',403);$d=$r->validate(['reviewer_id'=>'required|exists:users,id']);abort_unless(User::whereKey($d['reviewer_id'])->where('role','teacher')->where('suspended',false)->exists(),422);$assessment->update($d+['revision'=>$assessment->revision+1]);Audit::record('assessment.assigned',$assessment,$d);return $assessment;}
 public function publish(Request $r,Exam $exam,ContentService $service){return $service->publish($exam,$r->user());}
 public function import(Request $r,ContentService $service){$d=$r->validate(['tests'=>'required|array|min:1|max:50','commit'=>'boolean']);return $service->import($d['tests'],$r->user(),$d['commit']??false);}
 public function export(Request $r,Exam $exam){abort_unless($r->user()->role==='admin',403);return response()->json([$exam->only(['slug','title','description','skill','test_type','duration_seconds','draft'])])->header('Content-Disposition','attachment; filename="test-'.$exam->id.'.json"');}
}
