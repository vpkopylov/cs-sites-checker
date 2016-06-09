<?php

namespace SitesChecker\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SitesChecker\Worker;

class WorkerCommand extends Command
{
    
    protected function configure()
    {
        $this
            ->setName('addworker')
            ->setDescription('Run a gearman worker that executes requestes. You may run multiple workers for better speed');           
    }     

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $worker = new Worker();
        $output->writeln( 'Waiting for job...');
        while ($worker->work()) {
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                $output->writeln ('Return code: ' . $worker->returnCode() . "\n");
                break;
            }
        }
    }
    
    public function parseOptions()
    {
        
    }
}
