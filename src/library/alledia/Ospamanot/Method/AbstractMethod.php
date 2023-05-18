<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2023 Joomlashack.com. All rights reserved
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

namespace Alledia\Ospamanot\Method;

use Alledia\Framework\Factory;
use Alledia\Framework\Joomla\Extension\AbstractPlugin;
use Alledia\Ospamanot\Filters;
use Alledia\Ospamanot\Forms;
use Exception;
use JEventDispatcher;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

// phpcs:enable PSR1.Files.SideEffects

abstract class AbstractMethod extends AbstractPlugin
{
    public const LOG_FILE = 'ospamanot.log.php';

    /**
     * @var Forms[]
     */
    protected $forms = [];

    /**
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * @param DispatcherInterface $subject
     * @param array               $config
     *
     * @return void
     */
    public static function registerMethods($subject, array $config): void
    {
        try {
            $files = Folder::files(__DIR__, '^(?!Abstract).*\.php$');

            foreach ($files as $file) {
                $name      = basename($file, '.php');
                $className = '\\' . __NAMESPACE__ . '\\' . $name;

                if (class_exists($className)) {
                    $config['name'] .= '_' . strtolower($name);

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

                } else {
                    Factory::getApplication()->enqueueMessage('Class ' . $className . ' not found in ' . $file);
                }
            }

        } catch (\Throwable $error) {
            // ignore
        }
    }

    /**
     * @return string[]
     */
    public static function getLogEntries(): array
    {
        try {
            $logPath = Factory::getApplication()->get('log_path') . '/' . static::LOG_FILE;
            $entries = is_file($logPath) ? file($logPath) : [];

            if ($entries) {
                $entries = array_values(
                    array_filter($entries, function ($entry) {
                        $entry = trim($entry);

                        return stripos($entry, '#fields') === 0 || (strlen($entry) == 0 || $entry[0] == '#') == false;
                    })
                );

                $entries[0] = preg_replace('/#fields:\s*/i', '', $entries[0]);
            }

        } catch (\Throwable $error) {
            $entries = [];
        }

        return $entries;
    }

    /**
     * @param string $text
     *
     * @return Forms
     */
    protected function getForms(string $text): Forms
    {
        $key = md5($text);
        if (array_key_exists($key, $this->forms) == false) {
            $this->forms[$key] = new Forms($text);
        }

        return $this->forms[$key];
    }

    /**
     * Standard response for use by subclasses that want to block the user for any reason
     *
     * @param ?string $testName
     *
     * @return void
     * @throws Exception
     */
    protected function block(?string $testName = null)
    {
        $context = join(
            '.',
            array_filter(
                [
                    $this->app->input->getCmd('option'),
                    $this->app->input->getCmd('task', $this->app->input->getCmd('view'))
                ]
            )
        );
        if (Filters::getInstance()->allow($context)) {
            return;
        }


        $stack  = debug_backtrace();
        $caller = null;
        if (empty($stack[1]['class']) == false) {
            $classParts = explode('\\', $stack[1]['class']);
            $caller     = array_pop($classParts);
        }
        $method   = $stack[1]['function'] ?? null;
        $referrer = $this->app->input->server->get('HTTP_REFERER', '', 'URL');

        if ($testName == false) {
            $message = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_GENERIC');
        } else {
            $message = Text::sprintf('PLG_SYSTEM_OSPAMANOT_BLOCK_FORM', $testName);
        }

        if ($this->params->get('logging', 0)) {
            $category = $caller . '.' . ($testName ?: 'generic');
            Log::addLogger(['text_file' => static::LOG_FILE], Log::ALL, [$category]);
            Log::add($context . ' - ' . Uri::getInstance()->getPath(), Log::NOTICE, $category);
        }

        if ($this->app->input->getCmd('format', 'html') == 'html') {
            switch (strtolower($method)) {
                case 'onafterinitialise':
                case 'onafterroute':
                case 'onafterrender':
                    $link = $referrer ?: Route::_('index.php');

                    $this->app->enqueueMessage($message, 'error');
                    $this->app->redirect(Route::_($link));

                    return;
            }
        }

        throw new Exception($message, 403);
    }

    /**
     * Check the current url for fields that might have been improperly
     * introduced in the URL and remove if present
     *
     * @param string[] $fields
     *
     * @return void
     */
    protected function checkUrl(array $fields)
    {
        $uri   = Uri::getInstance();
        $query = $uri->getQuery(true);
        foreach ($fields as $field) {
            if (isset($query[$field])) {
                $uri->delVar($field);
            }
        }

        if ($query != $uri->getQuery(true)) {
            $this->app->redirect($uri);
        }
    }
}
