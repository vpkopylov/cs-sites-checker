<?php

namespace SitesChecker\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SitesChecker\Client;
use SitesChecker\Worker;
use SitesChecker\Exception;


class RunCommand extends Command
{
    
    protected $client = null;
    
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Parse csv file with urls and create csv file with sites that use cs-cart')
            ->addOption(
               'input-file',
               'i',
               InputOption::VALUE_REQUIRED,
               'name of csv file with urls'
            )
            ->addOption(
               'output-file',
               'o',
               InputOption::VALUE_REQUIRED,
               'output file name'
            )            
        ;
    }    
        
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start_time = time();
        
        $input_file = $input->getOption('input-file');
        $output_file =$input->getOption('output-file');
        
        if (is_null($output_file) || is_null($output_file)) {            
            $output->writeln($this->getSynopsis());
            $output->writeln($this->getDescription());
            $output->writeln('Required options missing');
            return;
        }
        $this->client = new Client($input_file, $output_file);
        
        $this->client->setCompleteCallback(array($this, 'onTaskComplete'));
        $this->client->setFailCallback(array($this, 'onTaskFail'));                
        
        $output->writeln ("Run worker(s) if you haven't done this yet");
        $output->writeln ("Creating tasks");
        $this->client->createTasks();
        $output->writeln ("Running tasks");
        if (!$this->client->runTasks()) {
            throw new Exception($this->client->error());
        }
        
        $end_time = time();
        $spent_min = round(($end_time - $start_time) / 60, 2);
        $output->writeln ("Spent time $spent_min min");
    }
    
    public function onTaskComplete($task)
    {
        $data = json_decode($task->data());
        if ($data->error_code != Worker::ERROR_OK) {
            printf ('Task completed with error: %s', $task->jobHandle() . ', ' . $task->data() . "\n");
        }
        printf("Tasks left: %u/%u\n", $this->client->tasksLeft(), $this->client->tasksTotal());
    }
    

    public function onTaskFail($task)
    {
        echo 'Task failed: ' . $task->jobHandle() . "\n";
    }    

}
