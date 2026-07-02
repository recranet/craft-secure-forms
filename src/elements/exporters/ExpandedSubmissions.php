<?php

namespace recranet\secureforms\elements\exporters;

use Craft;
use craft\base\ElementExporter;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use craft\helpers\Json;
use recranet\secureforms\elements\Submission;

/**
 * CSV/JSON export with the JSON `message` column decoded: every dynamic form
 * field becomes its own column, normalized across all exported submissions.
 */
class ExpandedSubmissions extends ElementExporter
{
    public static function displayName(): string
    {
        return Craft::t('secure-forms', 'Submissions (expanded fields)');
    }

    public function export(ElementQueryInterface $query): array
    {
        $rows = [];
        $fieldKeys = [];

        /** @var Submission $submission */
        foreach (Db::each($query) as $submission) {
            $fields = [];

            // Flatten the decoded message: nested arrays become readable strings
            foreach ($submission->getMessage() as $key => $value) {
                if (is_array($value)) {
                    $flat = iterator_to_array(
                        new \RecursiveIteratorIterator(new \RecursiveArrayIterator($value)),
                        false
                    );
                    $value = implode(', ', array_map(strval(...), $flat));
                } elseif (!is_scalar($value) && $value !== null) {
                    $value = Json::encode($value);
                }

                $fields[$key] = $value;
                $fieldKeys[$key] = true;
            }

            $rows[] = [
                'id' => $submission->id,
                'dateCreated' => $submission->dateCreated?->format('Y-m-d H:i:s'),
                'form' => $submission->form,
                'subject' => $submission->subject,
                'fromName' => $submission->fromName,
                'fromEmail' => $submission->fromEmail,
                'status' => $submission->getStatus(),
                'spamScore' => $submission->spamScore,
                'spamReason' => $submission->spamReason,
                '_fields' => $fields,
            ];
        }

        // Normalize: every row gets every field column, in a stable order
        $fieldKeys = array_keys($fieldKeys);

        return array_map(function(array $row) use ($fieldKeys) {
            $fields = $row['_fields'];
            unset($row['_fields']);

            foreach ($fieldKeys as $key) {
                // Prefix to avoid collisions with the fixed columns
                $row["field:$key"] = $fields[$key] ?? '';
            }

            return $row;
        }, $rows);
    }
}
