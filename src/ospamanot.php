<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, support@joomlashack.com
 * @copyright 2015 joomlashack.com, All rights reserved
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

        $this->loadLanguage();

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

        $path      = __DIR__ . '/Method';
        $baseClass = $path . '/AbstractMethod.php';
        if (is_file($baseClass) && $methods = JFolder::files($path, '^(?!AbstractMethod).*\.php$', false, true)) {
            require_once $baseClass;

            foreach ($methods as $path) {
                $name      = basename($path, '.php');
                $className = 'Alledia\\' . __CLASS__ . '\\Method\\' . $name;

                require_once $path;
                if (class_exists($className)) {
                    $config['name'] = $this->_name . strtolower($name);

                    $method = new $className($subject, $config);
                    $subject->attach($method);
                }
            }
        }
    }
}
