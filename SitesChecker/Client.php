<?php

namespace SitesChecker;

/**
 * Description of Client
 *
 */
class Client extends \GearmanClient
{
    
    protected $input_file_name;
    
    protected $output_file_name;   
    
    protected $input_file_handle;
    

    public function __construct($input_file_name, $output_file_name)
    {
        parent::__construct();
        $this->addServer();
    }

    public function createTasks()
    {
        $this->addTask('checkSite', 'yandex.ru');
    }
}
