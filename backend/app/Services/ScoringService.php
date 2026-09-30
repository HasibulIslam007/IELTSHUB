<?php
namespace App\Services;
class ScoringService {
 public function normalize(string $answer): string { return mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace("\u{00A0}",' ',$answer)))); }
 public function mark(array $q, mixed $answer): bool {
  if($q['type']==='multiple_choice'){
   if(!is_array($answer)||count($answer)!==($q['select_count']??count($q['accepted'])))return false;
   $a=array_map($this->normalize(...),$answer);$b=array_map($this->normalize(...),$q['accepted']);sort($a);sort($b);return $a===$b;
  }
  if(!is_string($answer))return false;
  $answer=$this->normalize($answer); if($answer==='')return false;
  if(isset($q['max_words'])){
   $words=preg_split('/\s+/u',$answer,-1,PREG_SPLIT_NO_EMPTY); $numbers=count(array_filter($words,fn($w)=>preg_match('/^[\d.,:]+$/u',$w)));
   if($numbers>($q['max_numbers']??0)||count($words)-$numbers>$q['max_words'])return false;
  }
  return in_array($answer,array_map($this->normalize(...),$q['accepted']),true);
 }
 public function band(int $raw, int $total, array $scoring): ?float {
  if($total!==40||!($scoring['reviewed']??false))return null; $band=null;
  foreach($scoring['thresholds']??[] as $r)if($raw>=$r['min'])$band=(float)$r['band']; return $band;
 }
 public function score(array $content,array $answers,array $sectionIds,array $scoring): array {
  $items=[];$breakdown=[];$correct=0;
  foreach($content['sections'] as $s){if(!in_array($s['id'],$sectionIds))continue;foreach($s['questions'] as $q){$ok=$this->mark($q,$answers[$q['id']]??'');$correct+=(int)$ok;$type=$q['type'];$breakdown[$type]??=['correct'=>0,'total'=>0];$breakdown[$type]['correct']+=(int)$ok;$breakdown[$type]['total']++;
   $items[]=['id'=>$q['id'],'type'=>$type,'prompt'=>$q['prompt'],'your_answer'=>$answers[$q['id']]??'','accepted'=>$q['accepted'],'correct'=>$ok,'explanation'=>$q['explanation'],'reference'=>$q['reference']??$s['title']];
  }}
  return ['raw_score'=>$correct,'total'=>count($items),'estimated_band'=>$this->band($correct,count($items),$scoring),'items'=>$items,'breakdown'=>$breakdown,'band_note'=>'Practice result, not an official IELTS score. Uncalibrated demonstration tests do not receive band estimates.'];
 }
 public function assessmentBand(string $skill,array $criteria): float {
  $average=fn($s)=>array_sum($s)/count($s);
  $score=$skill==='writing'?($average($criteria['task1'])+2*$average($criteria['task2']))/3:$average($criteria['speaking']);
  return round($score*2,0,PHP_ROUND_HALF_UP)/2;
 }
}
