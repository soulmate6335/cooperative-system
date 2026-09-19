<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('sessions')) {
            return;
        }

        $orphanedSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'sessions.user_id');
            })
            ->count();

        if ($orphanedSessions > 0) {
            throw new RuntimeException('Cannot add the sessions user foreign key while orphaned sessions exist.');
        }

        $constraintExists = DB::selectOne("select 1 from pg_constraint where conname = 'sessions_user_id_foreign'");

        if (! $constraintExists) {
            DB::statement('alter table sessions add constraint sessions_user_id_foreign foreign key (user_id) references users (id) on delete set null');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('sessions')) {
            DB::statement('alter table sessions drop constraint if exists sessions_user_id_foreign');
        }
    }
};
