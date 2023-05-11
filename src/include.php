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

use Alledia\Framework\AutoLoader;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

if (Factory::getApplication()->input->getCmd('option') == 'com_installer') {
    // Avoid conflicts during installation
    return false;
}

try {
    $frameworkPath = JPATH_SITE . '/libraries/allediaframework/include.php';
    if (is_file($frameworkPath) == false || (include $frameworkPath) == false) {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            $app->enqueueMessage('[OSpam-a-not] Joomlashack framework not found', 'error');
        }

        return false;
    }

    if (defined('ALLEDIA_FRAMEWORK_LOADED') && !defined('OSPAMANOT_LOADED')) {
        define('OSPAMANOT_LOADED', true);

        AutoLoader::register('Alledia\\Ospamanot', __DIR__ . '/library/Ospamanot');
    }

} catch (Throwable $error) {
    Factory::getApplication()
        ->enqueueMessage('[OSpam-a-not] Unable to initialize: ' . $error->getMessage(), 'error');

    return false;
}

return defined('ALLEDIA_FRAMEWORK_LOADED') && defined('OSPAMANOT_LOADED');
