<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\Validator\ErrorCheck;

/**
 * This script is part of the TYPO3 project - inspiring people to share!
 *
 * TYPO3 is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by
 * the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 */

/**
 * Validates that a specified field is an array and has an item count between two specified values.
 */
class BetweenItems extends AbstractErrorCheck {
  public function check(): string {
    $checkFailed = '';
    $min = (int) ($this->utilityFuncs->getSingle($this->settings['params'], 'minValue'));
    $max = (int) ($this->utilityFuncs->getSingle($this->settings['params'], 'maxValue'));
    $removeEmptyValues = $this->utilityFuncs->getSingle($this->settings['params'], 'removeEmptyValues');
    if (isset($this->gp[$this->formFieldName]) && is_array($this->gp[$this->formFieldName])) {
      $valuesArray = $this->gp[$this->formFieldName];
      if (1 === (int) $removeEmptyValues) {
        foreach ($valuesArray as $key => $fieldName) {
          if (empty($fieldName)) {
            unset($valuesArray[$key]);
          }
        }
      }
      if (count($valuesArray) < $min || count($valuesArray) > $max) {
        $checkFailed = $this->getCheckFailed();
      }
    } elseif ($min > 0) {
      $checkFailed = $this->getCheckFailed();
    }

    return $checkFailed;
  }

  /**
   * @param array<string, mixed> $gp       The get/post parameters
   * @param array<string, mixed> $settings An array with settings
   */
  public function init(array $gp, array $settings): void {
    parent::init($gp, $settings);
    $this->mandatoryParameters = ['minValue', 'maxValue'];
  }
}
