<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\AnalyticsDAO;

header('Content-Type: application/json');

$dao = new AnalyticsDAO();
$kpis = $dao->getKpiSummary();
$queue = $dao->getPrioritizedQueue();
$hotspots30d = $dao->getHotspotFrequencyByDistrict('All Districts', '30d');
$hotspots7d = $dao->getHotspotFrequencyByDistrict('KLCC', '7d');
$correlation30d = $dao->getResolutionDelaysVsFootfall('All Districts', '30d');
$correlation7d = $dao->getResolutionDelaysVsFootfall('KLCC', '7d');
$crews = $dao->getCrewWorkloadAndAvailability();

echo json_encode([
    'status' => 'SUCCESS',
    'kpis' => $kpis,
    'queue_count' => count($queue),
    'hotspots_30d' => $hotspots30d,
    'hotspots_7d_klcc' => $hotspots7d,
    'correlation_30d' => $correlation30d,
    'correlation_7d_klcc' => $correlation7d,
    'crews_count' => count($crews)
], JSON_PRETTY_PRINT);
