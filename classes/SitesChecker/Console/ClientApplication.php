<?php

namespace SitesChecker\Console;

use SitesChecker\Client;

/**
 * Description of ConsoleApplication
 *
 */
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
    
    public function parseOptions()
    {
        $this->parseOption('i', 'input-file');
        $this->parseOption('o', 'output-file');
    }    

    public function execute()
    {
        $input_file = $this->options['input-file'];
        $output_file = $this->options['output-file'];
        $client = new Client($input_file, $output_file);
        
        $client->setCompleteCallback(array($this, 'onTaskComplete'));
        $client->setFailCallback(array($this, 'onTaskFail'));        
        
        $client->createTasks();
        if (!$client->runTasks()) {
            throw new InternalException($client->error());
        }
    }
    
    public function onTaskComplete($task)
    {
        echo "Task completed: " . $task->jobHandle() . ", " . $task->data() . "\n";
    }

    public function onTaskFail($task)
    {
        echo "Task failed: " . $task->jobHandle() . "\n";
    }    

}