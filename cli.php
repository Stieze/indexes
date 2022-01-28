<?php
require __DIR__.'/vendor/autoload.php';

use App\Command\UpdateVPZ;
use App\Helper\Env;
use Symfony\Component\Console\Application;
(new Env(__DIR__.'/.env'))->load();

$command = new UpdateVPZ();

$application = new Application();
$application->add($command);

try {
    $application->run();
} catch (Exception $e) {
    echo $e;
}