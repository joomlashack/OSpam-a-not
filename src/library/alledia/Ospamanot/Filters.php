<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2023 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
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
 * along with OSpam-a-not.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Alledia\Ospamanot;

// phpcs:disable PSR1.Files.SideEffects
use Alledia\Ospamanot\Filter\AbstractFilter;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

final class Filters
{
    /**
     * @var static
     */
    protected static $instance = null;

    /**
     * @var AbstractFilter[]
     */
    protected $filters = [];

    /**
     * @var Registry
     */
    protected $config = null;

    /**
     * @return void
     */
    protected function __construct()
    {
        $plugin = PluginHelper::getPlugin('system', 'ospamanot');

        $this->config = new Registry($plugin->params ?? null);

        $this->loadFilters();
    }

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getParam(string $key, $default = null)
    {
        if ($this->config) {
            return $this->config->get($key, $default);
        }

        return $default;
    }

    /**
     * @return \SimpleXMLElement[]
     * @throws \Exception
     */
    public function getAdminForms(): array
    {
        $internalErrors = libxml_use_internal_errors(true);

        $xml = [];
        foreach ($this->filters as $filter) {
            $class = new \ReflectionClass($filter);
            $path  = $class->getFileName();
            $file  = dirname($path) . '/' . basename($path, '.php') . '.xml';

            if (is_file($file)) {
                if ($fragment = simplexml_load_file($file)) {
                    $xml[] = $fragment;

                } else {
                    $errors = array_map(
                        function (\LibXMLError $error) {
                            return Text::sprintf(
                                'PLG_SYSTEM_OSPAMANOT_ERROR_FILTER_LIBXML',
                                $error->line,
                                $error->column,
                                $error->message
                            );
                        },
                        libxml_get_errors()
                    );

                    Factory::getApplication()->enqueueMessage(
                        Text::sprintf(
                            'PLG_SYSTEM_OSPAMANOT_ERROR_FILTER_XML',
                            basename($file),
                            join('<br>', $errors)
                        ),
                        'warning'
                    );
                }
            }
        }

        libxml_use_internal_errors($internalErrors);
        return $xml;
    }

    /**
     * @return void
     */
    protected function loadFilters()
    {
        $filterPath = __DIR__ . '/Filter';
        if (is_dir($filterPath)) {
            $files = Folder::files($filterPath, '^(?!Abstract).*\.php$');

            foreach ($files as $file) {
                $name      = basename($file, '.php');
                $className = '\\' . __NAMESPACE__ . '\\Filter\\' . $name;

                if (class_exists($className)) {
                    $this->filters[] = new $className($this);
                }
            }
        }
    }

    /**
     * @param FormTags $form
     *
     * @return bool
     */
    public function exclude(FormTags $form): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->exclude($form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param FormTags $form
     *
     * @return bool
     */
    public function include(FormTags $form): bool
    {
        return $this->exclude($form) == false;
    }

    /**
     * @param string $context
     *
     * @return bool
     */
    public function allow(string $context): bool
    {
        foreach ($this->filters as $filter) {
            if ($filter->allow($context)) {
                return true;
            }
        }

        return false;
    }
}
