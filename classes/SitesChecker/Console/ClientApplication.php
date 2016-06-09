<?php

namespace SitesChecker\Console;

use SitesChecker\Client;
use SitesChecker\Worker;


class ClientApplication extends AConsoleApplication
{

    protected  $usage = 
'Parse csv file with urls and create csv file with sites that use cs-cart
Usage:
cs-sites-checker.php -i INPUTFILE -o OUTPUTFILE
-i|--input-file             - csv file with urls
-o|--output-file            - output csv file';
    
    protected $options_short = 'i:o:';
    
    protected $options_long = array(
        'input-file:',
        'output-file:'
    );
    
    protected $client = null;


    public function parseOptions()
    {
        $this->parseOption('i', 'input-file');
        $this->parseOption('o', 'output-file');
    }    

    public function execute()
    {
        $start_time = time();
        
        $input_file = $this->options['input-file'];
        $output_file = $this->options['output-file'];
        $this->client = new Client($input_file, $output_file);
        
        $this->client->setCompleteCallback(array($this, 'onTaskComplete'));
        $this->client->setFailCallback(array($this, 'onTaskFail'));                
        
        echo "Run worker(s) if you haven't done this yet \n";
        echo "Creating tasks\n";
        $this->client->createTasks();
        echo "Running tasks\n";
        if (!$this->client->runTasks()) {
            throw new InternalException($this->client->error());
        }
        
        $end_time = time();
        $spent_min = round(($end_time - $start_time) / 60, 2);
        echo "Spent time $spent_min min\n";
    }
    
    public function onTaskComplete($task)
    {
        $data = json_decode($task->data());
        if ($data->error_code != Worker::ERROR_OK) {
            echo 'Task completed with error: ' . $task->jobHandle() . ', ' . $task->data() . "\n";
        }
        printf("Tasks left: %u/%u\n", $this->client->tasksLeft(), $this->client->tasksTotal());
    }
    

    public function onTaskFail($task)
    {
        echo 'Task failed: ' . $task->jobHandle() . "\n";
    }    

}
