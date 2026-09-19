<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $usersIdType = DB::selectOne("select udt_name from information_schema.columns where table_schema = current_schema() and table_name = 'users' and column_name = 'id'")->udt_name;

        if ($usersIdType === 'uuid') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('ALTER TABLE users ADD COLUMN IF NOT EXISTS uuid_id uuid DEFAULT gen_random_uuid()');
        DB::statement('UPDATE users SET uuid_id = gen_random_uuid() WHERE uuid_id IS NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN uuid_id SET NOT NULL');

        DB::statement('ALTER TABLE sessions ADD COLUMN IF NOT EXISTS uuid_user_id uuid');
        DB::statement('UPDATE sessions SET uuid_user_id = users.uuid_id FROM users WHERE sessions.user_id = users.id');

        $tokenableIdType = DB::selectOne("select udt_name from information_schema.columns where table_schema = current_schema() and table_name = 'personal_access_tokens' and column_name = 'tokenable_id'")->udt_name;

        if (! in_array($tokenableIdType, ['int2', 'int4', 'int8'], true)) {
            throw new RuntimeException('Legacy users.id requires an integer personal_access_tokens.tokenable_id; found '.$tokenableIdType.'.');
        }

        DB::statement('ALTER TABLE personal_access_tokens ADD COLUMN IF NOT EXISTS uuid_tokenable_id uuid');
        DB::statement('UPDATE personal_access_tokens SET uuid_tokenable_id = users.uuid_id FROM users WHERE personal_access_tokens.tokenable_type = ? AND personal_access_tokens.tokenable_id = users.id', [User::class]);

        DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tokenable_type_tokenable_id_index');
        DB::statement('ALTER TABLE personal_access_tokens DROP COLUMN tokenable_id');
        DB::statement('ALTER TABLE personal_access_tokens RENAME COLUMN uuid_tokenable_id TO tokenable_id');
        DB::statement('CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens (tokenable_type, tokenable_id)');

        DB::statement('ALTER TABLE sessions DROP COLUMN user_id');
        DB::statement('ALTER TABLE sessions RENAME COLUMN uuid_user_id TO user_id');
        DB::statement('CREATE INDEX sessions_user_id_index ON sessions (user_id)');
        DB::statement('ALTER TABLE sessions ADD CONSTRAINT sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_pkey');
        DB::statement('ALTER TABLE users DROP COLUMN id');
        DB::statement('ALTER TABLE users RENAME COLUMN uuid_id TO id');
        DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('pending')->index();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The identity UUID migration is irreversible.');
    }
};
