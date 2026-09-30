<?php
namespace App\Filament\Resources\Collections;
use App\Models\Collection;
use App\Filament\Resources\Collections\Pages\ManageCollections;
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

class CollectionResource extends Resource {
 protected static ?string $model = Collection::class;
 protected static ?string $recordTitleAttribute = 'title';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('title')->required(),TextInput::make('slug')->required()->unique(ignoreRecord:true),Textarea::make('description')->columnSpanFull(),Toggle::make('is_mock')->label('Mock collection'),TagsInput::make('sequence')->label('Ordered test IDs')->helperText('The order shown to students when completing the bundle.')])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('title')->searchable(),TextColumn::make('slug'),IconColumn::make('is_mock')->boolean()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageCollections::route('/')]; }
 
}
