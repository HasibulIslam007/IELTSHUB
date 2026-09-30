<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExamVersion extends Model {
 protected $guarded = [];
 public $timestamps = false;
 public function exam() { return $this->belongsTo(Exam::class); }
 protected static function booted(): void { static::updating(fn()=>throw new \LogicException('Published versions are immutable.')); static::deleting(fn()=>throw new \LogicException('Archive the test instead.')); }
 protected function casts(): array { return ['content'=>'array', 'scoring'=>'array', 'created_at'=>'datetime']; }
}
