<?php
namespace App\Filament\Resources\MediaAssets;
use App\Models\MediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ManageMediaAssets;
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

class MediaAssetResource extends Resource {
 protected static ?string $model = MediaAsset::class;
 protected static ?string $recordTitleAttribute = 'name';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('name')->required(),Select::make('kind')->options(['audio'=>'Audio','image'=>'Image'])->required(),FileUpload::make('path')->disk('local')->directory('authored-media')->visibility('private')->acceptedFileTypes(['audio/mpeg','audio/wav','audio/x-wav','audio/ogg','image/png','image/jpeg','image/webp'])->maxSize(51200)->required()->columnSpanFull(),Hidden::make('disk')->default('local'),Textarea::make('description')->required()->label('Accessible description')->columnSpanFull(),Textarea::make('transcript')->rows(8)->columnSpanFull(),TextInput::make('license')->required()->default('Original owner-supplied content')])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable(),TextColumn::make('kind')->badge(),TextColumn::make('license')->limit(50),TextColumn::make('created_at')->dateTime()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageMediaAssets::route('/')]; }
 
}
