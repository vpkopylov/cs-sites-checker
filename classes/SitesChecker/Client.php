<?php

namespace SitesChecker;

/**
 * Description of Client
 *
 */
class Client extends \GearmanClient
{
    const READ_BUFF_SIZE = 1000;

    protected $input_file_name;
    
    protected $output_file_name;
    
    protected $input_file_handle;
    
    protected $host_black_list = array('none', 'null', 'localhost');

    public function __construct($input_file_name, $output_file_name)
    {
        $this->input_file_name = $input_file_name;
        $this->output_file_name = $output_file_name;
        
        parent::__construct();

        $this->addServer();
    }

    public function createTasks()
    {
        $this->openInputFile();
        
        while (($data = fgets($this->input_file_handle, self::READ_BUFF_SIZE)) !== false) {        
            $this->addTasksFromRow($data);
        }
            
        $this->closeInputFile();
    }

    protected function openInputFile()
    {
        $this->input_file_handle = @fopen($this->input_file_name, 'r');
        if (!$this->input_file_handle) {
            throw new Exception('Unable to open ' . $this->input_file_name);
        }
        //FIXME: Add an option not to skip the first line
        //Skip header
        fgets($this->input_file_handle, self::READ_BUFF_SIZE);
    }
    
    protected function addTasksFromRow($data)
    {
        $input_sites_list = explode(',',$data);

        $task_sites_list = array();

        foreach ($input_sites_list as $site) {

            $site = $this->normalizeUrl($site);
            if (!is_null($site) && !in_array($site, $task_sites_list)) {
                $task_sites_list[] = $site;
            }
        }

        if (count($task_sites_list) > 0) {
            $task_sites_list = $this->removeWwwDuplicates($task_sites_list);
            
        }
        
        foreach ($input_sites_list as $site) {
                $this->addTask('checkSite', $site);
        }         
    }

    protected function normalizeUrl($site)
    {
        $result = rtrim(trim($site), '/?');

        if (empty($result)) {
            return null;
        }

        $is_url = $this->isValidUrl($result);
        $is_host = !$is_url && $this->isValidHost($result);
        
        if (!$is_url && !$is_host) {
            //FIXME: remove direct output in this class
            echo "$result  is not a valid url or domain name\n";
            return null;
        }
        
        if ($is_host) {
            $result = 'http://' . $result;
        }

        $host = parse_url($result, PHP_URL_HOST);
        if (in_array(mb_strtolower($host), $this->host_black_list)) {
            echo "$result's  host is in the black list\n";
            return null;
        }
        
        return $result;
    }
    
    protected function isValidUrl($url) 
    {
        return preg_match('@https?://([^\s/?\.#]+\.?)+(/[^\s]*)?$@iS', $url);
    }
    
    protected function isValidHost($name)
    {
        return preg_match('@([^\s/?\.#]+\.?)+(/[^\s]*)?$@iS', $name);
    }
    
    protected function removeWwwDuplicates($sites_list)
    {
        foreach ($sites_list as $key => $site) {
            $host = parse_url($site, PHP_URL_HOST);
            if (mb_strpos($host, 'www.') === 0) {
                continue;
            }
            $www_host = 'www.' . $host;
            $www_site = str_replace($host, $www_host, $site);
            if (in_array($www_site, $sites_list)) {
                echo "$site is a duplicate in  " . implode(',', $sites_list) . "\n";
                unset($sites_list[$key]);
            }
        }
        return $sites_list;
    } 
    
    

    protected function closeInputFile()
    {
        fclose($this->input_file_handle);
    }    
}
