<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2022 Joomlashack.com. All rights reserved
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
use Alledia\Ospamanot\Method\AbstractMethod;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Event\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

if (include __DIR__ . '/include.php') {
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
         * @param JEventDispatcher|Dispatcher $subject
         * @param array                       $config
         *
         * @return void
         */
        public function __construct($subject, $config = [])
        {
            parent::__construct($subject, $config);

            // We only care about guest users on the frontend
            if ($this->app->isClient('site')) {
                $this->registerMethods($subject, $config);
            }
        }

        /**
         * Register all the known method plugins
         *
         * @param JEventDispatcher|Dispatcher $subject
         * @param array                       $config
         *
         * @return void
         */
        protected function registerMethods($subject, array $config)
        {
            try {
                $classInfo = new ReflectionClass(AbstractMethod::class);

                $path      = dirname($classInfo->getFileName());
                $nameSpace = $classInfo->getNamespaceName();

            } catch (Throwable $error) {
                // Fail silently
                return;
            }

            $methods = Folder::files($path, '^(?!AbstractMethod).*\.php$');

            foreach ($methods as $file) {
                $name      = basename($file, '.php');
                $className = '\\' . $nameSpace . '\\' . $name;

                if (class_exists($className)) {
                    $config['name'] = $this->_name . strtolower($name);

                    /** @var AbstractMethod $handler */
                    $handler = new $className($subject, $config);

                    if ($subject instanceof JEventDispatcher) {
                        // Joomla 3
                        $subject->attach($handler);

                    } elseif ($subject instanceof Dispatcher) {
                        // Joomla 4
                        // @TODO: Note this depends on J3 legacy support
                        $handler->registerListeners();
                    }
                }
            }
        }
    }
}
