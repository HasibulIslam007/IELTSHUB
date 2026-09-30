<?php
namespace App\Filament\Resources\Plans;
use App\Models\Plan;
use App\Filament\Resources\Plans\Pages\ManagePlans;
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

class PlanResource extends Resource {
 protected static ?string $model = Plan::class;
 protected static ?string $recordTitleAttribute = 'name';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('name')->required(),Toggle::make('active')->default(true),Textarea::make('description')->required()->columnSpanFull()])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable(),TextColumn::make('description')->limit(70),IconColumn::make('active')->boolean()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManagePlans::route('/')]; }
 
}
