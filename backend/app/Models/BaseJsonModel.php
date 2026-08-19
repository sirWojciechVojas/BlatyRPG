<?php

namespace App\Models;

use CodeIgniter\Model;

abstract class BaseJsonModel extends Model
{
    /** @var string[] */
    protected $jsonFields = [];

    protected $beforeInsert = ['encodeJsonFields'];
    protected $beforeUpdate = ['encodeJsonFields'];
    protected $afterFind = ['decodeJsonFields'];

    protected function encodeJsonFields(array $data): array
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        foreach ($this->jsonFields as $field) {
            if (!array_key_exists($field, $data['data'])) {
                continue;
            }

            $value = $data['data'][$field];
            if (is_array($value) || is_object($value)) {
                $data['data'][$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    protected function decodeJsonFields(array $data): array
    {
        if (!isset($data['data'])) {
            return $data;
        }

        $decodeRow = function (&$row): void {
            if (!is_array($row)) {
                return;
            }
            foreach ($this->jsonFields as $field) {
                if (!isset($row[$field]) || !is_string($row[$field])) {
                    continue;
                }
                $decoded = json_decode($row[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$field] = $decoded;
                }
            }
        };

        if (($data['singleton'] ?? false) === false && is_array($data['data'])) {
            foreach ($data['data'] as &$row) {
                $decodeRow($row);
            }
        } else {
            $decodeRow($data['data']);
        }

        return $data;
    }
}
