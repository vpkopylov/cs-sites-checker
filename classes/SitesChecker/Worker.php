<?php

namespace SitesChecker;


class Worker extends \GearmanWorker
{

    const TIMEOUT = 15;
    
    const ERROR_OK = 0;
    const ERROR_NETWORK = 1;
    const ERROR_NOTCART = 2;
    
    protected $known_products = array(
        'CS-Cart',
        'Multi-Vendor',
        'NettXpress'
    );
    
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
        echo "checking site $this->site\n";
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
            if ($this->isKnownProduct($text)) {
                $this->error_code = self::ERROR_OK;
                $this->product_version = strip_tags($text);
            } else {
                $this->error_code = self::ERROR_NOTCART;
            }
        }
    }
    
    protected function isKnownProduct($text)
    {
        foreach ($this->known_products as $product) {
            if (mb_stripos($text, $product) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function checkByJsText() 
    {
        $curl_result = $this->makeRequest($this->site);

        if ($curl_result !== false) {
            $text = trim($curl_result);
            $is_cart = mb_stripos($text, '.runCart') !== false;
            if ($is_cart) {
                $this->error_code = self::ERROR_OK;
                $this->product_version = 'Unknown version';
            } else {
                $this->error_code = self::ERROR_NOTCART;
            }
        }        
    }    

    protected function makeRequest($url)
    {

        $curl_resource = $this->initCurlSession();

        curl_setopt($curl_resource, CURLOPT_URL, $url);

        $result = curl_exec($curl_resource);

        if ($result === false) {
            $this->error_code = self::ERROR_NETWORK;
            $this->error_message = curl_error($curl_resource);
        }

        curl_close($curl_resource);

        return $result;
    }

    protected function initCurlSession()
    {
        $resource = curl_init();

        curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($resource, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        curl_setopt($resource, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($resource, CURLOPT_LOW_SPEED_TIME, self::TIMEOUT);
        curl_setopt($resource, CURLOPT_FAILONERROR, true);
        curl_setopt($resource, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($resource, CURLOPT_MAXREDIRS, 2);
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
