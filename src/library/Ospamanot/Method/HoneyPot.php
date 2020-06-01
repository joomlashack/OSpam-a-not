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

namespace Alledia\Ospamanot\Method;

use Alledia\Framework\Factory;
use Alledia\Ospamanot\FormTags;
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
                $failMessage = null;
                $timeKey     = $this->app->input->get($secret);

                if (preg_match('/^\d+(?:\.\d)?$/', $timeKey)) {
                    // Our secret field was added, check response time
                    $atoms     = explode('.', $timeKey);
                    $startTime = array_shift($atoms);
                    $idx       = $atoms ? array_shift($atoms) : null;

                    $timeGate = (float)$this->params->get('timeGate', 0);
                    if ($timeGate && (time() - $startTime) < $timeGate) {
                        // Failed timeGate
                        $failMessage = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_TIMEGATE');

                    } elseif ($idx !== null) {
                        // Honey pot was also added, check it out
                        $nameList = array_keys($this->honeyPots);

                        if (array_key_exists($idx, $nameList)) {
                            $honeyPot = $nameList[$idx];
                            if (isset($_REQUEST[$honeyPot]) && $_REQUEST[$honeyPot] === '') {
                                // Honey pot passed
                                $this->checkUrl(array($secret, $honeyPot));

                            } else {
                                // Failed the honey pot
                                $failMessage = Text::_('PLG_SYSTEM_OSPAMANOT_BLOCK_HONEYPOT');
                            }
                        }
                    }

                    if ($failMessage) {
                        $this->block($failMessage);
                    }
                }
            }
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
                        if ($form->addText) {
                            $this->addHiddenFields($body, $form);
                        }
                    }
                    $this->app->setBody($body);
                }
            }
        }
    }

    /**
     * Adds the timeGate/Honeypot fields and hides them using css in <head> tag
     *
     * @param string   $body
     * @param FormTags $form
     *
     * @return void
     */
    protected function addHiddenFields(&$body, FormTags $form)
    {
        $numbers = range(0, count($this->honeyPots) - 1);
        shuffle($numbers);

        foreach ($numbers as $idx) {
            $name = array_slice(array_keys($this->honeyPots), $idx, 1);
            $name = array_pop($name);

            if (stripos($form->source, $name) === false) {
                $secret = $this->getHashedFieldName();

                $secretValue = time();
                $honeyPot    = '';
                if ($form->addText) {
                    $secretValue .= '.' . $idx;
                    $honeyPot    = sprintf('<input type="text" name="%s" value=""/>', $name);
                }
                $timeGate = sprintf('<input type="hidden" name="%s" value="%s"/>', $secret, $secretValue);
                $replace  = str_replace($form->endTag, $honeyPot . $timeGate . $form->endTag, $form->source);

                if ($replace != $form->source) {
                    $body = str_replace($form->source, $replace, $body);

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
