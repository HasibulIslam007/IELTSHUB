<?php
namespace App\Services;
use App\Models\{Exam,User,Attempt};
class AccessService {
 public function canStart(User $user,Exam $exam): bool {
  if($user->suspended)return false; if(!$exam->premium)return true;
  return $user->entitlements()->whereNull('revoked_at')->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->where(fn($q)=>$q->whereNull('exam_id')->orWhere('exam_id',$exam->id))->exists();
 }
 public function own(User $user,Attempt $attempt): void { abort_unless(!$user->suspended&&$attempt->user_id===$user->id,404); }
 public function review(User $user,Attempt $attempt): void { abort_unless(!$user->suspended&&($user->role==='admin'||($user->role==='teacher'&&$attempt->assessment?->reviewer_id===$user->id)),404); }
 public function recording(User $user,Attempt $attempt): void { if($attempt->user_id===$user->id&&!$user->suspended)return; $this->review($user,$attempt); }
}
