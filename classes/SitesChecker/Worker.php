<?php

namespace SitesChecker;

/**
 * Description of Worker
 *
 */
class Worker extends \GearmanWorker
{
    
    protected $curl_resource = null;

    public function __construct()
    {
        parent::__construct();
        $this->addServer();
        $this->addFunction('checkSite', array($this, 'checkSite'));
    }

    public function checkSite($job)
    {
        echo $job->workload() . "\n";

        return true;
    }
    
    protected function initCurlSession()
    {

        $resource = curl_init();

        curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($resource, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($resource, CURLOPT_LOW_SPEED_TIME, self::TIMEOUT);
        curl_setopt($resource, CURLOPT_FAILONERROR, true);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, 1);

        $this->curl_resource = $resource;

    }
}
