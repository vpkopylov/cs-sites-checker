<?php

namespace SitesChecker;

/**
 * Description of WorkerApllication
 *
 */
class WorkerApplication extends AConsoleApplication
{

    public function execute()
    {
        $worker = new Worker();
        print "Waiting for job...\n";
        while ($worker->work()) {
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                echo "Return code: " . $worker->returnCode() . "\n";
                break;
            }
        }
    }
    public function parseOptions()
    {
        
    }
}
