<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Template Library
 *
 * @package     Template
 * @version     v1.0.0-beta
 * @license     MIT License
 * @author      Victor Danilo, <victordanilo_cs@live.com>
 * @link        https://github.com/victordanilo/codeigniter-template
 */

class Template
{
    private $ci;            /** @var CI_Controller $ci */
    private $_parser;       /** @var bool $_parser */
    private $_template;     /** @var string $_template */
    public $content;        /** @var Content $content */

    use View;

    /**
     * template constructor.
     *
     * @access public
     * @param array $config
     */
    public function __construct($config = array())
    {
        $this->ci        =& get_instance();
        $this->_parser   = false;
        $this->_template = null;
        $this->content   = new Content();

        /** initialize configurations */
        if (!empty($config))
            $this->initialize($config);

        log_message('debug', 'Template Class Initialized');
    }

    /**
     * initialize configurations
     *
     * @access public
     * @param array $config
     * @return Template $this
     */
    public function initialize($config = array())
    {
        foreach ($config as $key => $val)
            $this->{'_'.$key} = $val;

        /** set data content */
        $this->content->set_parser($this->_parser);

        return $this;
    }

    /**
     * set parser configuration
     *
     * @access public
     * @param bool $bool
     * @return Template $this
     */
    public function set_parser($bool)
    {
        $this->_parser = (bool) $bool;
        $this->content->set_parser($bool);

        return $this;
    }

    /**
     * set base template
     * @param string $view
     * @return Template $this
     */
    public function set_template($view)
    {
        $this->view_exists($view);
        $this->_template = (string) $view;

        return $this;
    }

    /**
     * template rendering
     *
     * @param string $view
     * @param array $data
     * @param bool $parser
     */
    public function render($view = null, $data = array(), $parser = false)
    {
        if (!empty($view))
            $this->content->add($view, $data, $parser);

        if (!empty($this->_template)) {
            $contents = $this->content->get_all();

            foreach ($contents as $name => $section)
                $data[$name] = implode('',$section);

            $out = $this->load_view($this->_template, $data, $this->_parser);
        }
        else
            $out = $this->content->build();

        $this->ci->output
            ->set_output($out)
            ->_display();
        exit;
    }
}

/**
 * Content Manager
 */
class Content
{
    private $ci;                /** @var CI_Controller $ci */
    private $_this;             /** @var array $_this*/
    private $_parser;           /** @var bool $_parser */
    private $_current_section;  /** string $_current_section */

    use View;

    /**
     * Content constructor.
     */
    public function __construct()
    {
        $this->ci               =& get_instance();
        $this->_this            = array();
        $this->_parser          = false;
        $this->_current_section = 'default';
    }

    /**
     * add new element to loading
     *
     * @param string $view
     * @param array $data
     * @param bool $parser
     * @return Content $this
     */
    public function add($view, $data = array(), $parser = false)
    {
        $this->view_exists($view);

        $parser = $parser || $this->_parser ? true : false;
        $origin = !empty($this->_this[$this->_current_section]) ? $this->_this[$this->_current_section] : array();
        $insert = $this->load_view($view, $data, $parser);

        array_push($origin, $insert);

        $this->_this[$this->_current_section] = $origin;

        return $this;
    }

    /**
     * add new element first to loading
     *
     * @param string $view
     * @param array $data
     * @param bool $parser
     * @return Content $this
     */
    public function add_first($view, $data = array(), $parser = false)
    {
        $this->view_exists($view);

        $parser = $parser || $this->_parser ? true : false;
        $origin = !empty($this->_this[$this->_current_section]) ? $this->_this[$this->_current_section] : array();
        $insert = $this->load_view($view, $data, $parser);

        array_unshift($origin, $insert);

        $this->_this[$this->_current_section] = $origin;

        return $this;
    }

    /**
     * set section current
     *
     * @param string $name
     * @return Content $this
     */
    public function section($name)
    {
        $this->_current_section = (string) $name;

        return $this;
    }

    /**
     * move current/select section to first position
     *
     * @param string $section
     * @return Content $this
     */
    public function first($section = null)
    {
        $section = $section ? $section : $this->_current_section;
        $move    = array();

        if (array_key_exists($section, $this->_this)) {
            $move[$section] = $this->_this[$section];
            unset($this->_this[$section]);
        }

        $this->_this = $move + $this->_this;

        return $this;
    }

    /**
     * move current/select section to last position
     *
     * @param string $section
     * @return $this
     */
    public function last($section = null)
    {
        $section = $section ? $section : $this->_current_section;
        $move    = array();

        if (array_key_exists($section, $this->_this)) {
            $move[$section] = $this->_this[$section];
            unset($this->_this[$section]);
        }

        $this->_this += $move;

        return $this;
    }

    /**
     * move current section to after select section
     *
     * @param string $section
     * @return Content $this | bool
     */
    public function after($section)
    {
        if (!array_key_exists($section, $this->_this))
            return false;

        $origin = $this->_this;
        $insert = null;
        $data   = array();

        if (array_key_exists($this->_current_section, $origin)) {
            $insert = $origin[$this->_current_section];
            unset($origin[$this->_current_section]);
        }

        foreach ($origin as $key => $value) {
            $data[$key] = $value;

            if ($key == $section)
                $data[$this->_current_section] = $insert;
        }

        $this->_this = $data;

        return $this;
    }

    /**
     * move current section to before select section
     *
     * @param string $section
     * @return Content $this | bool
     */
    public function before($section)
    {
        if (!array_key_exists($section, $this->_this))
            return false;

        $origin = $this->_this;
        $insert = null;
        $data   = array();

        if (array_key_exists($this->_current_section, $origin)) {
            $insert = $origin[$this->_current_section];
            unset($origin[$this->_current_section]);
        }

        foreach ($origin as $key => $value) {
            if ($key == $section)
                $data[$this->_current_section] = $insert;

            $data[$key] = $value;
        }

        $this->_this = $data;

        return $this;
    }

    /**
     * get section
     *
     * @return array $_this | bool;
     */
    public function get($section)
    {
        if (array_key_exists($section, $this->_this))
            return $this->_this[$section];

        return false;
    }

    /**
     * get all sections
     *
     * @return array $_this;
     */
    public function get_all()
    {
        return $this->_this;
    }

    /**
     * remove section
     *
     * @param string $section
     * @return Content $this | bool
     */
    public function remove($section)
    {
        if (!array_key_exists($section, $this->_this))
            return false;
        else
            unset($this->_this[$section]);

        return $this;
    }

    /**
     * remove all sections
     *
     * @return Content $this
     */
    public function remove_all()
    {
        $this->_this = array();

        return $this;
    }

    /**
     * set parser configuration
     *
     * @param bool $bool
     * @return Content $this
     */
    public function set_parser($bool)
    {
        $this->_parser = (bool) $bool;
        $this->loading_parser();

        return $this;
    }

    /**
     * build content
     *
     * @return null|string
     */
    public function build()
    {
        if (empty($this->_this))
            return '';

        $out = null;
        foreach ($this->_this as $value)
            $out .= implode('', $value);

        return $out;
    }

    /**
     * output content
     *
     * @return null|string
     */
    public function __toString()
    {
        return $this->build();
    }
}

/**
 * View
 */
trait View
{
    /**
     * loading view
     *
     * @param string $view
     * @param array $data
     * @param bool $parser
     * @return string
     */
    public function load_view($view, $data = array(), $parser = false)
    {
        $this->view_exists($view);

        if ($parser || $this->_parser)
        {
            if (!class_exists('CI_Parser'))
                $this->ci->load->library('parser');

            return $this->ci->parser->parse($view, $data, TRUE);
        }
        else
            return $this->ci->load->view($view, $data, TRUE);
    }

    /**
     * Check if view exists
     *
     * @access private
     * @param string $view
     */
    private function view_exists($view)
    {
        if (!file_exists(VIEWPATH . $view . '.php')) {
            show_error("Error: view {$view}.php not found!");
            exit;
        }
    }

    /**
     * loading parser
     */
    private function loading_parser()
    {
        if ($this->_parser && !class_exists('CI_Parser'))
            $this->ci->load->library('parser');
    }
}
