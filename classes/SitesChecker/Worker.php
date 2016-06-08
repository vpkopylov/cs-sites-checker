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
    const ERROR_NOTCART = 2;
    
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
        $this->error_code = null;
        $this->error_message = null;
        $this->checkByVersionReq();
        if ($this->error_code == self::ERROR_NOTCART) {
            $this->checkByJsText();
        }
        
        return $this->serializeResult();
    }

    protected function checkByVersionReq()
    {
        $url = $this->site . '/?version';
        $curl_result = $this->makeRequest($url);

        if ($curl_result !== false) {
            $text = trim($curl_result);
            $is_cart = mb_stripos($text, 'CS-Cart') === 0 || mb_stripos($text, 'Multi-Vendor') === 0;
            if ($is_cart) {
                $this->error_code = self::ERROR_OK;
                $this->product_version = strip_tags($text);
            } else {
                $this->error_code = self::ERROR_NOTCART;
            }
        }
    }
    
    protected function checkByJsText() 
    {
        $curl_result = $this->makeRequest($this->site);

        if ($curl_result !== false) {
            $text = trim($curl_result);
            $is_cart = mb_stripos($text, '.runCart') !== false;
            if ($is_cart) {
                $this->error_code = self::ERROR_OK;
                $this->product_version = 'Unknown';
            } else {
                $this->error_code = self::ERROR_NOTCART;
            }
        }        
    }    

    protected function makeRequest($url)
    {
        
        $curl_resource = $this->initCurlSession();

        curl_setopt($curl_resource, CURLOPT_URL, $url);

        $curl_result = curl_exec($curl_resource);

        if ($curl_result === false) {
            $this->error_code = self::ERROR_NETWORK;
            $this->error_message = curl_error($curl_resource);
        }

        curl_close($curl_resource);
        
        return $curl_result;        
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
