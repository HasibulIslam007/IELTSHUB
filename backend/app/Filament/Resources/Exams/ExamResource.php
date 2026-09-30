<?php
namespace App\Filament\Resources\Exams;
use App\Models\Exam;
use App\Filament\Resources\Exams\Pages\ManageExams;
use Filament\Forms\Components\{TextInput,Textarea,Select,Toggle,DateTimePicker,Repeater,TagsInput,FileUpload,Hidden};
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn,IconColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\{Action,EditAction,CreateAction};
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use App\Services\{Audit,ContentService,AssessmentService};
use Filament\Notifications\Notification;

class ExamResource extends Resource {
 protected static ?string $model = Exam::class;
 protected static ?string $recordTitleAttribute = 'title';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('title')->required()->maxLength(200),TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord:true),
 Textarea::make('description')->required()->columnSpanFull(),
 Select::make('skill')->options(['reading'=>'Reading','listening'=>'Listening','writing'=>'Writing','speaking'=>'Speaking'])->required(),Select::make('test_type')->options(['academic'=>'Academic','general'=>'General Training'])->required(),
 TextInput::make('duration_seconds')->numeric()->minValue(60)->maxValue(14400)->required()->default(1200),Select::make('collection_id')->relationship('collection','title')->preload(),
 Select::make('status')->options(['draft'=>'Draft','review'=>'Ready for review','archived'=>'Archived'])->required()->default('draft')->helperText('Use Publish to create an immutable version. Saving a published test creates a draft for review.'),Toggle::make('premium'),Toggle::make('demo')->default(true),Toggle::make('sample'),TagsInput::make('tags'),
 Hidden::make('revision')->default(0),
 Repeater::make('draft.sections')->label('Sections / parts')->schema([
 TextInput::make('id')->label('Stable section ID')->required()->regex('/^[a-zA-Z0-9_-]+$/'),TextInput::make('title')->required(),Textarea::make('instructions')->required()->default('Follow the instructions for each question.')->columnSpanFull(),Textarea::make('passage')->rows(10)->columnSpanFull(),Select::make('audio_asset_id')->options(fn()=>\App\Models\MediaAsset::where('kind','audio')->pluck('name','id'))->searchable(),Textarea::make('transcript')->rows(5)->columnSpanFull(),
 Repeater::make('questions')->schema([
 TextInput::make('id')->label('Stable question ID')->required()->regex('/^[a-zA-Z0-9_-]+$/'),Select::make('type')->options(array_combine(ContentService::TYPES,array_map(fn($t)=>ucwords(str_replace('_',' ',$t)),ContentService::TYPES)))->required()->live(),Textarea::make('prompt')->required()->columnSpanFull(),TagsInput::make('options')->helperText('Press Enter after each option. Used by choice and matching questions.')->columnSpanFull(),TagsInput::make('accepted')->helperText('Exact alternatives; for multiple choice, enter every required answer.')->columnSpanFull(),TextInput::make('select_count')->numeric()->minValue(2)->maxValue(10),TextInput::make('max_words')->numeric()->minValue(0)->maxValue(20),TextInput::make('max_numbers')->numeric()->minValue(0)->maxValue(5)->default(0),TextInput::make('min_words')->numeric(),Textarea::make('diagram')->label('Diagram / map description')->helperText('Plain-text spatial layout with labelled positions; an accessible alternative to drag-and-drop.')->columnSpanFull(),TextInput::make('task')->numeric()->minValue(1)->maxValue(2),TextInput::make('preparation_seconds')->numeric(),TextInput::make('response_seconds')->numeric(),Textarea::make('explanation')->columnSpanFull(),TextInput::make('reference')->columnSpanFull()
 ])->columns(2)->collapsible()->cloneable()->reorderableWithButtons()->itemLabel(fn(array $state)=>($state['id']??'Question').' · '.substr($state['prompt']??'',0,60))->columnSpanFull()
 ])->columns(2)->collapsible()->cloneable()->reorderableWithButtons()->itemLabel(fn(array $state)=>$state['title']??'Section')->columnSpanFull(),
 Toggle::make('draft.answer_keys_reviewed')->label('I have checked every answer and explanation')->default(false),Toggle::make('draft.scoring.reviewed')->label('Band conversion reviewed for this exact test')->default(false),TextInput::make('draft.scoring.source')->label('Scoring review / source reference')->columnSpanFull(),Repeater::make('draft.scoring.thresholds')->schema([TextInput::make('min')->label('Minimum raw mark')->numeric()->required(),TextInput::make('band')->numeric()->required()])->columns(2)->reorderableWithButtons()->columnSpanFull()
])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('title')->searchable()->wrap(),TextColumn::make('skill')->badge(),TextColumn::make('test_type'),TextColumn::make('status')->badge(),TextColumn::make('published_version')->label('Version'),IconColumn::make('premium')->boolean()])->filters([SelectFilter::make('skill')->options(['reading'=>'Reading','listening'=>'Listening','writing'=>'Writing','speaking'=>'Speaking']),SelectFilter::make('status')->options(['draft'=>'Draft','review'=>'Review','published'=>'Published','archived'=>'Archived'])])->recordActions([EditAction::make()->fillForm(fn(Exam $record)=>array_replace($record->toArray(),['status'=>$record->status==='published'?'draft':$record->status]))->using(function(Exam $record,array $data): Exam { return \Illuminate\Support\Facades\DB::transaction(function()use($record,$data){$locked=Exam::lockForUpdate()->findOrFail($record->id);abort_unless($locked->revision===(int)$data['revision'],409,'Another administrator saved this test. Reload before editing.');$data['revision']=$locked->revision+1;$locked->update($data);return $locked;});}),
 Action::make('preview')->url(fn(Exam $record)=>url('/admin/preview/'.$record->id))->openUrlInNewTab(),
 Action::make('publish')->color('success')->requiresConfirmation()->modalDescription('Validate this content and publish a new immutable version. Existing attempts retain their previous version.')->action(function(Exam $record){app(ContentService::class)->publish($record,auth()->user());Notification::make()->title('New version published')->success()->send();}),
 Action::make('duplicate')->action(function(Exam $record){$copy=$record->replicate(['published_version']);$copy->slug.='-copy-'.strtolower(\Illuminate\Support\Str::random(5));$copy->title.=' (copy)';$copy->status='draft';$copy->save();Audit::record('content.duplicated',$copy);}),
 Action::make('export')->url(fn(Exam $record)=>url('/api/v1/admin/tests/'.$record->id.'/export'))
])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageExams::route('/')]; }
 
}
