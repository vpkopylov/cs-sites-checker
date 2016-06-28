<?php

namespace SitesChecker;

use SitesChecker\Worker;

class Client extends \GearmanClient
{

    const READ_BUFF_SIZE = 1000;

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
    protected $input_file_name;
    protected $output_file_name;
    protected $input_file_handle;
    protected $output_file_handle;
    protected $delimiter = ',';
    protected $completed_callback = null;
    protected $tasks_total = null;
    protected $tasks_left = null;
    protected $version_totals = array();

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
            $put_data = array($result_data->site, $result_data->product_version_str);
            fputs($this->output_file_handle, implode($this->delimiter, $put_data) . "\n");
            $version = $result_data->product_version_major;
            if (!array_key_exists($version, $this->version_totals)) {
                $this->version_totals[$version] = 1;
            } else {
                $this->version_totals[$version] ++;
            }
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

    public function tasksTotal()
    {

        return $this->tasks_total;
    }

    public function writeTotalsFile()
    {
        $path_parts = pathinfo($this->output_file_name);
        $extenstion = isset($path_parts['extension']) ? '.' . $path_parts['extension'] : '';
        $file_name = $path_parts['dirname'] . '/' . $path_parts['filename'] . '_totals' . $extenstion;
        $handle = @fopen($file_name, 'w');
        if (!$handle) {
            throw new Exception('Unable to create ' . $file_name);
        }
        ksort($this->version_totals);
        $this->version_totals['total'] = array_sum($this->version_totals);
        
        fputs($handle, implode($this->delimiter, array_keys($this->version_totals)) . "\n");
        fputs($handle, implode($this->delimiter, $this->version_totals) . "\n");
        fclose($handle);
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

    protected function closeInputFile()
    {
        fclose($this->input_file_handle);
    }

    protected function createOutputFile()
    {

        $this->output_file_handle = @fopen($this->output_file_name, 'w');
        if (!$this->output_file_handle) {
            throw new Exception('Unable to create ' . $this->output_file_name);
        }
        fputs($this->output_file_handle, implode($this->delimiter, $this->column_names) . "\n");
    }

    protected function addTasksFromRow($data)
    {
        $input_sites_list = explode($this->delimiter, $data);

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
            if (mb_stripos($host, 'www.') === 0) {
                continue;
            }
            $www_host = 'www.' . $host;
            $www_site = str_ireplace($host, $www_host, $site);
            if (in_array($www_site, $sites_list)) {
                unset($sites_list[$key]);
            }
        }
        return $sites_list;
    }
}
