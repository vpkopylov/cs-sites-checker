<?php

namespace SitesChecker;

use SitesChecker\Worker;


class Client extends \GearmanClient
{
    const READ_BUFF_SIZE = 1000;

    protected $input_file_name;
    
    protected $output_file_name;
    
    protected $input_file_handle;
    
    protected $output_file_handle;
    
    protected $delim = ',';


    protected $column_names = array(
        'site_url',
        'version'
    );


    protected $host_black_list = array(
        'none',
        'null',
        'localhost',
        'tbd',
        'tbn'
    );
    
    protected $completed_callback = null;
    
    protected $tasks_total = null;
    
    protected $tasks_left = null;

    public function __construct($input_file_name, $output_file_name)
    {
        $this->input_file_name = $input_file_name;
        $this->output_file_name = $output_file_name;
        
        parent::__construct();

        $this->addServer();
    }
    
    public function setCompleteCallback($callback) 
    {
        $this->completed_callback = $callback;
        
        parent::setCompleteCallback(array($this, 'onTaskComplete'));
        
    }
    
    public function onTaskComplete($task)
    {
        $result_data = json_decode($task->data());
        if ($result_data->error_code == Worker::ERROR_OK) {
            $put_data = array($result_data->site, $result_data->product_version);
            fputs($this->output_file_handle, implode($this->delim, $put_data) . "\n");
        }
        
        $this->tasks_left--;
        
        call_user_func($this->completed_callback, $task);
    }    

    public function createTasks()
    {
        $this->openInputFile();
        
        $this->createOutputFile();
        
        while (($data = fgets($this->input_file_handle, self::READ_BUFF_SIZE)) !== false) {        
            $this->addTasksFromRow($data);
        }
            
        $this->closeInputFile();
    }
    
    public function tasksLeft() 
    {
        return $this->tasks_left;
    }
    
    public function tasksTotal() {
        
     return $this->tasks_total;
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
        $input_sites_list = explode($this->delim, $data);

        $task_sites_list = array();

        foreach ($input_sites_list as $site) {

            $site = $this->normalizeUrl($site);
            if (!is_null($site) && !in_array($site, $task_sites_list)) {
                $task_sites_list[] = $site;
            }
        }

        if (count($task_sites_list) == 0) {
            return;
        }

        $task_sites_list = $this->removeWwwDuplicates($task_sites_list);

        foreach ($task_sites_list as $site) {
            $this->tasks_total++;
            $this->addTask('checkSite', $site);
        }
        $this->tasks_left = $this->tasks_total;
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
            return null;
        }
        
        if ($is_host) {
            $result = 'http://' . $result;
        }

        $host = parse_url($result, PHP_URL_HOST);
        if (in_array(mb_strtolower($host), $this->host_black_list)) {
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
                unset($sites_list[$key]);
            }
        }
        return $sites_list;
    } 
        
    protected function closeInputFile()
    {
        fclose($this->input_file_handle);
    }   
    
    protected function createOutputFile() {
      
        $this->output_file_handle = @fopen($this->output_file_name, 'w');
        if (!$this->output_file_handle) {
            throw new Exception('Unable to create ' . $this->output_file_name);
        }        
        fputs($this->output_file_handle, implode($this->delim, $this->column_names) . "\n");
    }
    
}
