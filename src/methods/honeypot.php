<?php
/**
 * @package    OSpam-a-not
 * @subpackage
 * @contact    www.ostraining.com, support@ostraining.com
 * @copyright  2014 Open Source Training, LLC. All rights reserved
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

class PlgSystemOspamanotHoneypot extends AbstractPlugin
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
                $timeKey = $app->input->getCmd($secret);

                if (strpos($timeKey, '.') !== false) {
                    list($startTime, $idx) = explode('.', $timeKey);

                    if ((int)$startTime) {
                        $nameList = array_keys($this->honeyPots);
                        if (array_key_exists($idx, $nameList)) {
                            if (!empty($_REQUEST[$nameList[$idx]])) {
                                $this->_subject->trigger('onSpamanotBlock', array(__METHOD__));
                            }
                        }
                    } else {
                        $this->_subject->trigger('onSpamanotBlock', array(__METHOD__));
                    }
                } else {
                    $this->_subject->trigger('onSpamanotBlock', array(__METHOD__));
                }
            }
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

                $body = $j2 ? JResponse::getBody() : $app->getBody();

                $regex = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
                if (preg_match_all($regex, $body, $matches)) {
                    foreach ($matches[0] as $idx => $form) {
                        $this->addHiddenFields($body, $form, $matches[2][$idx]);
                    }
                    $j2 ? JResponse::setBody($body) : $app->setBody($body);
                }

            }
        }
    }

    /**
     * Adds the timeGate/Honeypot fields and hides them using css in <head> tag
     *
     * @param string $body
     * @param string $form
     * @param string $endtag
     *
     * @return void
     */
    protected function addHiddenFields(&$body, $form, $endtag)
    {
        foreach (array_keys($this->honeyPots) as $idx => $name) {
            if (stripos($form, $name) === false) {
                $now    = time();
                $secret = $this->getHashedFieldName();

                $honeyPot = "<input type=\"text\" name=\"{$name}\" value=\"\"/>";
                $timeGate = "<input type=\"hidden\" name=\"{$secret}\" value=\"{$now}.{$idx}\"/>";
                $replace  = str_replace($endtag, $honeyPot . $timeGate . $endtag, $form);
                if ($replace != $form) {
                    $body = str_replace($form, $replace, $body);

                    if (!$this->honeyPots[$name]) {
                        $css  = '<style type="text/css">input[name=' . $name . '] {display: none;}</style>';
                        $body = str_replace('</head>', "\n" . $css . "\n</head>", $body);
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
