<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Exam extends Model {
 protected $guarded = [];
 public function versions() { return $this->hasMany(ExamVersion::class); }
 public function collection() { return $this->belongsTo(Collection::class); }
 public function attempts() { return $this->hasMany(Attempt::class); }
 protected function casts(): array { return ['draft'=>'array', 'tags'=>'array', 'premium'=>'boolean', 'sample'=>'boolean', 'demo'=>'boolean']; }
}
