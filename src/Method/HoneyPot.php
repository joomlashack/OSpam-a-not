<?php
/**
 * @package    OSpam-a-not
 * @subpackage
 * @contact    www.joomlashack.com, help@joomlashack.com
 * @copyright  2015 Open Source Training, LLC. All rights reserved
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace Alledia\PlgSystemOspamanot\Method;

use \Exception;
use \JFactory;
use \JText;

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
     * @throws Exception
     * @return void
     */
    public function onAfterInitialise()
    {
        $app = JFactory::getApplication();

        if (in_array($app->input->getMethod(), array('GET', 'POST'))) {
            $secret = $this->getHashedFieldName();

            if (array_key_exists($secret, $_REQUEST)) {
                $failedTest = '***';
                $timeKey    = $app->input->get($secret);

                if (preg_match('/^\d+\.\d$/', $timeKey)) {
                    // timeGate field looks reasonable, find load time and honey pot index
                    list($startTime, $idx) = explode('.', $timeKey);

                    $timeGate = (float)$this->params->get('timeGate', 0);
                    if ($timeGate && (time() - $startTime) < $timeGate) {
                        // Failed timeGate
                        $failedTest = JText::_('PLG_SYSTEM_OSPAMANOT_BLOCK_TIMEGATE');

                    } else {
                        // Check the honey pot
                        $nameList = array_keys($this->honeyPots);
                        if (array_key_exists($idx, $nameList)) {
                            $honeyPot = $nameList[$idx];
                            if (isset($_REQUEST[$honeyPot]) && !$app->input->get($honeyPot)) {
                                // Honey pot passed
                                $this->checkUrl(array($secret, $honeyPot));
                                return;
                            }
                        }

                        // Failed the honey pot test
                        $failedTest = JText::_('PLG_SYSTEM_OSPAMANOT_BLOCK_HONEYPOT');
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
     * @throws Exception
     * @return void
     */
    public function onAfterRender()
    {
        if (JFactory::getUser()->guest) {
            $doc = JFactory::getDocument();

            if ($doc->getType() == 'html') {
                $app = JFactory::getApplication();

                $body = $app->getBody();

                if ($forms = $this->findForms($body)) {
                    foreach ($forms as $idx => $form) {
                        $this->addHiddenFields($body, $form->source, $form->endTag);
                    }
                    $app->setBody($body);
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
                $honeyPot = "<input type=\"text\" name=\"{$name}\" value=\"\"/>";
                $timeGate = "<input type=\"hidden\" name=\"{$secret}\" value=\"{$now}.{$idx}\"/>";
                $replace  = str_replace($endTag, $honeyPot . $timeGate . $endTag, $form);
                if ($replace != $form) {
                    $body = str_replace($form, $replace, $body);

                    if (!$this->honeyPots[$name]) {
                        preg_match('#<\s*/\s*head\s*>#', $body, $headTag);
                        $headTag = array_pop($headTag);

                        $css  = '<style type="text/css">input[name=' . $name . '] {display: none !important;}</style>';
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
        $config = JFactory::getConfig();

        $siteName = $config->get('sitename');
        $secret   = $config->get('secret');

        return md5($siteName . $secret);
    }

    /**
     * Find all candidate forms for spam protection
     *
     * @param $text
     *
     * @return array
     */
    protected function findForms($text)
    {
        $regexForm   = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
        $regexFields = '#<\s*(input|button).*?type\s*=["\']([^\'"]*)[^>]*>#sm';

        $forms = array();
        if (preg_match_all($regexForm, $text, $matches)) {
            foreach ($matches[0] as $idx => $form) {
                $submit = 0;
                $text   = 0;
                if (preg_match_all($regexFields, $form, $fields)) {
                    foreach ($fields[1] as $fdx => $field) {
                        $fieldType = $fields[2][$fdx];

                        if ($fieldType == 'submit' || ($field == 'button' && $fieldType == 'submit')) {
                            $submit++;
                        } elseif ($fieldType == 'text') {
                            $text++;
                        }
                    }
                }

                // Include form only if adding another text field won't break it
                if ($text > 1 || $submit > 0) {
                    $forms[] = (object)array(
                        'source' => $form,
                        'endTag' => $matches[2][$idx]
                    );
                }
            }
        }
        return $forms;
    }
}
