<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Bookmark extends Model {
 protected $guarded = [];
 public function attempt() { return $this->belongsTo(Attempt::class); }
 protected function casts(): array { return []; }
}
