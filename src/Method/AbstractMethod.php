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
            $caller[] = array_pop(explode('\\', $stack[1]['class']));
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
}
