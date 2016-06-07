<?php

namespace SitesChecker;

/**
 * Description of Worker
 *
 */
class Worker extends \GearmanWorker
{

    protected $worker = null;

    public function __construct()
    {
        parent::__construct();     
        $this->addServer();
        $this->addFunction('checkSite', array($this, 'checkSite'));        
    }


    public function checkSite($job)
    {
        echo $job->workload();
        return true;
    }
}
