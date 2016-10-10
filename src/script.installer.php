<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, support@joomlashack.com
 * @copyright 2015 joomlashack.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Installer\AbstractScript;

defined('_JEXEC') or die();

require_once 'library/Installer/include.php';

class plgsystemospamanotInstallerScript extends AbstractScript
{
    /**
     * @param string                     $type
     * @param JInstallerAdapterComponent $parent
     *
     * @return void
     */
    public function postFlight($type, $parent)
    {
        parent::postFlight($type, $parent);

        if (stripos($type, 'install') === false) {
            $this->reorderThisPlugin();
        }
    }
}
