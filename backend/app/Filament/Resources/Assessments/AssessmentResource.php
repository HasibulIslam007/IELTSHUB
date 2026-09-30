<?php
namespace App\Filament\Resources\Assessments;
use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Models\{Assessment,User};
use App\Services\{AssessmentService,Audit};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\{TextInput,Textarea,Select,Hidden};
use Filament\Tables\{Table,Columns\TextColumn,Filters\SelectFilter};
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\{Builder,Model};
class AssessmentResource extends Resource {
 protected static ?string $model=Assessment::class;
 protected static ?string $navigationLabel='Submission reviews';
 public static function canViewAny(): bool {return in_array(auth()->user()?->role,['teacher','admin'])&&!auth()->user()?->suspended;}
 public static function canCreate(): bool {return false;}
 public static function canEdit(Model $record): bool {return static::canViewAny()&&(auth()->user()->role==='admin'||$record->reviewer_id===auth()->id());}
 public static function canDelete(Model $record): bool {return false;}
 public static function getEloquentQuery(): Builder {$q=parent::getEloquentQuery()->with(['attempt.user','attempt.exam','reviewer']);return auth()->user()?->role==='teacher'?$q->where('reviewer_id',auth()->id()):$q;}
 public static function reviewFields(): array {
  $fields=[Hidden::make('revision'),Textarea::make('feedback')->rows(8)->maxLength(20000)->columnSpanFull()->helperText('Explain the evidence for scores and give concrete next steps. Publish requires at least 20 characters.')];
  foreach(['task1','task2','speaking'] as $task){$criteria=$task==='speaking'?['fluency'=>'Fluency and coherence','lexical'=>'Lexical resource','grammar'=>'Grammatical range and accuracy','pronunciation'=>'Pronunciation']:['task'=>'Task achievement / response','coherence'=>'Coherence and cohesion','lexical'=>'Lexical resource','grammar'=>'Grammatical range and accuracy'];
   foreach($criteria as $key=>$label){$fields[]=TextInput::make("criteria.$task.$key")->label(ucfirst($task).' · '.$label)->numeric()->minValue(0)->maxValue(9)->step(0.5)->visible(fn(Assessment $record)=>($record->attempt->exam->skill==='speaking')===($task==='speaking'));}
  }
  $fields[]=Textarea::make('correction_reason')->label('Reason for correcting previously published feedback')->visible(fn(Assessment $record)=>$record->status==='published')->required(fn(Assessment $record)=>$record->status==='published')->columnSpanFull();
  return $fields;
 }
 public static function form(Schema $schema): Schema {return $schema->components(self::reviewFields());}
 public static function table(Table $table): Table {return $table->columns([TextColumn::make('attempt.user.name')->label('Student')->searchable(),TextColumn::make('attempt.exam.title')->label('Test')->wrap(),TextColumn::make('attempt.exam.skill')->badge(),TextColumn::make('reviewer.name')->placeholder('Unassigned'),TextColumn::make('status')->badge(),TextColumn::make('band'),TextColumn::make('updated_at')->dateTime()])->filters([SelectFilter::make('status')->options(['awaiting_review'=>'Awaiting review','draft'=>'Draft','published'=>'Published']),SelectFilter::make('skill')->options(['writing'=>'Writing','speaking'=>'Speaking'])->query(fn(Builder $query,array $data)=>$query->when($data['value'],fn($q,$skill)=>$q->whereHas('attempt.exam',fn($e)=>$e->where('skill',$skill))))])->recordActions([
  Action::make('submission')->label('Read / listen')->url(fn(Assessment $record)=>url('/admin/submissions/'.$record->id))->openUrlInNewTab(),
  Action::make('assign')->visible(fn()=>auth()->user()->role==='admin')->schema([Select::make('reviewer_id')->options(fn()=>User::where('role','teacher')->where('suspended',false)->pluck('name','id'))->required()])->action(function(Assessment $record,array $data){$record->update($data+['revision'=>$record->revision+1]);Audit::record('assessment.assigned',$record,$data);}),
  Action::make('draft')->label('Draft feedback')->schema(self::reviewFields())->fillForm(fn(Assessment $record)=>$record->toArray())->action(fn(Assessment $record,array $data)=>app(AssessmentService::class)->save($record,auth()->user(),$data,false)),
  Action::make('publish')->label('Publish feedback')->color('success')->schema(self::reviewFields())->fillForm(fn(Assessment $record)=>$record->toArray())->action(fn(Assessment $record,array $data)=>app(AssessmentService::class)->save($record,auth()->user(),$data,true)),
 ])->defaultSort('created_at','desc');}
 public static function getPages(): array {return ['index'=>ManageAssessments::route('/')];}
}
