<?php

namespace SitesChecker;

/**
 * Description of Worker
 *
 */
class Worker extends \GearmanWorker
{

    const TIMEOUT = 30;
    const ERROR_OK = 0;
    const ERROR_NETWORK = 1;
    const ERROR_NOTCS = 1;
    
    protected $site = null;

    protected $error_code = null;
    protected $product_version = null;
    protected $error_message = null;

    public function __construct()
    {
        parent::__construct();
        $this->addServer();
        $this->addFunction('checkSite', array($this, 'checkSite'));
    }

    public function checkSite($job)
    {
        $this->site = $job->workload();
        $this->product_version = null;
        $this->error_code = self::ERROR_OK;
        $this->error_message = null;
        $this->checkByVersionReq();

        return $this->serializeResult();
    }

    protected function checkByVersionReq()
    {

        $result = false;
        
        $curl_resource = $this->initCurlSession();

        $url = $this->site . '/?version';
        curl_setopt($curl_resource, CURLOPT_URL, $url);

        $curl_result = curl_exec($curl_resource);

        if ($curl_result === false) {
            $this->error_code = self::ERROR_NETWORK;
            $this->error_message = curl_error($curl_resource);
        } else {
            $result = $this->checkVersionText($curl_result);
        }

        curl_close($curl_resource);
        
        return $result;
    }

    protected function initCurlSession()
    {

        $resource = curl_init();

        curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($resource, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($resource, CURLOPT_LOW_SPEED_TIME, self::TIMEOUT);
        curl_setopt($resource, CURLOPT_FAILONERROR, true);
        curl_setopt($resource, CURLOPT_RETURNTRANSFER, 1);

        return $resource;
    }

    protected function checkVersionText($text)
    {        
        $text = trim($text);
        $result = mb_stripos($text, 'CS-Cart') === 0 || mb_stripos($text, 'Multi-Vendor') === 0;
        if ($result) {
            $this->product_version = strip_tags($text);
        } else {
            $this->error_code = self::ERROR_NOTCS;
        }
        return $result;
    }

    protected function serializeResult()
    {

        $result = new \stdClass();
        $result->site = $this->site;
        $result->error_code = $this->error_code;
        $result->product_version = $this->product_version;
        $result->error_message = $this->error_message;

        return json_encode($result);
    }
}
