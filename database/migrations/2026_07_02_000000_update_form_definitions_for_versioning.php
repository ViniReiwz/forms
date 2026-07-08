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
        $hadVersion = Schema::hasColumn('form_definitions', 'version');
        $hadStatus = Schema::hasColumn('form_definitions', 'status');

        if (!$hadVersion || !$hadStatus) {
            $this->dropLegacyNameUniqueIndex();
            $this->addVersioningColumns($hadVersion, $hadStatus);
        }

        $this->normalizeVersionValues($hadVersion);
        $this->assertValidVersions();
        $this->normalizeStatusValues();
        $this->assertValidStatuses();
        $this->assertNoDuplicateNameVersion();
        $this->disableDuplicateActiveDefinitions();
        $this->ensureRequiredColumnsAreNotNullable();
        $this->ensureIndexes();
        $this->ensureActiveNameConstraint();
    }

    private function dropLegacyNameUniqueIndex(): void
    {
        try {
            Schema::table('form_definitions', function (Blueprint $table) {
                $table->dropUnique('form_definitions_name_unique');
            });
        } catch (\Throwable $e) {
            // The package may already have been migrated by an intermediate version.
        }
    }

    private function addVersioningColumns(bool $hadVersion, bool $hadStatus): void
    {
        Schema::table('form_definitions', function (Blueprint $table) use ($hadVersion, $hadStatus) {
            if (!$hadVersion) {
                $table->unsignedInteger('version')->default(1)->after('name');
            }

            if (!$hadStatus) {
                $table->string('status')->default('active')->after('version');
            }
        });
    }

    private function normalizeVersionValues(bool $hadVersion): void
    {
        if (!Schema::hasColumn('form_definitions', 'version')) {
            return;
        }

        if ($hadVersion) {
            $this->assertNoAmbiguousNullVersions();
        }

        DB::table('form_definitions')
            ->whereNull('version')
            ->update(['version' => 1]);
    }

    private function assertNoAmbiguousNullVersions(): void
    {
        $duplicateNullNames = DB::table('form_definitions')
            ->select('name')
            ->whereNull('version')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicateNullNames->isNotEmpty()) {
            throw new RuntimeException(
                'Nao foi possivel migrar form_definitions porque existem multiplas versoes NULL para os names: '
                . $duplicateNullNames->implode(', ')
            );
        }

        $conflictingNullNames = DB::table('form_definitions as null_versions')
            ->select('null_versions.name')
            ->whereNull('null_versions.version')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('form_definitions as version_one')
                    ->whereColumn('version_one.name', 'null_versions.name')
                    ->where('version_one.version', 1);
            })
            ->pluck('name');

        if ($conflictingNullNames->isNotEmpty()) {
            throw new RuntimeException(
                'Nao foi possivel migrar form_definitions porque version NULL conflita com version 1 para os names: '
                . $conflictingNullNames->implode(', ')
            );
        }
    }

    private function assertValidVersions(): void
    {
        $invalidIds = DB::table('form_definitions')
            ->whereNull('version')
            ->orWhere('version', '<', 1)
            ->pluck('id');

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException(
                'Nao foi possivel migrar form_definitions porque existem versions invalidas nos ids: '
                . $invalidIds->implode(', ')
            );
        }
    }

    private function normalizeStatusValues(): void
    {
        if (!Schema::hasColumn('form_definitions', 'status')) {
            return;
        }

        DB::table('form_definitions')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    private function assertValidStatuses(): void
    {
        $invalidIds = DB::table('form_definitions')
            ->whereNotIn('status', ['draft', 'active', 'disabled'])
            ->pluck('id');

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException(
                'Nao foi possivel migrar form_definitions porque existem statuses invalidos nos ids: '
                . $invalidIds->implode(', ')
            );
        }
    }

    private function assertNoDuplicateNameVersion(): void
    {
        $duplicates = DB::table('form_definitions')
            ->select('name', 'version')
            ->groupBy('name', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $duplicatedKeys = $duplicates
            ->map(fn ($definition) => "{$definition->name}:{$definition->version}")
            ->implode(', ');

        throw new RuntimeException(
            'Nao foi possivel migrar form_definitions porque existem pares name + version duplicados: '
            . $duplicatedKeys
        );
    }

    private function disableDuplicateActiveDefinitions(): void
    {
        $namesWithMultipleActiveVersions = DB::table('form_definitions')
            ->select('name')
            ->where('status', 'active')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($namesWithMultipleActiveVersions as $name) {
            $activeId = DB::table('form_definitions')
                ->where('name', $name)
                ->where('status', 'active')
                ->orderByDesc('version')
                ->value('id');

            DB::table('form_definitions')
                ->where('name', $name)
                ->where('status', 'active')
                ->where('id', '!=', $activeId)
                ->update(['status' => 'disabled']);
        }
    }

    private function ensureRequiredColumnsAreNotNullable(): void
    {
        Schema::table('form_definitions', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->nullable(false)->change();
            $table->string('status')->default('active')->nullable(false)->change();
        });
    }

    private function ensureIndexes(): void
    {
        Schema::table('form_definitions', function (Blueprint $table) {
            if (!Schema::hasIndex('form_definitions', ['name', 'version'], 'unique')) {
                $table->unique(['name', 'version']);
            }

            if (!Schema::hasIndex('form_definitions', 'form_definitions_name_index')) {
                $table->index('name');
            }

            if (!Schema::hasIndex('form_definitions', 'form_definitions_group_index')) {
                $table->index('group');
            }

            if (!Schema::hasIndex('form_definitions', 'form_definitions_status_index')) {
                $table->index('status');
            }
        });
    }

    private function ensureActiveNameConstraint(): void
    {
        if ($this->isMysql()) {
            $this->ensureMysqlActiveNameConstraint();
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS " . self::ACTIVE_UNIQUE_INDEX
            . " ON form_definitions (name) WHERE status = 'active'"
        );
    }

    private function ensureMysqlActiveNameConstraint(): void
    {
        if (!Schema::hasColumn('form_definitions', 'active_name')) {
            Schema::table('form_definitions', function (Blueprint $table) {
                $table->string('active_name')->nullable()->after('status');
            });
        }

        DB::table('form_definitions')->update([
            'active_name' => DB::raw("CASE WHEN status = 'active' THEN name ELSE NULL END"),
        ]);

        Schema::table('form_definitions', function (Blueprint $table) {
            if (!Schema::hasIndex('form_definitions', self::ACTIVE_UNIQUE_INDEX)) {
                $table->unique('active_name', self::ACTIVE_UNIQUE_INDEX);
            }
        });

        DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_INSERT_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_UPDATE_TRIGGER);

        DB::unprepared(
            'CREATE TRIGGER ' . self::ACTIVE_INSERT_TRIGGER . ' BEFORE INSERT ON form_definitions '
            . "FOR EACH ROW SET NEW.active_name = CASE WHEN NEW.status = 'active' THEN NEW.name ELSE NULL END"
        );

        DB::unprepared(
            'CREATE TRIGGER ' . self::ACTIVE_UPDATE_TRIGGER . ' BEFORE UPDATE ON form_definitions '
            . "FOR EACH ROW SET NEW.active_name = CASE WHEN NEW.status = 'active' THEN NEW.name ELSE NULL END"
        );
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public function down(): void
    {
        $this->dropActiveNameConstraint();

        Schema::table('form_definitions', function (Blueprint $table) {
            if (Schema::hasIndex('form_definitions', ['name', 'version'], 'unique')) {
                $table->dropUnique(['name', 'version']);
            }

            if (Schema::hasIndex('form_definitions', 'form_definitions_name_index')) {
                $table->dropIndex('form_definitions_name_index');
            }

            if (Schema::hasIndex('form_definitions', 'form_definitions_group_index')) {
                $table->dropIndex('form_definitions_group_index');
            }

            if (Schema::hasIndex('form_definitions', 'form_definitions_status_index')) {
                $table->dropIndex('form_definitions_status_index');
            }
        });

        Schema::table('form_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('form_definitions', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('form_definitions', 'version')) {
                $table->dropColumn('version');
            }
        });

        Schema::table('form_definitions', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    private function dropActiveNameConstraint(): void
    {
        if ($this->isMysql()) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_INSERT_TRIGGER);
            DB::unprepared('DROP TRIGGER IF EXISTS ' . self::ACTIVE_UPDATE_TRIGGER);

            if (Schema::hasColumn('form_definitions', 'active_name')) {
                Schema::table('form_definitions', function (Blueprint $table) {
                    if (Schema::hasIndex('form_definitions', self::ACTIVE_UNIQUE_INDEX)) {
                        $table->dropUnique(self::ACTIVE_UNIQUE_INDEX);
                    }

                    $table->dropColumn('active_name');
                });
            }

            return;
        }

        DB::statement('DROP INDEX IF EXISTS ' . self::ACTIVE_UNIQUE_INDEX);
    }
};
