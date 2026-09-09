<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\AnalyticsDAO;

header('Content-Type: application/json');

$dao = new AnalyticsDAO();
$kpis = $dao->getKpiSummary();
$queue = $dao->getPrioritizedQueue();
$hotspots = $dao->getHotspotFrequencyByDistrict();
$correlation = $dao->getResolutionDelaysVsFootfall();
$crews = $dao->getCrewWorkloadAndAvailability();
$activeDrawer = $dao->getActiveQueueDrawerData();
$resDrawer = $dao->getResolutionTimeDrawerData();
$incidentDrawer = $dao->getIncidentIntakeDrawerData();
$crewDrawer = $dao->getCrewFleetDrawerData();

echo json_encode([
    'status' => 'SUCCESS',
    'kpis' => $kpis,
    'queue_count' => count($queue),
    'hotspots_count' => count($hotspots),
    'correlation_labels' => $correlation['labels'],
    'crews_count' => count($crews),
    'active_drawer_pending' => $activeDrawer['summary']['total_pending'],
    'res_drawer_avg_days' => $resDrawer['metrics']['avg_resolution_days'],
    'incident_drawer_total' => $incidentDrawer['total_reported'],
    'crew_drawer_total' => $crewDrawer['total_crews'],
    'crew_drawer_skills' => $crewDrawer['skills']
], JSON_PRETTY_PRINT);
