<?php

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlLockingContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_row_lock_blocks_competing_inventory_lock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL locking contract runs only in the MySQL CI profile.');
        }

        $type = RoomType::query()->create([
            'code'=>'lock-test',
            'name'=>'Lock Test Room',
            'is_active'=>true,
        ]);

        $probeConfig = config('database.connections.mysql');
        config(['database.connections.mysql_lock_probe' => $probeConfig]);
        DB::purge('mysql_lock_probe');

        $primary = DB::connection('mysql');
        $probe = DB::connection('mysql_lock_probe');
        $blocked = false;

        try {
            $primary->beginTransaction();
            $primary->table('room_types')->where('id', $type->id)->lockForUpdate()->first();

            $probe->statement('SET SESSION innodb_lock_wait_timeout=1');
            $probe->beginTransaction();

            try {
                $probe->table('room_types')->where('id', $type->id)->lockForUpdate()->first();
            } catch (QueryException) {
                $blocked = true;
            }
        } finally {
            if ($probe->transactionLevel() > 0) $probe->rollBack();
            if ($primary->transactionLevel() > 0) $primary->rollBack();
            DB::disconnect('mysql_lock_probe');
        }

        $this->assertTrue($blocked, 'A competing MySQL FOR UPDATE lock should not bypass the active inventory lock.');
    }
}
