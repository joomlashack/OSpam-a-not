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

namespace Alledia\Ospamanot\Method;

use Alledia\Framework\Joomla\Extension\AbstractPlugin;
use Alledia\Ospamanot\FormTags;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects

abstract class AbstractMethod extends AbstractPlugin
{
    public const LOG_FILE = 'ospamanot.log.php';

    /**
     * @var FormTags[]
     */
    protected $forms = null;

    /**
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * @var string[] HTML5 text fields
     */
    protected $textFields = [
        'email',
        'number',
        'password',
        'search',
        'tel',
        'text',
        'url'
    ];

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
        $stack  = debug_backtrace();
        $caller = [];
        $method = null;
        if (empty($stack[1]['class']) == false) {
            $classParts = explode('\\', $stack[1]['class']);
            $caller[]   = array_pop($classParts);
        }

        if (empty($stack[1]['function']) == false) {
            $caller[] = $stack[1]['function'];
            $method   = $stack[1]['function'];
        }

        if ($testName == false) {
            $message = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_GENERIC');
        } else {
            $message = Text::sprintf('PLG_SYSTEM_OSPAMANOT_BLOCK_FORM', $testName);
        }

        if ($this->params->get('logging', 0)) {
            $category = 'osan.' . ($testName ?: 'generic');
            Log::addLogger(['text_file' => static::LOG_FILE], Log::ALL, [$category]);
            Log::add(join('::', $caller), Log::NOTICE, $category);
        }

        if ($this->app->input->getCmd('format', 'html') == 'html') {
            switch (strtolower($method)) {
                case 'onafterinitialise':
                case 'onafterroute':
                case 'onafterrender':
                    $link = $this->app->input->server->get('HTTP_REFERER', '', 'URL') ?: Route::_('index.php');

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

    /**
     * Find all candidate forms for spam protection
     *
     * @param string $text
     *
     * @return array
     */
    protected function findForms(string $text): array
    {
        if ($this->forms === null) {
            $regexForm   = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
            $regexFields = '#<\s*(input|button).*?type\s*=["\']([^\'"]*)[^>]*>#sm';

            $this->forms = [];
            if (preg_match_all($regexForm, $text, $matches)) {
                foreach ($matches[0] as $idx => $form) {
                    $submit = 0;
                    $text   = 0;
                    if (preg_match_all($regexFields, $form, $fields)) {
                        foreach ($fields[1] as $fdx => $field) {
                            $fieldType = $fields[2][$fdx];

                            if ($fieldType == 'submit' || ($field == 'button' && $fieldType == 'submit')) {
                                $submit++;

                            } elseif (in_array($fieldType, $this->textFields)) {
                                $text++;
                            }
                        }
                    }

                    /*
                     * If a form has only one text field and no submit button,
                     * the form can be submitted by pressing enter/return key.
                     * Modifying the form for our purposes will break that
                     * behavior
                     */
                    $this->forms[] = new FormTags([
                        'source'  => $form,
                        'endTag'  => $matches[2][$idx],
                        'addText' => $text > 1 || $submit > 0
                    ]);
                }
            }
        }

        return $this->forms;
    }
}
