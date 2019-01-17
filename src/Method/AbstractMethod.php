<?php
/**
 * @package    OSpam-a-not
 * @subpackage
 * @contact    www.joomlashack.com, help@joomlashack.com
 * @copyright  2015 Open Source Training, LLC. All rights reserved
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace Alledia\PlgSystemOspamanot\Method;

use Exception;
use JFactory;
use JLog;
use JRoute;
use JText;
use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

abstract class AbstractMethod extends AbstractPlugin
{
    /**
     * @var array
     */
    protected $forms = null;

    /**
     * Standard response for use by subclasses that want to block the user for any reason
     *
     * @param string $testName
     *
     * @throws \Exception
     * @return void
     */
    protected function block($testName = null)
    {
        $stack  = debug_backtrace();
        $caller = array();
        $method = null;
        if (!empty($stack[1]['class'])) {
            $classParts = explode('\\', $stack[1]['class']);
            $caller[]   = array_pop($classParts);
        }

        if (!empty($stack[1]['function'])) {
            $caller[] = $stack[1]['function'];
            $method   = $stack[1]['function'];
        }

        if (!$testName) {
            $message = JText::_('PLG_SYSTEM_OSPAMANOT_BLOCK_GENERIC');
        } else {
            $message = JText::sprintf('PLG_SYSTEM_OSPAMANOT_BLOCK_FORM', $testName);
        }

        if ($this->params->get('logging', 0)) {
            JLog::addLogger(array('text_file' => 'ospamanot.log.php'), JLog::ALL);
            JLog::add(join('::', $caller), JLog::NOTICE, $testName);
        }

        switch (strtolower($method)) {
            case 'onafterinitialise':
            case 'onafterroute':
            case 'onafterrender':
                $app = JFactory::getApplication();

                $link = $app->input->server->get('HTTP_REFERER', '', 'URL') ?: JRoute::_('index.php');

                $app->enqueueMessage($message, 'error');
                $app->redirect(JRoute::_($link));
                break;

            default:
                throw new Exception($message, 403);
        }
    }

    /**
     * Check the current url for fields that might have been improperly
     * introduced in the URL and remove if present
     *
     * @param string[] $fields
     *
     * @return void
     * @throws Exception
     */
    protected function checkUrl(array $fields)
    {
        $uri   = \JUri::getInstance();
        $query = $uri->getQuery(true);
        foreach ($fields as $field) {
            if (isset($query[$field])) {
                $uri->delVar($field);
            }
        }

        if ($query != $uri->getQuery(true)) {
            JFactory::getApplication()->redirect($uri);
        }
    }

    /**
     * Find all candidate forms for spam protection
     *
     * @param $text
     *
     * @return array
     */
    protected function findForms($text)
    {
        if ($this->forms === null) {
            $regexForm   = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
            $regexFields = '#<\s*(input|button).*?type\s*=["\']([^\'"]*)[^>]*>#sm';

            $this->forms = array();
            if (preg_match_all($regexForm, $text, $matches)) {
                foreach ($matches[0] as $idx => $form) {
                    $submit = 0;
                    $text   = 0;
                    if (preg_match_all($regexFields, $form, $fields)) {
                        foreach ($fields[1] as $fdx => $field) {
                            $fieldType = $fields[2][$fdx];

                            if ($fieldType == 'submit' || ($field == 'button' && $fieldType == 'submit')) {
                                $submit++;
                            } elseif ($fieldType == 'text') {
                                $text++;
                            }
                        }
                    }

                    // Include form only if adding another text field won't break it
                    if ($text > 1 || $submit > 0) {
                        $this->forms[] = (object)array(
                            'source' => $form,
                            'endTag' => $matches[2][$idx]
                        );
                    }
                }
            }
        }

        return $this->forms;
    }
}
