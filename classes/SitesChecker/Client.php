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
        
        $this->addTasksFromInput();
        
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

    protected function addTasksFromInput()
    {

        while (($data = fgets($this->input_file_handle, self::READ_BUFF_SIZE)) !== false) {        
            $this->addTask('checkSite', $data);
        }
    }

    protected function closeInputFile()
    {
        fclose($this->input_file_handle);
    }
}
