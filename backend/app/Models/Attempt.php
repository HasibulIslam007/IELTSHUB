<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Attempt extends Model {
 protected $guarded = [];
 use \Illuminate\Database\Eloquent\Concerns\HasUuids;
 public function user() { return $this->belongsTo(User::class); }
 public function exam() { return $this->belongsTo(Exam::class); }
 public function version() { return $this->belongsTo(ExamVersion::class, 'exam_version_id'); }
 public function assessment() { return $this->hasOne(Assessment::class); }
 public function recordings() { return $this->hasMany(Recording::class); }
 protected function casts(): array { return ['section_ids'=>'array', 'answers'=>'array', 'flags'=>'array', 'notes'=>'array', 'highlights'=>'array', 'playback'=>'array', 'result'=>'array', 'started_at'=>'datetime', 'deadline_at'=>'datetime', 'paused_at'=>'datetime', 'submitted_at'=>'datetime']; }
}
