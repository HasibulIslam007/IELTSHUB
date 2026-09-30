<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Exam,Collection,Contact,Plan,Setting};
use App\Services\{ContentService,AccessService};
use Illuminate\Http\Request;
class CatalogueController extends Controller {
 public function config(){return ['brand'=>Setting::where('key','brand')->value('value')?:config('app.name'),'support'=>Setting::where('key','support_email')->value('value'),'integrations'=>['payments'=>false,'email'=>config('hub.mail_enabled'),'ai'=>false],'plans'=>Plan::where('active',true)->get(),'question_types'=>ContentService::TYPES];}
 public function index(Request $r){
  $q=Exam::where('status','published')->with('collection');
  if($r->filled('search'))$q->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower(mb_substr($r->string('search'),0,100)).'%']);
  foreach(['skill','test_type','collection_id'] as $f)if($r->filled($f))$q->where($f,$r->input($f));
  if($r->filled('access'))$q->where('premium',$r->input('access')==='premium');
  if($r->filled('tag'))$q->whereJsonContains('tags',$r->input('tag'));
  if($r->input('length')==='short')$q->where('duration_seconds','<',1800);if($r->input('length')==='full')$q->where('duration_seconds','>=',1800);
  $u=$r->user('sanctum');
  if($u&&$r->filled('status')){if($r->input('status')==='not_started')$q->whereDoesntHave('attempts',fn($a)=>$a->where('user_id',$u->id));else $q->whereHas('attempts',fn($a)=>$a->where('user_id',$u->id)->whereIn('status',$r->input('status')==='in_progress'?['active','paused']:['submitted','awaiting_review','reviewed']));}
  match($r->input('sort')){'title'=>$q->orderBy('title'),'duration'=>$q->orderBy('duration_seconds'),default=>$q->orderBy('id')};
  $page=$q->paginate(9);$page->setCollection($page->getCollection()->map(fn($e)=>$this->card($e,$u)));return $page;
 }
 public function card(Exam $e,$u=null){$v=$e->versions()->where('number',$e->published_version)->first();$attempt=$u?$e->attempts()->where('user_id',$u->id)->latest()->first():null;return $e->only(['id','slug','title','description','skill','test_type','premium','demo','sample','duration_seconds','tags','collection_id'])+['collection'=>$e->collection?->title,'question_count'=>collect($v?->content['sections']??[])->sum(fn($s)=>count($s['questions'])),'attempt'=>$attempt?->only(['id','status','revision']),'can_access'=>$u?app(AccessService::class)->canStart($u,$e):!$e->premium];}
 public function show(Request $r,Exam $exam){abort_unless($exam->status==='published',404);$v=$exam->versions()->where('number',$exam->published_version)->firstOrFail();return $this->card($exam,$r->user('sanctum'))+['sections'=>array_map(fn($s)=>['id'=>$s['id'],'title'=>$s['title'],'question_count'=>count($s['questions'])],$v->content['sections']),'rules'=>['practice'=>'Choose parts, pause and replay audio. Explanations are available after submission.','mock'=>'The server deadline continues after refresh or disconnection. Audio may be played once per part. Saved work is finalized when time expires.']];}
 public function sample(ContentService $content){$e=Exam::where('sample',true)->where('status','published')->firstOrFail();return $this->card($e)+['content'=>$content->publicContent($e->versions()->where('number',$e->published_version)->firstOrFail()->content)];}
 public function collections(){return Collection::all();}
 public function contact(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:254','message'=>'required|string|min:10|max:5000']);$c=Contact::create($d);return response()->json(['id'=>$c->id,'message'=>'Your enquiry is stored for the support team. No email has been sent.','delivery_status'=>$c->delivery_status],201);}
}
