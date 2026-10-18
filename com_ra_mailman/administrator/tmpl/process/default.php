<?php

/**
 * If invoked from the dataload template in validate mode, it invokes LoadHelper::scanFile
 * If invoked from the dataload template in process mode, it invokes LoadHelper::processFile
 * 10/10/24 CB created
 * 04/05/25 CB cater for update of file name on upload
 * 26/05/25 CB import report
 * 22/09/26 CB extensively refactored
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\LoadHelper;

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
 $loader = new LoadHelper;
/*
 * The validation pass uses the header-driven loader. Pass two is invoked
 * through LoadHelper so this template contains no persistence dependency.
 */
if ((string) $this->processing === '0') {

    $dataTypeLabels = [
        '3' => 'Members list from corporate feed',
        '4' => 'MailChimp export',
        '5' => 'Simple CSV file',
    ];
    $selectedType = $dataTypeLabels[(string) $this->method_id] ?? 'Unknown file type';

    try {
        $scan = $loader->scanFile($this->working_file, (int) $this->method_id, (int) $this->list_id);
    } catch (\Throwable $exception) {
        Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
        echo '<p>File must be reloaded, or return to the previous form and correct the file type option.</p>';
        echo '<p>File type selected: <strong>' . htmlspecialchars($selectedType, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        $target = 'administrator/index.php?option=com_ra_mailman&view=dataload';
        echo $this->toolsHelper->buildButton($target, 'Return to DataLoad form', false, 'granite');
        $target = 'administrator/index.php?option=com_ra_mailman&task=dataload.cancel';
        echo $this->toolsHelper->buildButton($target, 'Return to Dashboard', false, 'granite');
        return;
    }

    echo '<h2>Validating CSV</h2>';
    echo '<p>' . count($scan['rows']) . ' valid data rows found.</p>';

    if ($scan['errors'] !== []) {
        echo '<h3>' . count($scan['errors']) . ' row(s) require attention</h3><ul>';

        foreach ($scan['errors'] as $error) {
            $messages = htmlspecialchars(implode(' ', $error['messages']), ENT_QUOTES, 'UTF-8');
            echo '<li>Line ' . (int) $error['line'] . ': ' . $messages . '</li>';
        }

        echo '</ul>';
        echo '<p>File must be reloaded, or return to the previous form and correct the file type option.</p>';
        echo '<p>File type selected: <strong>' . htmlspecialchars($selectedType, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<p>These rows will be skipped if you continue; subsequent valid rows can still be processed.</p>';
    }

    echo '<p>If you continue, updates will be applied to the database.</p>';
    $target = 'administrator/index.php?option=com_ra_mailman&view=dataload';
    echo $this->toolsHelper->buildButton($target, 'Return to DataLoad form', false, 'granite');
    $target = 'administrator/index.php?option=com_ra_mailman&task=dataload.cancel';
    echo $this->toolsHelper->buildButton($target, 'Return to Dashboard', false, 'granite');
    $target = 'administrator/index.php?option=com_ra_mailman&task=dataload.continue';
    echo $this->toolsHelper->buildButton($target, 'Continue', false, 'red');
    return;
}

$response = $loader->processFile([
    'method_id' => $this->method_id,
    'list_id' => $this->list_id,
    'processing' => $this->processing,
    'filename' => $this->working_file,
    'report_id' => $this->report_id,
]);

// Redirect as appropriate
if ($response === true) {
    if ($this->processing == '0') {
        echo 'If you continue, updates will be applied to the database.<br>';
        if ($this->method_id == '3') {
            $count = $this->toolsHelper->getValue('SELECT COUNT(id) FROM #__users');
            $message = 'Total number of existing Users=' . $count . '<br>';

            $sql = 'SELECT COUNT(id) FROM #__ra_mail_subscriptions ';
            $sql .= 'WHERE list_id=' . $this->list_id;
            $count = $this->toolsHelper->getValue($sql);
            $message .= 'Total number of existing Subscriptions to this list=' . $count . '<br>';

            $message .= 'Existing members not present on this file will be ';
            $members_leave = ComponentHelper::getParams('com_ra_mailman')->get('members_leave');
            if ($members_leave == 'B') {
                $message .= '<b>Archived</b>, and all their subscriptions will be cancelled';
            } else {

                $message .= '<b>Purged</b>, as will all their subscriptions';
            }
            echo $message . '<br>';
        }
        $target = 'administrator/index.php?option=com_ra_mailman&view=dataload';
        echo $this->toolsHelper->buildButton($target, 'Cancel', False, 'granite');
        $target = 'administrator/index.php?option=com_ra_mailman&task=dataload.continue';
        echo $this->toolsHelper->buildButton($target, 'Continue', False, 'red');
    } else {
        // Flush the data from the session..
        Factory::getApplication()->setUserState('com_ra_mailman.edit.upload.data', null);

        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($target);
    }
} else {
    $target = 'administrator/index.php?option=com_ra_mailman&view=dataload';
    echo $this->toolsHelper->backButton($target);
}
