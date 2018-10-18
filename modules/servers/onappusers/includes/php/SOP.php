<?php

/**
 * Main option parser class
 */
class SOP extends SOPGeneral
{
    const NUMBER = 'number';
    const INTEGER = 'integer';
    private $helpBanner;
    private $definedOptions;
    private $availableOptions = array();
    private $longOptsDefinition = array();

    # constants for type-checking
    private $shortOptsDefinition = '';
    private $longToShortAssociation = array();

    /**
     * @param array  $options Available options
     * @param string $helpBanner Help banner
     */
    public function __construct(array $options = array(), $helpBanner = '')
    {
        if (!empty($options)) {
            $this->setOptions($options);
        }
        if (!empty($helpBanner)) {
            $this->setBanner($helpBanner);
        }

        # manually add the default 'help' command
        $help = array(
            'help' => array(
                'short' => 'h',
                'description' => 'Display this help',
            )
        );
        $this->setOption($help);
        unset($help);

        $this->definedOptions = new stdClass();
    }

    /**
     * Sets options definition
     *
     * @param array $options List of available options
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $option) {
            $this->setOption(array($key => $option));
        }
    }

    public function setOption(array $option)
    {
        $option = new SOPOption($option);
        $this->availableOptions[$option->long] = $option;
        $this->longToShortAssociation[$option->long] = $option->short;

        $param = '::';
        $this->longOptsDefinition[] = $option->long . $param;
        if ($option->short) {
            $this->shortOptsDefinition .= $option->short . $param;
        }
    }

    /**
     * Sets the help banner
     *
     * @param string $banner The banner text to display on help
     */
    public function setBanner($banner)
    {
        $this->helpBanner = $banner;
    }

    /**
     * This is the main option parsing method
     *
     * @return string $text Text output for the CLI
     */
    public function parse()
    {
        $options = getopt($this->shortOptsDefinition, $this->longOptsDefinition);

        # assemble an array of proper options
        foreach ($options as $option => $value) {
            $opt = null;

            if (isset($this->availableOptions[$option])) {
                $opt = $this->availableOptions[$option];
            } else {
                $opt = $this->availableOptions[array_search($option, $this->longToShortAssociation)];
            }

            # if value was unspecified, look up the default
            if ($value == null) {
                $value = $opt->default;
            }

            # use some type-punning to cast $value to the appropriate
            # PHP data-type
            if (is_numeric($value)) {
                $value += 0;
            } else {
                $value .= '';
            }

            # now, set the value for the option
            $opt->setValue($value);

            $this->definedOptions->{$opt->long} = $opt;
        }

        # Now that we've successfully parsed the options, simply
        # show the help banner if --help or -h has been specified.
        if (isset($this->definedOptions->help)) {
            $this->showHelp();
        }

        # validate and prepare each key/value pair for return
        foreach ($this->availableOptions as $option) {
            $option->validate();
        }

        # return the array of parsed options
        return $this->getDefinedOptions();
    }

    /**
     * Displays the help text
     */
    private function showHelp()
    {
        # display the help banner if one has been set
        if ($this->helpBanner !== null) {
            echo $this->helpBanner, PHP_EOL, PHP_EOL;
        }

        $maxLen = 0;
        foreach ($this->availableOptions as $option) {
            if (strlen($option->long) > $maxLen) {
                $maxLen = strlen($option->long);
            }
        }
        $maxLen += 3;

        echo 'Options:', PHP_EOL;
        foreach ($this->availableOptions as $option) {
            $line = '';

            if ($option->short) {
                $line .= '-' . $option->short . ', ';
            } else {
                $line .= str_pad($line, 4);
            }

            $line .= '--' . str_pad($option->long, $maxLen) . ucfirst($option->description);
            if ($option->type != null) {
                $line .= ', valid type is ' . $option->type;
            }

            if ($option->required) {
                $line .= ', required';
            }

            echo ' ', $line, PHP_EOL;
        }
        $this->halt();
    }

    public function getDefinedOptions()
    {
        $return = new stdClass();
        foreach ($this->definedOptions as $option) {
            $return->{$option->long} = $option->value;
        }

        return $return;
    }
}

/**
 * This class encapsulates the options
 */
class SOPOption extends SOPGeneral
{
    private $long;
    private $type;
    private $short;
    private $value;
    private $default;
    private $required;
    private $validation;
    private $description;

    /**
     * Construct the SOP option object
     *
     * @param array $data The option data
     */
    public function __construct(array $data)
    {
        # map the array to object properties
        $this->long = str_replace('_', '-', $i = key($data));
        $data = $data[$i];

        if (!isset($data['description']) || empty($data['description'])) {
            $this->halt('SOP library error: description for option "' . $this->long . '" is not specified.');
        }

        $this->description = trim($data['description']);
        $this->default = (isset($data['default'])) ? $data['default'] : null;
        $this->short = isset($data['short']) ? $data['short'][0] : null;
        $this->type = isset($data['type']) ? strtolower($data['type']) : null;
        $this->validation = isset($data['validation']) ? $data['validation'] : null;
        $this->required = isset($data['required']) ? (bool) $data['required'] : false;
    }

    /**
     * Validates each option
     */
    public function validate()
    {
        # require that required variables have a value
        if ($this->required && $this->value === null) {
            $this->halt('Error: required value for "' . $this->long . '" was not specified.');
        }

        if (isset($this->value) && isset($this->validation)) {
            $this->customValidation();
        } else {
            # if a type constraint was specified, verify that the constraint
            # itself is valid.
            if ($this->type != null && !in_array($this->type, array(SOP::NUMBER, SOP::INTEGER))) {
                $this->halt('SOP library error: invalid type constraint set for "' . $this->long . '". Must be integer, number or string.');
            }

            if ($this->required) {
                switch ($this->type) {
                    case SOP::INTEGER:
                        is_int($this->value) or $this->halt('Error: option "' . $this->long . '" must be an integer.');
                        break;

                    case SOP::NUMBER:
                        is_numeric($this->value) or $this->halt('Error: option "' . $this->long . '" must be a number.');
                        break;
                }
            }
        }
    }

    private function customValidation()
    {
        $result = preg_match('/' . $this->validation . '/', $this->value, $matches);

        if ($result != 1) {
            $this->halt('Error: option "' . $this->long . '" does not match allowed format.');
        }
    }

    /**
     * Value setter
     *
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Magic getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return null;
        }
    }
}

abstract class SOPGeneral
{
    protected function halt($msg = '')
    {
        exit($msg . PHP_EOL);
    }
}