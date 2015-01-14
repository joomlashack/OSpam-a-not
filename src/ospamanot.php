<?php
/**
 * @package   OSpam-a-not
 * @contact   www.alledia.com, support@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

require_once 'include.php';

/**
 * Ospamanot Content Plugin
 *
 */
class PlgContentOspamanot extends AbstractPlugin
{
    public function __construct(&$subject, $config = array())
    {
        $this->namespace = 'Ospamanot';

        parent::__construct($subject, $config);
    }
}
