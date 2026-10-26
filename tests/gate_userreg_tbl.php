<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.connections.gate_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],'database.default'=>'gate_test']);
DB::purge('gate_test');
DB::statement('CREATE TABLE tbl_userreg (USER_ADDR INTEGER NULL, description TEXT, created_at TEXT)');
$command = new class extends App\Console\Commands\GateServerCommand {
    protected function appendGateLog(string $line): void {}
};
$command->setOutput(new Illuminate\Console\OutputStyle(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\BufferedOutput));
$method = new ReflectionMethod($command, 'processIncomingData');
$socket = fopen('php://memory', 'r+');
try {
    foreach ([2,4] as $id) {
        $method->invoke($command, $socket, json_encode(['ret'=>'setuserinfo','enrollid'=>$id,'backupnum'=>50,'result'=>false,'reason'=>12,'msg'=>'fac']));
    }
    $rows = DB::table('tbl_userreg')->orderBy('USER_ADDR')->get();
    if ($rows->count() !== 2 || $rows[0]->USER_ADDR !== 2 || $rows[1]->USER_ADDR !== 4) throw new RuntimeException('FAIL: identity/count');
    if ($rows[0]->description !== 'fac face is double [BELUM DIVERIFIKASI]') throw new RuntimeException('FAIL: reason code not mapped: '.$rows[0]->description);
    foreach ([['ret'=>'setuserinfo','result'=>true],['ret'=>'setuserlock','result'=>false]] as $payload) $method->invoke($command, $socket, json_encode($payload));
    if (DB::table('tbl_userreg')->count() !== 2) throw new RuntimeException('FAIL: unrelated/success stored');
    DB::statement('DROP TABLE tbl_userreg');
    $method->invoke($command, $socket, json_encode(['ret'=>'setuserinfo','enrollid'=>2,'result'=>false,'msg'=>'fac']));
    echo "PASS: inbound failures saved for 2/4; reason retained; success/unrelated ignored; DB failure survives\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
} finally { fclose($socket); }
