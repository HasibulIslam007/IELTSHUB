<?php
namespace App\Services;
use App\Models\AuditEvent;
class Audit {
 public static function record(string $action, $subject, array $changes = []): void { AuditEvent::create(['actor_id'=>auth()->id(),'action'=>$action,'subject_type'=>class_basename($subject),'subject_id'=>(string)$subject->getKey(),'changes'=>$changes,'created_at'=>now()]); }
}
