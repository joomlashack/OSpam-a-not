<?php
/**
 * @package   OSpam-a-not
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2020 Joomlashack.com. All rights reserved
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

namespace Alledia\PlgSystemOspamanot\Method;

use Alledia\Framework\Factory;
use Exception;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die();

class HoneyPot extends AbstractMethod
{
    /**
     * @var string
     */
    protected $namespace = 'Ospamanothoneypot';

    /**
     * @var array A collection of reasonable sounding field names
     */
    protected $honeyPots = array(
        'my_name'           => 0,
        'your_name'         => 0,
        'your_name_here'    => 0,
        'my_address'        => 0,
        'your_address'      => 0,
        'your_address_here' => 0
    );

    /**
     * Check the timeGate/Honeypot fields if they exist
     *
     * @return void
     * @throws Exception
     */
    public function onAfterInitialise()
    {
        if (in_array($this->app->input->getMethod(), array('GET', 'POST'))) {
            $secret = $this->getHashedFieldName();

            if (array_key_exists($secret, $_REQUEST)) {
                $failedTest = '***';
                $timeKey    = $this->app->input->get($secret);

                if (preg_match('/^\d+\.\d$/', $timeKey)) {
                    // timeGate field looks reasonable, find load time and honey pot index
                    list($startTime, $idx) = explode('.', $timeKey);

                    $timeGate = (float)$this->params->get('timeGate', 0);
                    if ($timeGate && (time() - $startTime) < $timeGate) {
                        // Failed timeGate
                        $failedTest = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_TIMEGATE');

                    } else {
                        // Check the honey pot
                        $nameList = array_keys($this->honeyPots);
                        if (array_key_exists($idx, $nameList)) {
                            $honeyPot = $nameList[$idx];
                            if (isset($_REQUEST[$honeyPot]) && $_REQUEST[$honeyPot] === '') {
                                // Honey pot passed
                                $this->checkUrl(array($secret, $honeyPot));
                                return;
                            }
                        }

                        // Failed the honey pot test
                        $failedTest = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_HONEYPOT');
                    }
                }

                // Failed timeGate/HoneyPot tests
                $this->block($failedTest);
            }

            // @TODO: does it make sense to consider an unprotected form as a hack attempt?
        }
    }

    /**
     * if not logged in on an html page, add the timeGate/Honeypot fields to any forms found
     *
     * @return void
     */
    public function onAfterRender()
    {
        if (Factory::getUser()->guest) {
            $doc = Factory::getDocument();

            if ($doc->getType() == 'html') {
                $body = $this->app->getBody();

                if ($forms = $this->findForms($body)) {
                    foreach ($forms as $idx => $form) {
                        $this->addHiddenFields($body, $form->source, $form->endTag);
                    }
                    $this->app->setBody($body);
                }
            }
        }
    }

    /**
     * Adds the timeGate/Honeypot fields and hides them using css in <head> tag
     *
     * @param string $body
     * @param string $form
     * @param string $endTag
     *
     * @return void
     */
    protected function addHiddenFields(&$body, $form, $endTag)
    {
        foreach (array_keys($this->honeyPots) as $idx => $name) {
            if (stripos($form, $name) === false) {
                $secret = $this->getHashedFieldName();

                $now      = time();
                $honeyPot = sprintf('<input type="text" name="%s" value=""/>', $name);
                $timeGate = sprintf('<input type="hidden" name="%s" value="%s"/>', $secret, $now . $idx);
                $replace  = str_replace($endTag, $honeyPot . $timeGate . $endTag, $form);

                if ($replace != $form) {
                    $body = str_replace($form, $replace, $body);

                    if (!$this->honeyPots[$name]) {
                        preg_match('#<\s*/\s*head\s*>#', $body, $headTag);
                        $headTag = array_pop($headTag);

                        $css  = sprintf(
                            '<style type="text/css">input[name=\'%s\'] {display: none !important;}</style>',
                            $name
                        );
                        $body = str_replace($headTag, "\n" . $css . "\n" . $headTag, $body);
                    }
                    $this->honeyPots[$name]++;
                }

                return;
            }
        }
    }

    /**
     * Create a hashed field name that we can recognize as the timeGate field
     *
     * @return string
     */
    protected function getHashedFieldName()
    {
        $config = Factory::getConfig();

        $siteName = $config->get('sitename');
        $secret   = $config->get('secret');

        return md5($siteName . $secret);
    }
}
