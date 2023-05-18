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
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class OsanFormFieldDownload extends FormField
{
    /**
     * @var string[]
     */
    protected $entries = null;

    /**
     * @inheritDoc
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $element['hiddenLabel'] = 'true';

        if (parent::setup($element, $value, $group)) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getInput()
    {
        return sprintf(
            '<div class="btn-group">%s%s</div>',
            $this->getDownloadButton(),
            $this->getClearButton()
        );
    }

    /**
     * @return string[]
     */
    protected function getEntries(): array
    {
        if ($this->entries === null) {
            $this->entries = AbstractMethod::getLogEntries();
        }

        return $this->entries;
    }

    /**
     * @return string
     */
    protected function getDownloadButton(): string
    {
        $count = max(0, count($this->getEntries()) - 1);
        $text  = Text::plural('PLG_SYSTEM_OSPAMANOT_LOG_DOWNLOAD', $count);

        if ($count) {
            return HTMLHelper::link(
                'index.php?option=com_ajax&group=system&plugin=osanDownload&format=raw',
                $text,
                [
                    'class' => 'btn btn-primary'
                ]
            );
        }

        return sprintf('<div class="btn btn-primary disabled">%s</div>', $text);
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getClearButton(): string
    {
        $count = max(0, count($this->getEntries()) - 1);

        if ($count) {
            $clearId = $this->id . '_clear';

            Factory::getApplication()->getDocument()->addScriptDeclaration($this->getClearScript($clearId));

            return sprintf(
                '<div id="%s" class="btn btn-danger">%s</div>',
                $clearId,
                Text::_('PLG_SYSTEM_OSPAMANOT_LOG_CLEAR')
            );
        }

        return '';
    }

    protected function getClearScript(string $id): string
    {
        Text::script('PLG_SYSTEM_OSPAMANOT_ERROR_UNKNOWN');
        Text::script('PLG_SYSTEM_OSPAMANOT_ERROR_SERVER');

        return <<<JSCRIPT
;jQuery(document).ready(function($) {
    $('#{$id}').on('click', function(evt) {
        evt.preventDefault();
        
        $.post('index.php', {
            option: 'com_ajax',
            group : 'system',
            plugin: 'osanClear',
            format: 'json'
        })
        .always(function(response, status) {
            console.log(response);
            
            if (status === 'success') {
                if (response.success) {
                    let message = response.data || [Joomla.JText._('PLG_SYSTEM_OSPAMANOT_ERROR_NORESPONSE')];
                    
                    alert(message.join("\\n"));
                    
                } else {
                    alert(response.message || Joomla.JText._('PLG_SYSTEM_OSPAMANOT_ERROR_UNKNOWN'));
                }
                
            } else {
                alert(Joomla.JText._('PLG_SYSTEM_OSPAMANOT_ERROR_SERVER'));
            }
        });
    });
});
JSCRIPT;
    }
}
