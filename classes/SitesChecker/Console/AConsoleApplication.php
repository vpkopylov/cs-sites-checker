<?php

namespace SitesChecker\Console;

/**
 * Description of AConsoleApplication
 *
 */
abstract class AConsoleApplication
{
        
    protected $options_short = null;
    
    protected $options_long = array();

    protected $options = null;
    
    protected $usage = null;    
    
    protected $usage_error = null;

    public function __construct()
    {
        $this->options = getopt($this->options_short, $this->options_long);
    }

    public function usageString()
    {

        $result = '';
        if ($this->usage_error) {
            $result.= $this->usage_error . "\n";
        }
        $result.= $this->usage;
        
        return $result;
    }

    abstract public function parseOptions();

    abstract public function execute();

    protected function parseOption($short_name, $long_name, $is_required = true)
    {
        $value = array_key_exists($short_name, $this->options) ? $this->options[$short_name] : false;
        if (!$value) {
            $value = array_key_exists($long_name, $this->options) ? $this->options[$long_name] : false;
        }
        if (!$value && $is_required) {
            $this->usage_error = "Option $short_name|$long_name is missing";
            throw new InvalidArgumentException($this->usage_error);
        }
        
        $this->options[$long_name] = $value;
    }
}
