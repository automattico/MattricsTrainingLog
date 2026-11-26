<?php
declare(strict_types=1);

require_authenticated();
require_once MATTWARDEN_SITE_DIR . '/lib/bootstrap.php';
require_once MATTWARDEN_SITE_DIR . '/lib/workout-review.php';

$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='POST') {
    mattwarden_require_same_origin();
    mattwarden_require_csrf();
} elseif ($method!=='GET') {
    header('Allow: GET, POST');
    mattrics_send_json(['error'=>'Method not allowed.'],405);
}
if (!mattrics_foundation_read_enabled()) mattrics_send_json(['error'=>'Workout review unavailable.'],404);
$ctx=mattrics_foundation_read_context();
if (!workout_review_available($ctx)) mattrics_send_json(['error'=>'Workout review unavailable.'],404);
try {
    if ($method==='POST') {
        $state=workout_review_change($ctx,mattrics_read_json_body());
    } else {
        $ctx['pdo']->beginTransaction();
        $ctx['pdo']->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $state=workout_review_state($ctx);
        $ctx['pdo']->commit();
    }
    mattrics_send_json(workout_review_public($state));
} catch (DomainException $e) {
    mattrics_send_json(['error'=>$e->getMessage()],$e->getCode());
} catch (Throwable $e) {
    mattrics_send_json(['error'=>'Workout review unavailable.'],503);
}
