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

namespace Alledia\Ospamanot\Filter;

use Alledia\Ospamanot\FormFilter;
use Alledia\Ospamanot\FormTags;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

// phpcs:enable PSR1.Files.SideEffects

abstract class AbstractFilterBase
{
    /**
     * @var FormFilter
     */
    protected $parent = null;

    /**
     * @return void
     */
    public function __construct(FormFilter $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getParam(string $key, $default = null)
    {
        return $this->parent->getParam($key, $default);
    }

    /**
     * @param FormTags $form
     *
     * @return bool
     */
    abstract public function exclude(FormTags $form): bool;
}
