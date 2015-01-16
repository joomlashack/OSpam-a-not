<?php
/**
 * @package   OSpam-a-not
 * @contact   www.alledia.com, support@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Framework\Joomla\Extension\AbstractPlugin;

defined('_JEXEC') or die();

require_once 'include.php';

/**
 * Ospamanot Content Plugin
 *
 */
class PlgSystemOspamanot extends AbstractPlugin
{
    protected $honeyPots = array(
        'your_name_here'    => 0,
        'your_address_here' => 0,
        'my_name'           => 0,
        'my_address'        => 0
    );

    public function __construct(&$subject, $config = array())
    {
        $this->namespace = 'Ospamanot';

        parent::__construct($subject, $config);
    }

    public function onAfterInitialise()
    {
        $app = JFactory::getApplication();
        $message = array();

        if ($app->input->getMethod() == 'POST') {
            $secret = JFactory::getConfig()->get('secret');

            if (array_key_exists($secret, $_REQUEST)) {
                $timeKey = $app->input->getCmd($secret);

                list($startTime, $idx) = explode('.', $timeKey);

                if ((int)$startTime) {
                    $message[] = 'Time Lapse: ' . (time() - (int)$startTime) . ' seconds';

                    $nameList = array_keys($this->honeyPots);
                    if (array_key_exists($idx, $nameList)) {
                        if (!empty($_REQUEST[$nameList[$idx]])) {
                            $message[] = 'Possible Hack: Honey Pot not empty';
                        }
                    } else {
                        $message[] = 'Honey Pot is valid';
                    }

                } else {
                    $message[] = 'Possible Hack: Start time is not valid';
                }
            }
        }

        if ($message) {
            $app->enqueueMessage(join('<br/>', $message));
        }
    }

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

    protected function addHiddenFields(&$body, $form, $endtag)
    {
        foreach (array_keys($this->honeyPots) as $idx => $name) {
            if (stripos($form, $name) === false) {
                $secret   = JFactory::getConfig()->get('secret');
                $now      = time();
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
}
