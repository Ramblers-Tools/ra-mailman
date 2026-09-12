<?php

/**
 * @version    4.4.10
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/07/25 CB created
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$this->record_count = 0;

echo 'File is ' . $this->working_file . '<br>';

$handle = fopen($this->working_file, "r");
if ($handle == 0) {
    echo 'Unable to open ' . $this->working_file . '<br>';
    $this->success = false;
    return $this->success;
}
$records = [];
$max = 0;
//echo 'File ' . $this->working_file . ' opened OK<br>';
for ($i = 0; $i < 5; $i++) {
    $line = fgets($handle);
    echo 'Line ' . $i . ': ';
    if (str_contains($line, ',')) {
        $fields = explode(',', $line);
        $records[] = $fields;
        echo count($fields) . ' fields<br>';
        if (count($fields) > $max) {
            $max = count($fields);
        }
        if ($i == 0) {
            if (count($fields) == 1) {
                echo '... ' . "Array has only one entry" . '<br>';
                echo '... ' . 'Data is not comma delimited' . '<br>';
                $this->success = false;
                //               return $this->success;
            }
            $pointer = 0;
            $cForename = 'Not found';
            $cSurname = 'Not found';
            $cEmail = 'Not found';
            $cGroup = 'Not found';
            foreach ($fields as $field) {
                if ($field == 'Forenames') {
                    $cForename = $pointer;
                } elseif ($field == 'Last Name') {
                    $cSurname = $pointer;
                } elseif ($field == 'Email Address') {
                    $cEmail = $pointer;
                } elseif ($field == 'Group Code') {
                    $cGroup = $pointer;
                }
                $pointer++;
            }
            echo 'Forename ' . $cForename . '<br>';
            echo 'Surname ' . $cSurname . '<br>';
            echo 'Email ' . $cEmail . '<br>';
            echo 'Group ' . $cGroup . '<br>';
        }
    } else {
        echo $line . '<br>Data is not comma delimited' . '<br>';
    }
}
echo 'Maximum number of fields  ' . $max . '<br>';

$objTable = new ToolsTable();
$objTable->add_header("Count,Field,1,2,3,4");
$pointer = 0;
$i = 0;
for ($pointer = 0; $pointer < $max; $pointer++) {
    $objTable->add_item($pointer);
    for ($i = 0; $i < 5; $i++) {
        $objTable->add_item($records[$i][$pointer]);
    }
    if ($pointer == 26) {
        $colour = 'red';
    } else {
        $colour = 'blue';
    }
    $objTable->generate_line($colour);
}
$objTable->generate_table();

$toolsHelper = new ToolsHelper;
$target = 'administrator/index.php?option=com_ra_mailman&view=dataload';
echo $toolsHelper->backButton($target);
