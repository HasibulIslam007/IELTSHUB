<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Recording extends Model {
 protected $guarded = [];
 use \Illuminate\Database\Eloquent\Concerns\HasUuids;
 protected $hidden = ['path','disk'];
 public function attempt() { return $this->belongsTo(Attempt::class); }
 protected function casts(): array { return ['expires_at'=>'datetime']; }
}
