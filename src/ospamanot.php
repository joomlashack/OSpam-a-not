<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2019 Joomlashack.com. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSpam-a-not.
 *
 * OSpam-a-not is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSpam-a-not is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSpam-a-not.  If not, see <http://www.gnu.org/licenses/>.
 */

use Alledia\Framework\Joomla\Extension\AbstractPlugin;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;

defined('_JEXEC') or die();

require_once 'include.php';

if (defined('ALLEDIA_FRAMEWORK_LOADED')) {
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

        protected $autoloadLanguage = true;

        /**
         * @var CMSApplication
         */
        protected $app = null;

        /**
         * @param JEventDispatcher $subject
         * @param array            $config
         *
         * @return void
         * @throws Exception
         */
        public function __construct($subject, $config = array())
        {
            parent::__construct($subject, $config);

            // We only care about guest users on the frontend right now
            if (Factory::getApplication()->isClient('site')) {
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
            $path      = __DIR__ . '/Method';
            $baseClass = $path . '/AbstractMethod.php';

            if (is_file($baseClass) && $methods = Folder::files($path, '^(?!AbstractMethod).*\.php$', false, true)) {
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
}
