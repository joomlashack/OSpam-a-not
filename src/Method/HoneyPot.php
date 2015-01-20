<?php
/**
 * @package    OSpam-a-not
 * @subpackage
 * @contact    www.ostraining.com, support@ostraining.com
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
                $timeKey = $app->input->get($secret);

                if (preg_match('/^\d+\.\d$/', $timeKey)) {
                    // timeGate field looks reasonable, find load time and honey pot index
                    list($startTime, $idx) = explode('.', $timeKey);

                    $timeGate = (float)$this->params->get('timeGate', 0);
                    if (!$timeGate || (time() - $startTime) > $timeGate) {
                        // timeGate test ignored or passed
                        $nameList = array_keys($this->honeyPots);
                        if (array_key_exists($idx, $nameList)) {
                            $honeyPot = $nameList[$idx];
                            if (isset($_REQUEST[$honeyPot]) && !$app->input->get($honeyPot)) {
                                // Honey pot passed
                                return;
                            }
                        }
                    }
                }

                // Failed timeGate/HoneyPot tests
                $this->block(JText::_('PLG_SYSTEM_OSPAMANOT_BLOCK_FORM'));
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
                $j2  = !method_exists($app, 'getBody');

                $body = $j2 ? \JResponse::getBody() : $app->getBody();

                $regex = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
                if (preg_match_all($regex, $body, $matches)) {
                    foreach ($matches[0] as $idx => $form) {
                        $this->addHiddenFields($body, $form, $matches[2][$idx]);
                    }
                    $j2 ? \JResponse::setBody($body) : $app->setBody($body);
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
                preg_match('#<\s*/\s*head\s*>#', $body, $headTag);
                $headTag = array_pop($headTag);
                $secret  = $this->getHashedFieldName();

                $now      = time();
                $honeyPot = "<input type=\"text\" name=\"{$name}\" value=\"\"/>";
                $timeGate = "<input type=\"hidden\" name=\"{$secret}\" value=\"{$now}.{$idx}\"/>";
                $replace  = str_replace($endTag, $honeyPot . $timeGate . $endTag, $form);
                if ($replace != $form) {
                    $body = str_replace($form, $replace, $body);

                    if (!$this->honeyPots[$name]) {
                        $css  = '<style type="text/css">input[name=' . $name . '] {display: none;}</style>';
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
}
