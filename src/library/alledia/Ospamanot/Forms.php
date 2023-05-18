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

namespace Alledia\Ospamanot;

// phpcs:disable PSR1.Files.SideEffects

defined('_JEXEC') or die();

// phpcs:enable PSR1.Files.SideEffects

final class Forms implements \Iterator
{
    /**
     * @var FormTags[]
     */
    protected $forms = null;

    /**
     * @var string[] HTML5 text fields
     */
    protected $textFields = [
        'email',
        'number',
        'password',
        'search',
        'tel',
        'text',
        'url'
    ];

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @param string $text
     *
     * @return void
     */
    public function __construct(string $text)
    {
        $this->loadForms($text);
    }

    /**
     * Find all candidate forms for spam protection
     *
     * @param string $text
     *
     * @return void
     */
    protected function loadForms(string $text): void
    {
        $regexForm   = '#(<\s*form.*?>).*?(<\s*/\s*form\s*>)#sm';
        $regexFields = '#<\s*(input|button).*?type\s*=["\']([^\'"]*)[^>]*>#sm';
        $regexOther  = '#<\s*(textarea|select).*?>.*</\1>#';
        $formFilter  = Filters::getInstance();

        $this->forms = [];
        if (preg_match_all($regexForm, $text, $matches)) {
            foreach ($matches[0] as $idx => $form) {
                $buttonCount = 0;
                $fieldCount  = 0;
                if (preg_match_all($regexFields, $form, $fields)) {
                    foreach ($fields[1] as $fdx => $field) {
                        $fieldType = $fields[2][$fdx];

                        if ($field == 'button' && $fieldType == 'submit') {
                            $buttonCount++;

                        } elseif (in_array($fieldType, $this->textFields)) {
                            $fieldCount++;
                        }
                    }
                } elseif (preg_match_all($regexOther, $form, $other)) {
                    $fieldCount += count($other[0]);
                }

                $form = new FormTags([
                    'source'      => $form,
                    'endTag'      => $matches[2][$idx],
                    'fieldCount'  => $fieldCount,
                    'buttonCount' => $buttonCount
                ]);
                if ($formFilter->exclude($form) == false) {
                    $this->forms[] = $form;
                }
            }
        }
    }

    /**
     * @return ?FormTags
     */
    public function current(): ?FormTags
    {
        if ($this->valid()) {
            return $this->forms[$this->position];
        }

        return null;
    }

    /**
     * @return ?FormTags
     */
    public function next(): ?FormTags
    {
        $this->position++;
        if ($this->position < count($this->forms)) {
            return $this->forms[$this->position];
        }

        return null;
    }

    /**
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->forms[$this->position]);
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->rewind();
    }
}
