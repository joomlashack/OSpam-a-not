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

use Alledia\Framework\Joomla\Extension\AbstractPlugin;
use Alledia\Ospamanot\Filters;
use Alledia\Ospamanot\Method\AbstractMethod;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

if (include __DIR__ . '/include.php') {
    class PlgSystemOspamanot extends AbstractPlugin
    {
        /**
         * @inheritdoc
         */
        protected $namespace = 'Ospamanot';

        /**
         * @inheritdoc
         */
        protected $autoloadLanguage = true;

        /**
         * @var CMSApplication
         */
        protected $app = null;

        /**
         * @inheritDoc
         */
        public function __construct($subject, $config = [])
        {
            parent::__construct($subject, $config);

            if ($this->app->isClient('site') && Factory::getUser()->guest) {
                // We only care about guest users on the frontend
                AbstractMethod::registerMethods($subject, $config);
            }
        }

        /**
         * @param Form  $form
         * @param array $data
         *
         * @return void
         * @deprecated Eventually should move to onContentValidateData
         */
        public function onUserBeforeDataValidation($form, $data)
        {
            $this->onContentValidateData($form, $data);
        }

        /**
         * @param Form         $form
         * @param array|object $data
         *
         * @return void
         */
        public function onContentValidateData($form, $data): void
        {
            $this->updateForm($form, $data);
        }

        /**
         * @param Form         $form
         * @param object|array $data
         *
         * @return void
         */
        public function onContentPrepareForm($form, $data): void
        {
            $this->updateForm($form, $data);
        }

        /**
         * @param Form         $form
         * @param object|array $data
         *
         * @return void
         */
        protected function updateForm(Form $form, $data): void
        {
            $data = new Registry($data);

            if (
                $form->getName() == 'com_plugins.plugin'
                && $data->get('folder') == $this->_type
                && $data->get('element') == $this->_name
            ) {
                $filterForms = Filters::getInstance()->getAdminForms();
                foreach ($filterForms as $filterForm) {
                    $this->mergeXML($form->getXml(), $filterForm);
                }
            }
        }

        /**
         * @param SimpleXMLElement $base
         * @param SimpleXMLElement $add
         *
         * @return void
         */
        protected function mergeXML(SimpleXMLElement $base, SimpleXMLElement $add)
        {
            $new = $base->addChild($add->getName());
            foreach ($add->attributes() as $a => $b) {
                $new[$a] = $b;
            }
            if ($add->count()) {
                foreach ($add->children() as $child) {
                    $this->mergeXML($new, $child);
                }

            } else {
                $new[0] = $add[0];
            }
        }

        /**
         * @return void
         */
        public function onAjaxOsanDownload()
        {
            error_reporting(0);
            ini_set('display_errors', 0);

            $entries  = AbstractMethod::getLogEntries();
            $fileName = basename(AbstractMethod::LOG_FILE, '.php');

            header('Content-Type: text/plain');
            header(sprintf('Content-Disposition: attachment; filename="%s"', $fileName));

            echo join('', $entries);

            jexit();
        }

        /**
         * @return string
         */
        public function onAjaxOsanClear(): string
        {
            $errorReporting = error_reporting(-1);
            $displayErrors  = ini_set('display_errors', 1);
            ob_start();

            $logPath = $this->app->get('log_path') . '/' . AbstractMethod::LOG_FILE;
            if (is_file($logPath)) {
                unlink($logPath);
            }

            $errors = ob_get_contents();
            ob_end_clean();

            error_reporting($errorReporting);
            ini_set('display_errors', $displayErrors);

            return $errors ?: Text::_('PLG_SYSTEM_OSPAMANOT_LOG_CLEAR_SUCCESS');
        }
    }
}
