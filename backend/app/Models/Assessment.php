<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assessment extends Model {
 protected $guarded = [];
 public function attempt() { return $this->belongsTo(Attempt::class); }
 public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
 protected function casts(): array { return ['criteria'=>'array', 'published_at'=>'datetime', 'band'=>'float']; }
}
