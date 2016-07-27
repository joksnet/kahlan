<?php
namespace Kahlan\Reporter;

class Step extends Terminal
{
    protected $_verbose;
    protected $_coverages;
    protected $_coveragesVerbosity;
    protected $_coverageParams;

    private function _run($method, array $args)
    {
        $this->_init();
        //call_user_func_array([$this->_verbose, $method], $args);
    }

    public function start($params)
    {
        parent::start($params);

        $config = [
            'start' => $this->_start,
            'colors' => $this->_colors,
            'header' => $this->_header,
        ];
        $this->_verbose =  new Verbose($config);

        if (PHP_SAPI === 'phpdbg') {
            $driver = new Coverage\Driver\Phpdbg();
        } elseif (extension_loaded('xdebug')) {
            $driver = new Coverage\Driver\Xdebug();
        } else {
            fwrite(STDERR, "ERROR: PHPDBG SAPI has not been detected and Xdebug is not installed, code coverage can't be used.\n");
            exit(-1);
        }
        $this->_coverageParams = $config + [
            'driver' => $driver,
            'path' => 'src/',
        ];
        unset($driver);
        unset($config);

        $this->_verbose->start($params);
    }

    public function suiteStart($report = null)
    {
        $target = '';
        foreach ($report->messages() as $message) {
            if (strpos($message, ' ') !== false) {
                continue;
            }
            if (empty($message)) {
                $message = 'Khalan';
            }
            $message = str_replace('->', '::', $message);
            $message = trim(trim($message, ')'), '(');
            if (strpos($target, ':') === false && strpos($message, ':') === false) {
                $message = '\\' . $message;
            }
            $target .= $message;
        }
        unset($message);

        $targets = [];
        if (strpos(strrchr($target, ':'), '/') !== false) {
            $fqdn = strchr($target, ':', true);
            $methods = explode('/', ltrim(strrchr($target, ':'), ':'));
            foreach ($methods as $method) {
                $targets[] = $fqdn . '::' . $method;
            }
            unset($fqdn);
            unset($methods);
        } else {
            $targets[] = $target;
        }
        unset($target);

        $this->_coverages = [];
        $this->_coveragesVerbosity = [];
        foreach ($targets as $target) {
            if (strpos($target, '::') === false) {
                continue;
            }
            $verbosity = ltrim($target . '()', '\\');
            $this->_coverages[] = new Coverage($this->_coverageParams + [
                'verbosity' => $verbosity,
            ]);
            $this->_coveragesVerbosity[] = $verbosity;
        }

        $this->_verbose->suiteStart($report);
    }

    public function suiteEnd($report = null)
    {
        $this->_verbose->suiteEnd($report);
    }

    public function specStart($report = null)
    {
        foreach ($this->_coverages as $coverage) {
            $coverage->specStart($report);
        }
    }

    public function specEnd($report = null)
    {
        $this->_verbose->specEnd($report);
        foreach ($this->_coverages as $i => $coverage) {
            $coverage->specEnd($report);
            $metrics = $coverage->metrics()->get($this->_coveragesVerbosity[$i]);
            if ($metrics) {
                $coverage->_renderCoverage($metrics);
            }
        }
    }

    public function end($results = [])
    {
        $this->_verbose->end($results);
    }
}
