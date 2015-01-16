<?php
/**
 * @package   OSpam-a-not
 * @contact   www.alledia.com, support@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

require_once 'include.php';

/**
 * Ospamanot Content Plugin
 *
 */
class PlgSystemOspamanot extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $namespace = 'Ospamanot';

    /**
     * @param JEventDispatcher $subject
     * @param array            $config
     */
    public function __construct($subject, $config = array())
    {
        parent::__construct($subject, $config);

        // We only care about guest users on the frontend right now
        if (JFactory::getApplication()->isSite()) {
            $this->registerMethods($subject, $config);
        }
    }

    /**
     * Register all the known method plugins
     *
     * @param JEventDispatcher $subject
     * @param array            $config
     */
    protected function registerMethods($subject, $config)
    {
        jimport('joomla.filesystem.folder');

        $methods = JFolder::files(__DIR__ . '/methods', '\.php$', false, true);
        foreach ($methods as $path) {
            $name      = strtolower(basename($path, '.php'));
            $className = __CLASS__ . ucfirst($name);

            require_once $path;
            if (class_exists($className)) {
                $config['name'] = $this->_name . $name;

                $method = new $className($subject, $config);
                $subject->attach($method);
            }
        }
    }

    /**
     * Standard response for use by methods that want to block the user for any reason
     *
     * @param string $method
     * @param string $message
     *
     * @throws Exception
     * @return void
     */
    public function onSpamanotBlock($method, $message = null)
    {
        if (strpos($method, '::') !== false) {
            list($caller, $method) = explode('::', $method);
        }

        if ($message === null) {
            $message = JText::_('JERROR_ALERTNOAUTHOR');
        }

        switch (strtolower($method)) {
            case 'onafterinitialise':
            case 'onafterroute':
            case 'onafterrender':
                JFactory::getApplication()->redirect('index.php', $message);
                break;

            default:
                throw new Exception($method . ': ' . $message, 403);
        }
    }
}
