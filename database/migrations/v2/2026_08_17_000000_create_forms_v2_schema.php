<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_UNIQUE_INDEX = 'form_definitions_active_name_unique';

    private const ACTIVE_INSERT_TRIGGER = 'form_definitions_active_name_bi';

    private const ACTIVE_UPDATE_TRIGGER = 'form_definitions_active_name_bu';

    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('active');
            $table->string('group');
            $table->string('description')->nullable();
            $table->json('fields')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version'], 'form_definitions_name_version_unique');
            $table->index('name', 'form_definitions_name_index');
            $table->index('group', 'form_definitions_group_index');
            $table->index('status', 'form_definitions_status_index');
        });

        $this->createActiveDefinitionConstraint();

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_definition_id')
                ->constrained('form_definitions')
                ->cascadeOnDelete();
            $table->bigInteger('user_id')->nullable();
            $table->string('key');
            $table->json('data');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->activitySchema()->create($this->activityTable(), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->string('event')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        $this->dropActiveDefinitionConstraint();

        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
        $this->activitySchema()->dropIfExists($this->activityTable());
    }

    private function createActiveDefinitionConstraint(): void
    {
        if ($this->isMysql()) {
            Schema::table('form_definitions', function (Blueprint $table): void {
                $table->string('active_name')->nullable()->after('status');
                $table->unique('active_name', self::ACTIVE_UNIQUE_INDEX);
            });

            DB::unprepared(
                'CREATE TRIGGER ' . self::ACTIVE_INSERT_TRIGGER . ' BEFORE INSERT ON form_definitions '
                . "FOR EACH ROW SET NEW.active_name = CASE WHEN NEW.status = 'active' THEN NEW.name ELSE NULL END"
            );

            DB::unprepared(
                'CREATE TRIGGER ' . self::ACTIVE_UPDATE_TRIGGER . ' BEFORE UPDATE ON form_definitions '
                . "FOR EACH ROW SET NEW.active_name = CASE WHEN NEW.status = 'active' THEN NEW.name ELSE NULL END"
            );

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS ' . self::ACTIVE_UNIQUE_INDEX
            . " ON form_definitions (name) WHERE status = 'active'"
        );
    }

    private function dropActiveDefinitionConstraint(): void
    {
        if ($this->isMysql()) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_INSERT_TRIGGER);
            DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_UPDATE_TRIGGER);

            Schema::table('form_definitions', function (Blueprint $table): void {
                $table->dropUnique(self::ACTIVE_UNIQUE_INDEX);
                $table->dropColumn('active_name');
            });

            return;
        }

        DB::statement('DROP INDEX IF EXISTS ' . self::ACTIVE_UNIQUE_INDEX);
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function activitySchema(): \Illuminate\Database\Schema\Builder
    {
        $connection = config('activitylog.database_connection') ?: config('database.default');

        return Schema::connection($connection);
    }

    private function activityTable(): string
    {
        return (string) config('activitylog.table_name', 'activity_log');
    }
};
