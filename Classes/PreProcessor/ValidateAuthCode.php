<?php

declare(strict_types=1);

namespace Typoheads\Formhandler\PreProcessor;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
 * A pre processor validating an auth code generated by Finisher_GenerateAuthCode.
 */
class ValidateAuthCode extends AbstractPreProcessor {
  /**
   * The main method called by the controller.
   *
   * @return array<string, mixed> The probably modified GET/POST parameters
   */
  public function process(): array {
    $authCode = trim(strval($this->gp['authCode'] ?? ''));
    if (!empty($authCode)) {
      try {
        $table = trim(strval($this->gp['table'] ?? ''));
        if ($this->settings['table']) {
          $table = $this->utilityFuncs->getSingle($this->settings, 'table');
        }
        $uidField = trim(strval($this->gp['uidField'] ?? ''));
        if ($this->settings['uidField']) {
          $uidField = $this->utilityFuncs->getSingle($this->settings, 'uidField');
        }
        if (empty($uidField)) {
          $uidField = 'uid';
        }
        $uid = trim(strval($this->gp['uid'] ?? ''));

        if (!(strlen($table) > 0 && strlen($uid) > 0)) {
          $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
        }

        /** @var ConnectionPool ConnectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();

        // Check if table is valid
        if (!$connection->getSchemaManager()->tablesExist([$table])) {
          $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
        }

        // Check if uidField is valid
        $tableColumns = $connection->getSchemaManager()->listTableColumns($table);
        $existingFields = [];
        foreach ($tableColumns as $column) {
          $existingFields[] = strtolower($column->getName());
        }
        if (!in_array(strtolower($uidField), $existingFields, true)) {
          $this->utilityFuncs->throwException('validateauthcode_insufficient_params');
        }

        $hiddenField = 'disable';
        if ($this->settings['hiddenField']) {
          $hiddenField = $this->utilityFuncs->getSingle($this->settings, 'hiddenField');
        } elseif ($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disable']) {
          $hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disable'];
        }
        $selectFields = '*';
        if ($this->settings['selectFields']) {
          $selectFields = $this->utilityFuncs->getSingle($this->settings, 'selectFields');
        }
        $queryBuilder
          ->select(...explode(',', $selectFields))
          ->from($table)
        ;

        $hiddenStatusValue = 1;
        if (isset($this->settings['hiddenStatusValue'])) {
          $hiddenStatusValue = $this->utilityFuncs->getSingle($this->settings, 'hiddenStatusValue');
        }
        if (1 !== (int) $this->utilityFuncs->getSingle($this->settings, 'showDeleted')) {
          // Enable fields

          /** @var FrontendRestrictionContainer $frontendRestrictionContainer */
          $frontendRestrictionContainer = GeneralUtility::makeInstance(FrontendRestrictionContainer::class);
          $queryBuilder->setRestrictions($frontendRestrictionContainer);
          $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        } else {
          $queryBuilder->getRestrictions()->removeAll();
        }

        $queryBuilder->where(
          $queryBuilder->expr()->eq($uidField, $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
          $queryBuilder->expr()->eq($hiddenField, $queryBuilder->createNamedParameter($hiddenStatusValue, \PDO::PARAM_INT))
        );

        $this->utilityFuncs->debugMessage('sql_request', [$queryBuilder->getSQL()]);

        $stmt = $queryBuilder->executeQuery();
        if (0 === $stmt->rowCount()) {
          $this->utilityFuncs->throwException('validateauthcode_no_record_found');
        }

        $row = $stmt->fetchAssociative();
        $this->utilityFuncs->debugMessage('Selected row: ', [], 1, $row ?: []);

        $localAuthCode = GeneralUtility::hmac(serialize($row), 'formhandler');

        $this->utilityFuncs->debugMessage('Comparing auth codes: ', [], 1, ['Calculated:' => $localAuthCode, 'Given:' => $authCode]);
        if ($localAuthCode !== $authCode) {
          $this->utilityFuncs->throwException('validateauthcode_invalid_auth_code');
        }
        $activeStatusValue = 0;
        if (isset($this->settings['activeStatusValue'])) {
          $activeStatusValue = $this->utilityFuncs->getSingle($this->settings, 'activeStatusValue');
        }
        $connection->update($table, [$hiddenField => $activeStatusValue], [$uidField => $uid]);

        $this->utilityFuncs->doRedirectBasedOnSettings($this->settings, $this->gp);
      } catch (\Exception $e) {
        $redirectPage = $this->utilityFuncs->getSingle($this->settings, 'errorRedirectPage');
        if ($redirectPage) {
          $this->utilityFuncs->doRedirectBasedOnSettings($this->settings, $this->gp, 'errorRedirectPage');
        } else {
          throw new \Exception($e->getMessage());
        }
      }
    }

    return $this->gp;
  }
}
