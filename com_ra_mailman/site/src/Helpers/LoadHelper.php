<?php

/**
 * Header-driven CSV mapping and validation for the MailMan DataLoad workflow.
 *
 * The class deliberately returns structured data so the
 * administrator view can render messages without the helper emitting HTML.
 * 22/09/26 Created by GPT 5.6 Luna
 */

namespace Ramblers\Component\Ra_mailman\Site\Helpers;

defined('_JEXEC') or die;

use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\DataLoadProcessor;

class LoadHelper {

    public const TYPE_INSIGHT = 3;
    public const TYPE_MAILCHIMP = 4;
    public const TYPE_SIMPLE = 5;

    private ToolsHelper $toolsHelper;

    public function __construct(?ToolsHelper $toolsHelper = null) {
        $this->toolsHelper = $toolsHelper ?? new ToolsHelper();
    }

    /**
     * Run the established pass-two processor.  Keeping this adapter here
     * removes persistence orchestration from the administrator template while
     * the remaining subscription/lapsed logic is migrated in Phase 15.
     */
    public function processFile(array $options): bool {
        $processor = new DataLoadProcessor();
        foreach (['method_id', 'list_id', 'processing', 'filename', 'report_id'] as $property) {
            if (array_key_exists($property, $options)) {
                $processor->{$property} = $options[$property];
            }
        }

        return $processor->processFile() === true;
    }

    private function aliasesFor(int $dataType): array {
        return match ($dataType) {
            self::TYPE_INSIGHT => [
        'forename' => ['Forenames', 'First Name'],
        'surname' => ['Last Name', 'Surname'],
        'email' => ['Email Address', 'Email'],
                'group_code' => ['Group Code', 'Group', 'Group/group'],
            ],
            self::TYPE_MAILCHIMP => [
        'email' => ['Email address'],
        'forename' => ['First Name'],
        'surname' => ['Last Name'],
            ],
            self::TYPE_SIMPLE => [
                'group_code' => ['Group Code', 'Group', 'Group/group'],
        'name' => ['Name', 'Real Name', 'Full Name'],
        'email' => ['Email Address', 'Email'],
            ],
        };
    }

    private function getListGroupCode(int $listId): string {
        if ($listId < 1) {
            return '';
        }

        $value = $this->toolsHelper->getValue(
                'SELECT group_code FROM #__ra_mail_lists WHERE id = ' . $listId . ' LIMIT 1'
        );

        return strtoupper(trim((string) $value));
    }

    private function isBlankRow(array $row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isCommentRow(array $row): bool {
        return isset($row[0]) && str_starts_with(ltrim((string) $row[0]), '#');
    }

    /**
     * Convert a source row into the common MailMan import shape.
     */
    public function mapRow(int $dataType, array $row, array $headerMap): array {
        $indexes = $headerMap['indexes'] ?? [];
        $value = static function (array $row, array $indexes, string $field): string {
            $index = $indexes[$field] ?? null;

            return $index === null ? '' : trim((string) ($row[$index] ?? ''));
        };

        $forename = $value($row, $indexes, 'forename');
        $surname = $value($row, $indexes, 'surname');
        $name = $dataType === self::TYPE_SIMPLE ? $value($row, $indexes, 'name') : trim($forename . ' ' . $surname);

        return [
            'group_code' => $dataType === self::TYPE_MAILCHIMP ? trim((string) ($headerMap['group_code'] ?? '')) : $value($row, $indexes, 'group_code'),
            'name' => $name,
            'email' => $value($row, $indexes, 'email'),
        ];
    }

    private function normaliseHeading(string $heading): string {
        /*
        * Remove Byte Order Mark (BOM, typically \xEF\xBB\xBF), trim whitespace,
        * replace non-alphanumeric with space, collapse whitespace, lowercase.
        */
        $heading = preg_replace('/^\xEF\xBB\xBF/u', '', trim($heading));
        $heading = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $heading);

        return strtolower(trim((string) preg_replace('/\s+/u', ' ', $heading)));
    }

    /**
     * Resolve required fields from a CSV header row.
     *
     * @param   int    $dataType  MailMan DataLoad source type.
     * @param   array  $headers   Raw CSV header row.
     * @param   int    $listId    Selected list, used by MailChimp mapping.
     *
     * @return  array{indexes: array<string,int>, headings: array<int,string>, group_code: string|null}
     */
    public function resolveHeaders(int $dataType, array $headers, int $listId = 0): array {
        if (!in_array($dataType, [self::TYPE_INSIGHT, self::TYPE_MAILCHIMP, self::TYPE_SIMPLE], true)) {
            throw new \InvalidArgumentException('Unsupported MailMan DataLoad type: ' . $dataType);
        }

        $normalised = [];
        $display = [];

        foreach ($headers as $index => $heading) {
            $heading = trim((string) $heading);

            if ($index === 0) {
                $heading = preg_replace('/^\xEF\xBB\xBF/u', '', $heading);
            }

            $display[$index] = $heading;
            $key = $this->normaliseHeading($heading);

            if ($key === '') {
                continue;
            }

            if (isset($normalised[$key])) {
                throw new \InvalidArgumentException(
                                'The CSV header contains duplicate heading "' . $heading . '".'
                );
            }

            $normalised[$key] = (int) $index;
        }

        $aliases = $this->aliasesFor($dataType);
        $indexes = [];
        $missing = [];

        foreach ($aliases as $canonical => $names) {
            $index = null;

            foreach ($names as $name) {
                $key = $this->normaliseHeading($name);

                if (array_key_exists($key, $normalised)) {
                    $index = $normalised[$key];
                    break;
                }
            }

            if ($index === null) {
                // Use the source-facing label in diagnostics; group_code is
                // an internal canonical name and should not be shown here.
                $missing[] = $canonical === 'group_code' ? 'group' : $canonical;
            } else {
                $indexes[$canonical] = $index;
            }
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                            'The CSV is missing required heading(s): ' . implode(', ', $missing)
                            . '. Headings found: ' . implode(', ', $display)
            );
        }

        $groupCode = null;

        if ($dataType === self::TYPE_MAILCHIMP) {
            $groupCode = $this->getListGroupCode($listId);

            if ($groupCode === '') {
                throw new \InvalidArgumentException(
                                'The selected mailing list does not have a valid group code.'
                );
            }
        }

        return [
            'indexes' => $indexes,
            'headings' => $display,
            'group_code' => $groupCode,
        ];
    }

    /**
     * Scan and validate a CSV file without changing application data.
     * Malformed rows are returned as errors and do not stop the scan.
     *
     * @return array{headers: array<int,string>, header_map: array, rows: array<int,array>, errors: array<int,array>}
     */
    public function scanFile(string $filename, int $dataType, int $listId = 0): array {
        if ($filename === '' || !is_readable($filename)) {
            throw new \InvalidArgumentException('Unable to read the CSV file: ' . $filename);
        }

        $handle = fopen($filename, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open the CSV file: ' . $filename);
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false || $headers === [null]) {
                throw new \InvalidArgumentException('The CSV file does not contain a header row.');
            }

            $headerMap = $this->resolveHeaders($dataType, $headers, $listId);
            $rows = [];
            $errors = [];
            $lineNumber = 1;

            while (($rawRow = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($rawRow === [null] || $this->isBlankRow($rawRow) || $this->isCommentRow($rawRow)) {
                    continue;
                }

                // Additional source columns are permitted and ignored. A row
                // with fewer columns cannot safely satisfy the header map.
                if (count($rawRow) < count($headers)) {
                    $errors[] = [
                        'line' => $lineNumber,
                        'row' => $rawRow,
                        'messages' => ['The row has fewer columns than the header.'],
                    ];
                    continue;
                }

                $mapped = $this->mapRow($dataType, $rawRow, $headerMap);
                $messages = $this->validateRow($mapped, $dataType);

                if ($messages !== []) {
                    $errors[] = [
                        'line' => $lineNumber,
                        'row' => $rawRow,
                        'mapped' => $mapped,
                        'messages' => $messages,
                    ];
                    continue;
                }

                $rows[] = [
                    'line' => $lineNumber,
                    'source' => $rawRow,
                    'mapped' => $mapped,
                ];
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers' => array_map(static fn($value): string => trim((string) $value), $headers),
            'header_map' => $headerMap,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * Validate one common-shape row. Empty rows should be filtered by caller.
     *
     * @return  string[]
     */
    public function validateRow(array $row, int $dataType = 0): array {
        $errors = [];
        $groupCode = trim((string) ($row['group_code'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));

        if ($groupCode === '') {
            $errors[] = 'Group code is blank.';
        } elseif ($this->toolsHelper->validGroupcode($groupCode) === false) {
            $errors[] = 'Invalid group code ' . $groupCode . '.';
        }

        if ($name === '') {
            $errors[] = 'Name is blank.';
        }

        if ($email === '') {
            // A missing email is expected for some membership records.  It
            // is counted by the processor, but is not a validation error.
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Invalid email ' . $email . ' found for ' . ($name !== '' ? $name : 'unknown member') . '.';
        }

        return $errors;
    }

}
