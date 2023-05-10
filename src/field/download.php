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

// phpcs:disable PSR1.Files.SideEffects
use Alledia\Ospamanot\Method\AbstractMethod;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class OsanFormFieldDownload extends \Joomla\CMS\Form\FormField
{
    /**
     * @inheritDoc
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $element['hiddenLabel'] = 'true';

        return parent::setup($element, $value, $group);
    }

    /**
     * @inheritDoc
     */
    protected function getInput()
    {
        $entries = AbstractMethod::getLogEntries();
        $count   = max(0, count($entries) - 1);
        $text    = Text::plural('PLG_SYSTEM_OSPAMANOT_LOG_DOWNLOAD', $count);

        if ($count) {
            return HTMLHelper::link(
                'index.php?option=com_ajax&format=raw&group=system&plugin=osanDownload',
                $text,
                [
                    'class' => 'btn btn-primary'
                ]
            );
        }

        return sprintf('<button type="button" class="btn btn-primary disabled">%s</button>', $text);
    }
}
