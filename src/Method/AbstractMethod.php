<?php
/**
 * @package    OSpam-a-not
 * @subpackage
 * @contact    www.ostraining.com, support@ostraining.com
 * @copyright  2014 Open Source Training, LLC. All rights reserved
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace Alledia\PlgSystemOspamanot\Method;

use \Exception;
use \JFactory;
use \JText;
use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

abstract class AbstractMethod extends AbstractPlugin
{
    /**
     * Standard response for use by subclasses that want to block the user for any reason
     *
     * @param string $message
     * @param string $method
     *
     * @throws \Exception
     * @return void
     */
    protected function block($message = null, $method = null)
    {
        if ($method === null) {
            $stack = debug_backtrace();
            if (!empty($stack[1]['function'])) {
                $method = $stack[1]['function'];
            }

        } elseif (strpos($method, '::') !== false) {
            $method = array_pop(explode('::', $method));

        } elseif (empty($message)) {
            $message = JText::_('PLG_SYSTEM_OSPAMANOT_BLOCK_GENERIC');
        }

        switch (strtolower($method)) {
            case 'onafterinitialise':
            case 'onafterroute':
            case 'onafterrender':
                JFactory::getApplication()->redirect('index.php', $message);
                break;

            default:
                throw new Exception($message, 403);
        }
    }
}
