<?php

/**
 * Report Model - W5OBM Accounting System
 * File: /accounting/models/reportModel.php
 * Purpose: Data access layer for report operations
 * SECURITY: All database operations use prepared statements
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Report Model Class
 * Handles all database operations for reports
 */
class ReportModel
{
    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Save a report record
     * @param array $data Report data
     * @return bool|int Report ID on success, false on failure
     */
    public function create($data)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO acc_reports 
                (report_type, parameters, file_path, generated_by, generated_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");

            $parameters_json = json_encode($data['parameters'] ?? []);
            $generated_by = $data['generated_by'] ?? getCurrentUserId();

            // bind_param requires variables (not expressions/array offsets)
            $report_type = $data['report_type'] ?? '';
            $file_path = $data['file_path'] ?? null;
            $stmt->bind_param(
                'sssi',
                $report_type,
                $parameters_json,
                $file_path,
                $generated_by
            );

            if ($stmt->execute()) {
                $report_id = $this->conn->insert_id;
                $stmt->close();
                return $report_id;
            }

            $stmt->close();
            return false;
        } catch (Exception $e) {
            logError("Error creating report: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Get report by ID
     * @param int $id Report ID
     * @return array|false Report data or false if not found
     */
    public function getById($id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, u.username AS generated_by_username
                FROM acc_reports r
                LEFT JOIN auth_users u ON r.generated_by = u.id
                WHERE r.id = ?
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result && $result['parameters']) {
                $result['parameters'] = json_decode($result['parameters'], true);
            }

            return $result;
        } catch (Exception $e) {
            logError("Error getting report by ID: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Get all reports with optional filtering
     * @param array $filters Optional filters
     * @param array $options Optional limit, offset, order
     * @return array Reports array
     */
    public function getAll($filters = [], $options = [])
    {
        try {
            $where_conditions = [];
            $params = [];
            $types = '';

            // Build WHERE clause
            if (!empty($filters['report_type'])) {
                $where_conditions[] = "r.report_type = ?";
                $params[] = $filters['report_type'];
                $types .= 's';
            }

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $where_conditions[] = "r.generated_at BETWEEN ? AND ?";
                $params[] = $filters['start_date'] . ' 00:00:00';
                $params[] = $filters['end_date'] . ' 23:59:59';
                $types .= 'ss';
            }

            if (!empty($filters['generated_by'])) {
                $where_conditions[] = "r.generated_by = ?";
                $params[] = $filters['generated_by'];
                $types .= 'i';
            }

            $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

            // Order clause
            $order_by = $options['order_by'] ?? 'r.generated_at DESC';

            $query = "
                SELECT r.*, u.username AS generated_by_username
                FROM acc_reports r
                LEFT JOIN auth_users u ON r.generated_by = u.id
                $where_clause
                ORDER BY $order_by
            ";

            // Add limit if specified
            if (!empty($options['limit'])) {
                $query .= " LIMIT ?";
                $params[] = $options['limit'];
                $types .= 'i';

                if (!empty($options['offset'])) {
                    $query .= " OFFSET ?";
                    $params[] = $options['offset'];
                    $types .= 'i';
                }
            }

            $stmt = $this->conn->prepare($query);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $reports = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['parameters']) {
                    $row['parameters'] = json_decode($row['parameters'], true);
                }
                $reports[] = $row;
            }

            $stmt->close();
            return $reports;
        } catch (Exception $e) {
            logError("Error getting all reports: " . $e->getMessage(), 'accounting');
            return [];
        }
    }

    /**
     * Delete a report
     * @param int $id Report ID
     * @return bool Success status
     */
    public function delete($id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM acc_reports WHERE id = ?");
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            logError("Error deleting report: " . $e->getMessage(), 'accounting');
            return false;
        }
    }

    /**
     * Get recent reports
     * @param int $limit Number of reports to return
     * @return array Recent reports
     */
    public function getRecent($limit = 10)
    {
        return $this->getAll([], ['limit' => $limit]);
    }
}

// Legacy function wrappers for backward compatibility
if (!function_exists('save_report')) {
    function save_report($report_type, $parameters, $file_path = null)
    {
        $model = new ReportModel();
        $data = [
            'report_type' => $report_type,
            'parameters' => $parameters,
            'file_path' => $file_path
        ];
        return $model->create($data);
    }
}

if (!function_exists('fetch_report_by_id')) {
    function fetch_report_by_id($id)
    {
        $model = new ReportModel();
        return $model->getById($id);
    }
}

if (!function_exists('fetch_all_reports')) {
    function fetch_all_reports($report_type = null, $start_date = null, $end_date = null)
    {
        $model = new ReportModel();
        $filters = [];

        if ($report_type) $filters['report_type'] = $report_type;
        if ($start_date && $end_date) {
            $filters['start_date'] = $start_date;
            $filters['end_date'] = $end_date;
        }

        return $model->getAll($filters);
    }
}
