<?php

namespace App\Controllers;

use App\DAO\ReportDAO;
use App\DAO\TaskDAO;
use App\DAO\UserDAO;
use App\DAO\AuditLogDAO;
use App\Models\DamageReport;
use App\Models\IssueCategory;
use App\Models\SkillCategory;
use App\Models\ReportStatus;
use App\Utils\JWTUtil;
use App\Utils\GeoUtil;
use App\Utils\StorageUtil;
use App\Utils\ResponseUtil;
use App\Utils\CacheUtil;
use Exception;

class ReportController
{
    private ReportDAO $reportDAO;
    private TaskDAO $taskDAO;
    private UserDAO $userDAO;
    private AuditLogDAO $auditLogDAO;

    public function __construct()
    {
        $this->reportDAO = new ReportDAO();
        $this->taskDAO = new TaskDAO();
        $this->userDAO = new UserDAO();
        $this->auditLogDAO = new AuditLogDAO();
    }

    /**
     * Authenticate request and retrieve JWT user payload.
     */
    private function getAuthenticatedUser(): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return null;
        }
        $token = $matches[1];
        return JWTUtil::validateToken($token);
    }

    /**
     * Create a new damage report (POST /api/v1/reports)
     * Handles live photo storage, 50m EXIF GPS check, and baseline Pscore calculation.
     */
    public function createReport(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required to submit damage report.', 401);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $categoryStr = trim($data['category'] ?? '');
        $skillStr    = trim($data['required_skill'] ?? 'STRUCTURAL');
        $description = trim($data['description'] ?? '');
        $latitude    = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $longitude   = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $district    = trim($data['district'] ?? 'Kuala Lumpur');
        $photoBase64 = $data['photo_base64'] ?? ($data['photo_url'] ?? '');
        $exifLat     = isset($data['exif_lat']) ? (float)$data['exif_lat'] : null;
        $exifLng     = isset($data['exif_lng']) ? (float)$data['exif_lng'] : null;
        $exifTime    = $data['exif_timestamp'] ?? date('c');

        $errors = [];

        // 1. Validation
        if (empty($categoryStr)) {
            $errors['category'] = 'Hazard category is required.';
        }
        if (empty($description)) {
            $errors['description'] = 'Damage description is required.';
        } elseif (strlen($description) > 500) {
            $errors['description'] = 'Description cannot exceed 500 characters.';
        }
        if ($latitude === null || $longitude === null) {
            $errors['location'] = 'Facility GPS coordinates are required.';
        }
        if (empty($photoBase64)) {
            $errors['photo'] = 'Live photo evidence is required.';
        }

        if (!empty($errors)) {
            ResponseUtil::error('Validation failed.', 400, ['errors' => $errors]);
            return;
        }

        // 2. Map Category & Trade Enums
        try {
            $category = IssueCategory::from(strtoupper($categoryStr));
        } catch (\ValueError $e) {
            ResponseUtil::error('Invalid category. Must be CRITICAL_SAFETY, MAJOR_BLOCKAGE, MINOR_DEFECT, or AESTHETIC.', 400);
            return;
        }

        $skillUpper = strtoupper($skillStr);
        if ($skillUpper === 'NOT_SURE' || $skillUpper === 'OTHER') {
            $requiredSkill = SkillCategory::OTHER;
        } else {
            try {
                $requiredSkill = SkillCategory::from($skillUpper);
            } catch (\ValueError $e) {
                $requiredSkill = SkillCategory::OTHER;
            }
        }

        // 3. 100-Meter Anti-Fraud EXIF Distance Check
        if ($exifLat !== null && $exifLng !== null) {
            $distanceMeters = GeoUtil::haversineDistanceMeters($exifLat, $exifLng, $latitude, $longitude);
            if ($distanceMeters > 100.0) {
                ResponseUtil::error(
                    'Anti-fraud check failed: Selected facility location is ' . round($distanceMeters) . ' meters away from photo capture GPS (max 100m allowed).',
                    400,
                    [
                        'distance_mismatch' => true,
                        'distance_meters'   => round($distanceMeters, 1),
                        'max_allowed'       => 100.0
                    ]
                );
                return;
            }
        }

        try {
            // 4. Upload photo to Supabase Storage bucket 'ecoasset-media/reports/'
            $filename = StorageUtil::generateFilename('report', 'jpg');
            if (strpos($photoBase64, 'http://') === 0 || strpos($photoBase64, 'https://') === 0) {
                $photoUrl = $photoBase64;
            } else {
                $photoUrl = StorageUtil::uploadBase64Image($photoBase64, StorageUtil::FOLDER_REPORTS, $filename);
            }

            // 5. Instantiate DamageReport Model
            $report = new DamageReport(
                userId: $user['user_id'],
                category: $category,
                description: $description,
                latitude: $latitude,
                longitude: $longitude,
                photoUrl: $photoUrl,
                exifTimestamp: $exifTime,
                requiredSkill: $requiredSkill,
                upvoteCount: 1,
                priorityScore: 0.0,
                status: ReportStatus::OPEN_FOR_CLAIM,
                district: $district
            );

            // 6. Ingest into database via ReportDAO (computes Dzone and initial Pscore)
            $reportId = $this->reportDAO->createReport($report);

            if (!$reportId) {
                ResponseUtil::error('Failed to create damage report record in database.', 500);
                return;
            }

            // 7. Auto-run spatial dispatch algorithm immediately upon report creation
            $dispatchResult = $this->taskDAO->autoDispatchSingleReport($reportId);

            // 8. Audit log creation & automated dispatch event
            try {
                $statusDesc = $dispatchResult['dispatched'] 
                    ? "automatically dispatched to crew {$dispatchResult['assigned_crew_name']} (24h acceptance window)"
                    : "placed in Open Maintenance Pool ({$dispatchResult['reason']})";

                $this->auditLogDAO->log(
                    $user['user_id'],
                    'REPORT_CREATED',
                    "Report {$reportId} created under category {$category->value} ({$district}) and {$statusDesc}."
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            // 9. Return created report with live dispatch details
            $createdData = $report->jsonSerialize();
            $createdData['report_id'] = $reportId;
            $createdData['status'] = $dispatchResult['status'];
            $createdData['reporter_name'] = $user['full_name'] ?? 'Citizen Reporter';
            $createdData['dispatch_result'] = $dispatchResult;

            CacheUtil::flush();
            $successMsg = $dispatchResult['dispatched']
                ? "Damage report submitted! Automatically dispatched to {$dispatchResult['assigned_crew_name']} (24h SLA acceptance window)."
                : "Damage report submitted! Placed in Open Maintenance Pool for crew claiming or scheduled batch dispatch.";

            ResponseUtil::success($createdData, $successMsg, 201);

        } catch (Exception $e) {
            ResponseUtil::error('Service error creating report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get active incident reports for the map (GET /api/v1/reports)
     */
    public function getActiveReports(): void
    {
        $user = $this->getAuthenticatedUser();
        $userId = $user['user_id'] ?? null;

        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
        $radiusKm = isset($_GET['radius']) ? (float)$_GET['radius'] : null;

        try {
            $reports = $this->reportDAO->getActiveReports($lat, $lng, $radiusKm);

            // Augment with author status and upvote state
            foreach ($reports as &$rpt) {
                $isAuthor = ($userId && isset($rpt['user_id']) && $rpt['user_id'] === $userId);
                $hasVoted = ($userId ? $this->reportDAO->hasUserUpvoted($rpt['report_id'], $userId) : false);

                $rpt['is_author'] = $isAuthor;
                $rpt['has_upvoted'] = ($isAuthor || $hasVoted);
                $rpt['upvote_count'] = max(1, (int)($rpt['upvote_count'] ?? 1));
                $rpt['priority_score'] = (float)($rpt['priority_score'] ?? 0.0);
            }

            ResponseUtil::success(['reports' => $reports], 'Active reports retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve active reports: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single report details by ID (GET /api/v1/reports/{id})
     */
    public function getReportById(string $reportId): void
    {
        try {
            $report = $this->reportDAO->getReportById($reportId);
            if (!$report) {
                ResponseUtil::error('Damage report not found.', 404);
                return;
            }

            $user = $this->getAuthenticatedUser();
            if ($user && isset($user['user_id'])) {
                $isAuthor = ($report['user_id'] === $user['user_id']);
                $hasVoted = $this->reportDAO->hasUserUpvoted($reportId, $user['user_id']);
                $report['is_author'] = $isAuthor;
                $report['has_upvoted'] = ($isAuthor || $hasVoted);
            } else {
                $report['is_author'] = false;
                $report['has_upvoted'] = false;
            }

            $report['upvote_count'] = max(1, (int)($report['upvote_count'] ?? 1));

            ResponseUtil::success(['report' => $report], 'Report details retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Error fetching report details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upvote a damage report (POST /api/v1/reports/{id}/upvote)
     */
    public function upvoteReport(string $reportId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required to upvote reports.', 401);
            return;
        }

        try {
            $res = $this->reportDAO->addUpvote($reportId, $user['user_id']);

            if (!$res['success']) {
                if (isset($res['error']) && strpos($res['error'], 'already upvoted') !== false) {
                    ResponseUtil::error('You have already upvoted this incident.', 409);
                    return;
                }
                ResponseUtil::error($res['error'] ?? 'Unable to register upvote.', 400);
                return;
            }

            // Audit log upvote event
            try {
                $this->auditLogDAO->log($user['user_id'], 'REPORT_UPVOTED', "User upvoted report {$reportId}.");
            } catch (Exception $e) {
                // Non-blocking
            }

            CacheUtil::flush();
            ResponseUtil::success($res, 'Upvote registered! Priority score recalculated.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to process upvote: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get citizen activity history (GET /api/v1/user/activity)
     */
    public function getUserActivity(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user || !isset($user['user_id'])) {
            ResponseUtil::error('Authentication required.', 401);
            return;
        }

        try {
            $db = \App\Config\Database::getConnection();
            $userId = $user['user_id'];

            // 1. My Reports
            $stmt1 = $db->prepare("SELECT r.* FROM damage_reports r WHERE r.user_id = :uid1 AND r.status NOT IN ('COMPLETED', 'INVALID_RESOLVED') ORDER BY r.created_at DESC");
            $stmt1->execute(['uid1' => $userId]);
            $myReports = $stmt1->fetchAll();

            // 2. Upvoted Reports
            $stmt2 = $db->prepare("SELECT r.* FROM damage_reports r JOIN report_upvotes u ON r.report_id = u.report_id WHERE u.user_id = :uid2 AND r.status NOT IN ('COMPLETED', 'INVALID_RESOLVED') ORDER BY u.created_at DESC");
            $stmt2->execute(['uid2' => $userId]);
            $upvotedReports = $stmt2->fetchAll();

            // 3. Closed Archive (Completed or Flagged Invalid)
            $stmt3 = $db->prepare("SELECT r.*, t.resolution_notes, t.drop_reason, t.flag_invalid_notes, t.completion_photo_url, t.completed_at, u.full_name as crew_name 
                                   FROM damage_reports r 
                                   LEFT JOIN maintenance_tasks t ON r.report_id = t.report_id 
                                   LEFT JOIN users u ON t.assigned_crew_id = u.user_id
                                   WHERE (r.user_id = :uid3 OR r.report_id IN (SELECT report_id FROM report_upvotes WHERE user_id = :uid4))
                                     AND r.status IN ('COMPLETED', 'INVALID_RESOLVED') 
                                   ORDER BY r.created_at DESC");
            $stmt3->execute(['uid3' => $userId, 'uid4' => $userId]);
            $closedArchive = $stmt3->fetchAll();

            // Ensure upvote_count is at least 1
            foreach ($myReports as &$r) {
                $r['upvote_count'] = max(1, (int)($r['upvote_count'] ?? 1));
            }
            foreach ($upvotedReports as &$r) {
                $r['upvote_count'] = max(1, (int)($r['upvote_count'] ?? 1));
            }
            foreach ($closedArchive as &$r) {
                $r['upvote_count'] = max(1, (int)($r['upvote_count'] ?? 1));
            }

            ResponseUtil::success([
                'my_reports'      => $myReports,
                'upvoted_reports' => $upvotedReports,
                'closed_archive'  => $closedArchive
            ], 'User activity retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve user activity: ' . $e->getMessage(), 500);
        }
    }
}
