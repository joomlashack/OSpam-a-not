<?php

/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2026 Joomlashack.com. All rights reserved
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
use Alledia\Framework\Joomla\Event\SubscriberInterface;
use Alledia\Framework\Joomla\Extension\AbstractPlugin;
use Alledia\Ospamanot\Filters;
use Alledia\Ospamanot\Forms;
use Exception;
use JEventDispatcher;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

// phpcs:enable PSR1.Files.SideEffects

abstract class AbstractMethod extends AbstractPlugin implements SubscriberInterface
{
    public const LOG_FILE = 'ospamanot.log.php';

    /**
     * @var Forms[]
     */
    protected array $forms = [];

    /**
     * @inheritdoc
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * @inheritdoc
     */
    protected $autoloadLanguage = true;

    /**
     * @param ?JEventDispatcher|DispatcherInterface $subject
     * @param ?array                                $config
     *
     * @return array
     */
    public static function registerMethods($subject = null, ?array $config = null): void
    {
        try {
            $files = Folder::files(__DIR__, '^(?!Abstract).*\.php$');

            foreach ($files as $file) {
                $name      = basename($file, '.php');
                $className = '\\' . __NAMESPACE__ . '\\' . $name;

                if (class_exists($className)) {
                    $config['name'] .= '_' . strtolower($name);
                    $config['type'] = 'ospamanot_method';

                    $reflection = new \ReflectionClass($className);

                    /** @var AbstractMethod $handler */
                    switch ($reflection->getConstructor()->getNumberOfParameters()) {
                        case 1:
                            // Updated CMSPlugin class
                            $handler = new $className($config);
                            $subject->addSubscriber($handler);
                            break;

                        case 2:
                            // Legacy registration
                            $handler = new $className($subject, $config);
                            if (is_callable([$subject, 'attach'])) {
                                $subject->attach($handler);
                            } else {
                                $subject->addSubscriber($handler);
                            }

                            break;

                        default:
                            Factory::getApplication()->enqueueMessage(
                                Text::sprintf('PLG_SYSTEM_OSPAMANOT_ERROR_METHOD_SIGNATURE', $className),
                                'error'
                            );
                            break;
                    }

                } else {
                    Factory::getApplication()->enqueueMessage(
                        Text::sprintf('PLG_SYSTEM_OSPAMANOT_ERROR_METHOD_NOTFOUND', $className, $file),
                        'error'
                    );
                }
            }

        } catch (\Throwable $error) {
            Factory::getApplication()->enqueueMessage($error->getMessage(), 'error');
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
        $input = Factory::getInput($this->app);

        $context = join(
            '.',
            array_filter(
                [
                    $input->getCmd('option'),
                    $input->getCmd('task', Factory::getInput($this->app)->getCmd('view')),
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
        $referrer = $input->server->get('HTTP_REFERER', '', 'URL');

        if ($testName == false) {
            $message = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_GENERIC');
        } else {
            $message = $context . ':: ' . Text::sprintf('PLG_SYSTEM_OSPAMANOT_BLOCK_FORM', $testName);
        }

        if ($this->params->get('logging', 0)) {
            $category = $caller . '.' . ($testName ?: 'generic');
            Log::addLogger(['text_file' => static::LOG_FILE], Log::ALL, [$category]);
            Log::add($context . ' - ' . Uri::getInstance()->getPath(), Log::NOTICE, $category);
        }

        if ($input->getCmd('format', 'html') == 'html') {
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
