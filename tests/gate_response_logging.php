<?php
// Isolated regression test: no DB access, no hardware, temporary log directory.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = sys_get_temp_dir().'/gate-log-test-'.bin2hex(random_bytes(5));
mkdir($dir.'/logs', 0777, true);
$app->useStoragePath($dir);
$command = new App\Console\Commands\GateServerCommand;
$command->setOutput(new Illuminate\Console\OutputStyle(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\BufferedOutput));
$method = new ReflectionMethod($command, 'processIncomingData');
$socket = fopen('php://memory', 'r+');
try {
    foreach (['face is double,id=2', 'face too small'] as $message) {
        $method->invoke($command, $socket, json_encode(['ret'=>'setuserinfo','result'=>false,'enrollid'=>12024,'reason'=>$message,'record'=>'BIOMETRIC_SECRET','nested'=>['token'=>'TOKEN_SECRET']]));
    }
    $file = $dir.'/logs/gate.log';
    if (!is_file($file)) throw new RuntimeException('FAIL: gate.log was not written');
    $text = file_get_contents($file);
    foreach (['face is double,id=2','face too small','12024','setuserinfo'] as $expected) {
        if (!str_contains($text, $expected)) throw new RuntimeException('FAIL: missing '.$expected);
    }
    foreach (['BIOMETRIC_SECRET','TOKEN_SECRET'] as $secret) {
        if (str_contains($text, $secret)) throw new RuntimeException('FAIL: sensitive payload leaked');
    }
    echo "PASS: inbound errors persisted; identity retained; nested secrets redacted\n";
} finally {
    fclose($socket);
    foreach (glob($dir.'/logs/*') as $file) unlink($file);
    rmdir($dir.'/logs');
    rmdir($dir);
}
